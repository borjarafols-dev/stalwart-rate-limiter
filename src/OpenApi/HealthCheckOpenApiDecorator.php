<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final readonly class HealthCheckOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $openApi->getPaths()->addPath('/healthz', new PathItem(
            get: new Operation(
                operationId: 'getHealthCheck',
                tags: ['Health'],
                responses: [
                    '200' => new Response(description: 'Service is healthy'),
                ],
                summary: 'Health check',
                description: 'Returns 200 OK when the service is running.',
            ),
        ));

        return $openApi;
    }
}
