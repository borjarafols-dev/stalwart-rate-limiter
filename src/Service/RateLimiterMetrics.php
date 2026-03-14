<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ProviderStateRepositoryInterface;
use App\Contract\RateLimiterMetricsInterface;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;

final readonly class RateLimiterMetrics implements RateLimiterMetricsInterface
{
    private CounterInterface $levelChanges;
    private CounterInterface $webhookEvents;

    public function __construct(
        MeterProviderInterface $meterProvider,
        ProviderStateRepositoryInterface $providerStateRepository,
    ) {
        $meter = $meterProvider->getMeter('stalwart-ratelimiter');

        $this->levelChanges = $meter->createCounter(
            'ratelimiter.level_changes',
            description: 'Number of rate level changes',
        );

        $this->webhookEvents = $meter->createCounter(
            'ratelimiter.webhook_events',
            description: 'Webhook events received by type',
        );

        $meter->createObservableGauge(
            'ratelimiter.current_level',
            description: 'Current rate level per provider',
            callbacks: static function (ObserverInterface $observer) use ($providerStateRepository): void {
                foreach ($providerStateRepository->findAllActive() as $state) {
                    $observer->observe($state->getCurrentLevel(), ['provider' => $state->getProvider()]);
                }
            },
        );
    }

    public function recordLevelChange(string $provider, string $direction): void
    {
        $this->levelChanges->add(1, ['provider' => $provider, 'direction' => $direction]);
    }

    public function recordWebhookEvent(string $eventType, string $provider): void
    {
        $this->webhookEvents->add(1, ['event_type' => $eventType, 'provider' => $provider]);
    }
}
