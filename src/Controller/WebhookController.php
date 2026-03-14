<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\ProcessDeliveryEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class WebhookController
{
    public function __construct(
        private MessageBusInterface $messageBus,
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

        $type = $decoded['type'] ?? null;
        $domain = $decoded['rcpt_domain'] ?? null;

        if (!\is_string($type) || !\is_string($domain)) {
            return new JsonResponse(['status' => 'skipped']);
        }

        $this->messageBus->dispatch(new ProcessDeliveryEvent(
            type: $type,
            rcptDomain: $domain,
            status: isset($decoded['status']) && \is_string($decoded['status']) ? $decoded['status'] : null,
        ));

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
}
