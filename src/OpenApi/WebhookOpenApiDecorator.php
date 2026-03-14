<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final readonly class WebhookOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi->getPaths()->addPath('/webhook/stalwart', new PathItem(
            post: new Operation(
                operationId: 'postStalwartWebhook',
                tags: ['Webhook'],
                responses: [
                    '200' => new Response(description: 'Event processed successfully'),
                    '401' => new Response(description: 'Invalid or missing HMAC signature'),
                    '400' => new Response(description: 'Invalid JSON payload'),
                ],
                summary: 'Receive Stalwart delivery events',
                description: 'Processes delivery webhook events from Stalwart mail server. Requires a valid HMAC-SHA256 signature in the X-Signature header.',
                parameters: [
                    [
                        'name' => 'X-Signature',
                        'in' => 'header',
                        'required' => true,
                        'description' => 'HMAC-SHA256 signature of the request body',
                        'schema' => ['type' => 'string'],
                    ],
                ],
                requestBody: new RequestBody(
                    description: 'Stalwart delivery event payload',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['type', 'rcpt_domain'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'description' => 'Event type (e.g. delivery.completed, delivery.failed)',
                                    ],
                                    'rcpt_domain' => [
                                        'type' => 'string',
                                        'description' => 'Recipient email domain',
                                    ],
                                    'status' => [
                                        'type' => 'string',
                                        'description' => 'SMTP status code (e.g. 250, 421, 550)',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
        ));

        return $openApi;
    }
}
