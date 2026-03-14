<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\WebhookOpenApiDecorator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookOpenApiDecoratorTest extends TestCase
{
    #[Test]
    public function itAddsWebhookPath(): void
    {
        $inner = $this->createMock(OpenApiFactoryInterface::class);
        $inner->method('__invoke')->willReturn(
            new OpenApi(new Info('Test', '1.0.0'), [], new Paths()),
        );

        $decorator = new WebhookOpenApiDecorator($inner);
        $openApi = $decorator([]);

        $path = $openApi->getPaths()->getPath('/webhook/stalwart');
        self::assertNotNull($path);

        $operation = $path->getPost();
        self::assertNotNull($operation);
        self::assertSame('postStalwartWebhook', $operation->getOperationId());
        self::assertSame(['Webhook'], $operation->getTags());
        self::assertSame('Receive Stalwart delivery events', $operation->getSummary());
    }

    #[Test]
    public function itDefinesExpectedResponses(): void
    {
        $inner = $this->createMock(OpenApiFactoryInterface::class);
        $inner->method('__invoke')->willReturn(
            new OpenApi(new Info('Test', '1.0.0'), [], new Paths()),
        );

        $decorator = new WebhookOpenApiDecorator($inner);
        $openApi = $decorator([]);

        $operation = $openApi->getPaths()->getPath('/webhook/stalwart')?->getPost();
        self::assertNotNull($operation);

        $responses = $operation->getResponses();
        self::assertArrayHasKey('200', $responses);
        self::assertArrayHasKey('401', $responses);
        self::assertArrayHasKey('400', $responses);
    }

    #[Test]
    public function itPreservesExistingPaths(): void
    {
        $existingPaths = new Paths();
        $existingPaths->addPath('/healthz', new \ApiPlatform\OpenApi\Model\PathItem());

        $inner = $this->createMock(OpenApiFactoryInterface::class);
        $inner->method('__invoke')->willReturn(
            new OpenApi(new Info('Test', '1.0.0'), [], $existingPaths),
        );

        $decorator = new WebhookOpenApiDecorator($inner);
        $openApi = $decorator([]);

        self::assertNotNull($openApi->getPaths()->getPath('/healthz'));
        self::assertNotNull($openApi->getPaths()->getPath('/webhook/stalwart'));
    }
}
