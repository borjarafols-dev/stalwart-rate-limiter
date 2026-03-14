<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\ProviderStateRepositoryInterface;
use App\Entity\ProviderState;
use App\Service\RateLimiterMetrics;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimiterMetricsTest extends TestCase
{
    private CounterInterface $levelChangesCounter;
    private CounterInterface $webhookEventsCounter;
    private RateLimiterMetrics $metrics;
    /** @var callable */
    private $gaugeCallback;

    protected function setUp(): void
    {
        $this->levelChangesCounter = $this->createMock(CounterInterface::class);
        $this->webhookEventsCounter = $this->createMock(CounterInterface::class);

        $meter = $this->createMock(MeterInterface::class);
        $meter->method('createCounter')
            ->willReturnCallback(fn (string $name): CounterInterface => match ($name) {
                'ratelimiter.level_changes' => $this->levelChangesCounter,
                'ratelimiter.webhook_events' => $this->webhookEventsCounter,
                default => $this->createMock(CounterInterface::class),
            });

        $gauge = $this->createMock(ObservableGaugeInterface::class);
        $meter->method('createObservableGauge')
            ->willReturnCallback(function (string $name, ?string $unit, ?string $description, array $advisory, callable ...$callbacks) use ($gauge): ObservableGaugeInterface {
                if ([] !== $callbacks) {
                    $this->gaugeCallback = $callbacks[0];
                }

                return $gauge;
            });

        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $meterProvider->method('getMeter')->willReturn($meter);

        $repository = $this->createMock(ProviderStateRepositoryInterface::class);
        $repository->method('findAllActive')->willReturn([
            new ProviderState('gmail'),
            new ProviderState('microsoft'),
        ]);

        $this->metrics = new RateLimiterMetrics($meterProvider, $repository);
    }

    #[Test]
    public function recordLevelChangeIncrementsCounter(): void
    {
        $this->levelChangesCounter->expects(self::once())
            ->method('add')
            ->with(1, ['provider' => 'gmail', 'direction' => 'decreased']);

        $this->metrics->recordLevelChange('gmail', 'decreased');
    }

    #[Test]
    public function recordWebhookEventIncrementsCounter(): void
    {
        $this->webhookEventsCounter->expects(self::once())
            ->method('add')
            ->with(1, ['event_type' => 'delivery.completed', 'provider' => 'gmail']);

        $this->metrics->recordWebhookEvent('delivery.completed', 'gmail');
    }

    #[Test]
    public function observableGaugeCallbackReportsProviderLevels(): void
    {
        self::assertNotNull($this->gaugeCallback);

        $observer = $this->createMock(ObserverInterface::class);
        $observer->expects(self::exactly(2))
            ->method('observe')
            ->willReturnCallback(function (int|float $value, iterable $attributes): void {
                $attrs = $attributes instanceof \Traversable ? iterator_to_array($attributes) : (array) $attributes;
                self::assertSame(2, $value);
                self::assertContains($attrs['provider'], ['gmail', 'microsoft']);
            });

        ($this->gaugeCallback)($observer);
    }
}
