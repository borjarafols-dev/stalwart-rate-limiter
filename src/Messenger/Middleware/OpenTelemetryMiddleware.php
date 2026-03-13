<?php

declare(strict_types=1);

namespace App\Messenger\Middleware;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class OpenTelemetryMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $tracer = Globals::tracerProvider()->getTracer('messenger');
        $class = $envelope->getMessage()::class;
        $name = substr($class, (int) strrpos($class, '\\') + 1);
        $isConsuming = null !== $envelope->last(ReceivedStamp::class);

        $span = $tracer->spanBuilder($name.' '.($isConsuming ? 'process' : 'publish'))
            ->setSpanKind($isConsuming ? SpanKind::KIND_CONSUMER : SpanKind::KIND_PRODUCER)
            ->setAttribute('messaging.system', 'symfony_messenger')
            ->setAttribute('messaging.destination', $name)
            ->startSpan();

        $scope = $span->activate();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }

        return $envelope;
    }
}
