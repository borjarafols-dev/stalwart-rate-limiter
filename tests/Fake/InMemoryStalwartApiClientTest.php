<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use PHPUnit\Framework\TestCase;

final class InMemoryStalwartApiClientTest extends TestCase
{
    private InMemoryStalwartApiClient $client;

    protected function setUp(): void
    {
        $this->client = new InMemoryStalwartApiClient();
    }

    public function testGetSettingsReturnsEmptyArrayWhenNoSettings(): void
    {
        self::assertSame([], $this->client->getSettings('any.prefix'));
    }

    public function testSetSettingsStoresKeyValues(): void
    {
        $this->client->setSettings([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
        ]);

        self::assertSame([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
        ], $this->client->getSettings('queue.limiter.outbound.google'));
    }

    public function testGetSettingsFiltersOnPrefix(): void
    {
        $this->client->setSettings([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.yahoo.rate' => '50/1h',
        ]);

        $result = $this->client->getSettings('queue.limiter.outbound.google');

        self::assertSame(['queue.limiter.outbound.google.rate' => '100/1h'], $result);
    }

    public function testSetSettingsOverwritesExistingKeys(): void
    {
        $this->client->setSettings(['key' => 'old']);
        $this->client->setSettings(['key' => 'new']);

        self::assertSame(['key' => 'new'], $this->client->getAll());
    }

    public function testDeleteSettingsRemovesMatchingKeys(): void
    {
        $this->client->setSettings([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
            'queue.limiter.outbound.yahoo.rate' => '50/1h',
        ]);

        $this->client->deleteSettings('queue.limiter.outbound.google');

        self::assertSame(
            ['queue.limiter.outbound.yahoo.rate' => '50/1h'],
            $this->client->getAll(),
        );
    }

    public function testDeleteSettingsHandlesTrailingDot(): void
    {
        $this->client->setSettings([
            'queue.limiter.outbound.google.rate' => '100/1h',
        ]);

        $this->client->deleteSettings('queue.limiter.outbound.google.');

        self::assertSame([], $this->client->getAll());
    }

    public function testApplyRateLimitDeletesThenSetsCorrectKeys(): void
    {
        // Pre-populate with old settings
        $this->client->setSettings([
            'queue.limiter.outbound.google.rate' => 'old',
            'queue.limiter.outbound.google.concurrency' => '1',
            'queue.limiter.outbound.google.match' => 'old.com',
        ]);

        $this->client->applyRateLimit('google', ['gmail.com', 'googlemail.com'], '100/1h', 10);

        self::assertSame([
            'queue.limiter.outbound.google.rate' => '100/1h',
            'queue.limiter.outbound.google.concurrency' => '10',
            'queue.limiter.outbound.google.match' => 'gmail.com,googlemail.com',
        ], $this->client->getAll());
    }

    public function testGetAllReturnsAllSettings(): void
    {
        $this->client->setSettings(['a' => '1', 'b' => '2']);

        self::assertSame(['a' => '1', 'b' => '2'], $this->client->getAll());
    }

    public function testResetClearsAllSettings(): void
    {
        $this->client->setSettings(['key' => 'value']);
        $this->client->reset();

        self::assertSame([], $this->client->getAll());
    }
}
