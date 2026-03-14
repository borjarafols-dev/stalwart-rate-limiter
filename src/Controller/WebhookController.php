<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderMapperInterface;
use App\Contract\RateLevelEngineInterface;
use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\Repository\ProviderStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class WebhookController
{
    /** @var list<string> */
    private const array SUCCESS_EVENTS = ['delivery.completed', 'dsn.success'];

    public function __construct(
        private RateLevelEngineInterface $rateLevelEngine,
        private ProviderMapperInterface $providerMapper,
        private LimitApplierInterface $limitApplier,
        private ProviderStateRepository $providerStateRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('%webhook_secret%')] private string $webhookSecret,
    ) {
    }

    #[Route('/webhook/stalwart', name: 'webhook_stalwart', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (!$this->verifySignature($request->headers->get('X-Signature'), $payload)) {
            return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $decoded = json_decode($payload, true);

        if (!\is_array($decoded)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array{type?: string, rcpt_domain?: string, status?: string} $event */
        $event = $decoded;

        $type = $event['type'] ?? null;
        $domain = $event['rcpt_domain'] ?? null;

        if (null === $type || null === $domain) {
            return new JsonResponse(['status' => 'skipped']);
        }

        $providerName = $this->providerMapper->resolve($domain);

        $state = $this->providerStateRepository->findByProvider($providerName);
        if (null === $state) {
            $state = new ProviderState($providerName);
            $this->entityManager->persist($state);
        }

        $success = \in_array($type, self::SUCCESS_EVENTS, true);
        $errorType = $success ? null : $this->classifyError($event['status'] ?? null);
        $change = $this->rateLevelEngine->processEvent($state, $success, $errorType);

        if (LevelChange::None !== $change) {
            $this->limitApplier->apply($state);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'processed']);
    }

    private function verifySignature(?string $signature, string $payload): bool
    {
        if (null === $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    private function classifyError(?string $status): ?string
    {
        if (null === $status) {
            return null;
        }

        return match (true) {
            str_starts_with($status, '4') => '4xx',
            str_starts_with($status, '5') => '5xx',
            default => null,
        };
    }
}
