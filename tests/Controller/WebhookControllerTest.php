<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Fake\InMemoryLimitApplier;
use App\Tests\Fake\InMemoryProviderStateRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebhookControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $webhookSecret;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var string $secret */
        $secret = self::getContainer()->getParameter('webhook_secret');
        $this->webhookSecret = $secret;
    }

    public function testValidSignatureWithSuccessEventReturnsProcessed(): void
    {
        $this->sendWebhook(json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"processed"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testMissingSignatureReturns401(): void
    {
        $this->client->request('POST', '/webhook/stalwart', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: '{"type":"delivery.completed","rcpt_domain":"gmail.com"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidSignatureReturns401(): void
    {
        $this->client->request('POST', '/webhook/stalwart', server: [
            'HTTP_X_SIGNATURE' => 'invalid',
            'CONTENT_TYPE' => 'application/json',
        ], content: '{"type":"delivery.completed","rcpt_domain":"gmail.com"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidJsonReturns400(): void
    {
        $this->sendWebhook('not-json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testMissingTypeReturnsSkipped(): void
    {
        $this->sendWebhook(json_encode(['rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"skipped"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testMissingDomainReturnsSkipped(): void
    {
        $this->sendWebhook(json_encode(['type' => 'delivery.completed'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"skipped"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testGetMethodReturns405(): void
    {
        $this->client->request('GET', '/webhook/stalwart');

        self::assertResponseStatusCodeSame(405);
    }

    public function testEndToEndSuccessEventCreatesProviderState(): void
    {
        $this->sendWebhook(json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        /** @var InMemoryProviderStateRepository $repo */
        $repo = self::getContainer()->get(InMemoryProviderStateRepository::class);
        $state = $repo->findByProvider('gmail');

        self::assertNotNull($state);
        self::assertSame(2, $state->getCurrentLevel());
        self::assertSame(1, $state->getSuccessCount());
    }

    public function testEndToEndFailureTriggersLimitApplier(): void
    {
        $this->client->disableReboot();

        for ($i = 0; $i < 3; ++$i) {
            $this->sendWebhook(json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'icloud.com', 'status' => '550'], \JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
        }

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(1, $applier->getAppliedStates());
        self::assertSame('apple', $applier->getAppliedStates()[0]->getProvider());
        self::assertSame(1, $applier->getAppliedStates()[0]->getCurrentLevel());
    }

    private function sendWebhook(string $payload): void
    {
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $this->client->request('POST', '/webhook/stalwart', server: [
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);
    }
}
