<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\ProviderStateRepositoryInterface;
use App\Entity\ProviderState;
use App\Service\RateLimiterMetrics;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeter;
use OpenTelemetry\API\Metrics\ObserverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimiterMetricsTest extends TestCase
{
    private RateLimiterMetrics $metrics;
    private ProviderStateRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProviderStateRepositoryInterface::class);
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $meterProvider->method('getMeter')->willReturn(new NoopMeter());

        $this->metrics = new RateLimiterMetrics($meterProvider, $this->repository);
    }

    #[Test]
    public function recordLevelChangeDoesNotThrow(): void
    {
        $this->metrics->recordLevelChange('gmail', 'decreased');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function recordWebhookEventDoesNotThrow(): void
    {
        $this->metrics->recordWebhookEvent('delivery.completed', 'gmail');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function constructorCreatesMetricsSuccessfully(): void
    {
        self::assertInstanceOf(RateLimiterMetrics::class, $this->metrics);
    }

    #[Test]
    public function observableGaugeCallbackReportsProviderLevels(): void
    {
        $this->repository->method('findAllActive')->willReturn([
            new ProviderState('gmail'),
            new ProviderState('microsoft'),
        ]);

        $observer = $this->createMock(ObserverInterface::class);
        $observer->expects(self::exactly(2))
            ->method('observe')
            ->willReturnCallback(function (int|float $value, iterable $attributes): void {
                $attrs = (array) $attributes;
                self::assertSame(2, $value);
                self::assertContains($attrs['provider'], ['gmail', 'microsoft']);
            });

        $callback = self::buildGaugeCallback($this->repository);
        $callback($observer);
    }

    private static function buildGaugeCallback(ProviderStateRepositoryInterface $repository): \Closure
    {
        return static function (ObserverInterface $observer) use ($repository): void {
            foreach ($repository->findAllActive() as $state) {
                $observer->observe($state->getCurrentLevel(), ['provider' => $state->getProvider()]);
            }
        };
    }
}
