<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Contract\LimitApplierInterface;
use App\Contract\ProviderMapperInterface;
use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\RateLevelEngineInterface;
use App\Contract\RateLimiterMetricsInterface;
use App\Entity\ProviderState;
use App\Enum\LevelChange;
use App\Message\ProcessDeliveryEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessDeliveryEventHandler
{
    /** @var list<string> */
    private const array SUCCESS_EVENTS = ['delivery.completed', 'dsn.success'];

    public function __construct(
        private RateLevelEngineInterface $rateLevelEngine,
        private ProviderMapperInterface $providerMapper,
        private LimitApplierInterface $limitApplier,
        private ProviderStateRepositoryInterface $providerStateRepository,
        private RateLimiterMetricsInterface $metrics,
    ) {
    }

    public function __invoke(ProcessDeliveryEvent $message): void
    {
        $providerName = $this->providerMapper->resolve($message->rcptDomain);

        $state = $this->providerStateRepository->findByProvider($providerName)
            ?? new ProviderState($providerName);

        $success = \in_array($message->type, self::SUCCESS_EVENTS, true);
        $errorType = $success ? null : self::classifyError($message->status);
        $change = $this->rateLevelEngine->processEvent($state, $success, $errorType);

        $this->metrics->recordWebhookEvent($message->type, $providerName);

        if (LevelChange::None !== $change) {
            $this->limitApplier->apply($state);
            $this->metrics->recordLevelChange($providerName, strtolower($change->name));
        }

        $this->providerStateRepository->save($state);
    }

    private static function classifyError(?string $status): ?string
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
