<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProviderState;
use App\Tests\Fake\InMemoryLimitApplier;
use Doctrine\ORM\EntityManagerInterface;
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

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\ProviderState')->execute();
    }

    public function testValidSignatureWithSuccessEventReturnsProcessed(): void
    {
        $payload = json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"processed"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testValidSignatureWithDsnSuccessReturnsProcessed(): void
    {
        $payload = json_encode(['type' => 'dsn.success', 'rcpt_domain' => 'outlook.com'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();
    }

    public function testMissingSignatureReturns401(): void
    {
        $payload = json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/webhook/stalwart', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidSignatureReturns401(): void
    {
        $payload = json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/webhook/stalwart', server: [
            'HTTP_X_SIGNATURE' => 'invalid-signature',
            'CONTENT_TYPE' => 'application/json',
        ], content: $payload);

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidJsonReturns400(): void
    {
        $payload = 'not-json';

        $this->sendWebhook($payload);

        self::assertResponseStatusCodeSame(400);
    }

    public function testMissingTypeReturnsSkipped(): void
    {
        $payload = json_encode(['rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"skipped"}',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testMissingDomainReturnsSkipped(): void
    {
        $payload = json_encode(['type' => 'delivery.completed'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

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

    public function testNewProviderIsCreatedAtDefaultLevel(): void
    {
        $payload = json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $state = $em->getRepository(ProviderState::class)->find('gmail');

        self::assertNotNull($state);
        self::assertSame(2, $state->getCurrentLevel());
        self::assertSame(1, $state->getSuccessCount());
    }

    public function testFailureEventUpdatesState(): void
    {
        $payload = json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'yahoo.com', 'status' => '550'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $state = $em->getRepository(ProviderState::class)->find('yahoo');

        self::assertNotNull($state);
        self::assertSame(1, $state->getFailCount());
        self::assertSame(0, $state->getSuccessCount());
    }

    public function testLevelChangeTriggerLimitApplier(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $payload = json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'icloud.com', 'status' => '550'], \JSON_THROW_ON_ERROR);

            $this->sendWebhook($payload);

            self::assertResponseIsSuccessful();
        }

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(1, $applier->getAppliedStates());
        self::assertSame('apple', $applier->getAppliedStates()[0]->getProvider());
        self::assertSame(1, $applier->getAppliedStates()[0]->getCurrentLevel());
    }

    public function testSuccessEventDoesNotTriggerLimitApplier(): void
    {
        $payload = json_encode(['type' => 'delivery.completed', 'rcpt_domain' => 'hotmail.com'], \JSON_THROW_ON_ERROR);

        $this->sendWebhook($payload);

        self::assertResponseIsSuccessful();

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(0, $applier->getAppliedStates());
    }

    public function test4xxFailureEventClassifiedCorrectly(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $payload = json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'msn.com', 'status' => '421'], \JSON_THROW_ON_ERROR);

            $this->sendWebhook($payload);
        }

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(1, $applier->getAppliedStates());
        self::assertSame(1, $applier->getAppliedStates()[0]->getCurrentLevel());
    }

    public function testFailureEventWithoutStatusDoesNotDemote(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $payload = json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'gmail.com'], \JSON_THROW_ON_ERROR);

            $this->sendWebhook($payload);

            self::assertResponseIsSuccessful();
        }

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(0, $applier->getAppliedStates());
    }

    public function testFailureEventWithUnknownStatusDoesNotDemote(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $payload = json_encode(['type' => 'delivery.failed', 'rcpt_domain' => 'gmail.com', 'status' => 'unknown'], \JSON_THROW_ON_ERROR);

            $this->sendWebhook($payload);

            self::assertResponseIsSuccessful();
        }

        /** @var InMemoryLimitApplier $applier */
        $applier = self::getContainer()->get(InMemoryLimitApplier::class);

        self::assertCount(0, $applier->getAppliedStates());
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
