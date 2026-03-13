<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\StalwartApiClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class StalwartApiClient implements StalwartApiClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getSettings(string $prefix): array
    {
        $response = $this->httpClient->request('GET', '/api/settings/list', [
            'query' => ['prefix' => $prefix],
        ]);

        /** @var array{data: array{total: int, items: array<string, string>}} $data */
        $data = $response->toArray();

        if (0 === $data['data']['total']) {
            return [];
        }

        return $data['data']['items'];
    }

    /**
     * @param array<string, string> $keyValues
     */
    public function setSettings(array $keyValues): void
    {
        if ([] === $keyValues) {
            return;
        }

        $values = [];
        foreach ($keyValues as $key => $value) {
            $values[] = [$key, $value];
        }

        $this->httpClient->request('POST', '/api/settings', [
            'json' => [
                [
                    'type' => 'insert',
                    'values' => $values,
                    'assert_empty' => false,
                ],
            ],
        ]);
    }

    public function deleteSettings(string $prefix): void
    {
        $normalizedPrefix = str_ends_with($prefix, '.') ? $prefix : $prefix.'.';

        $this->httpClient->request('DELETE', '/api/settings/list', [
            'query' => ['prefix' => $normalizedPrefix],
        ]);
    }

    /**
     * @param list<string> $domains
     */
    public function applyRateLimit(string $provider, array $domains, string $rate, int $concurrency): void
    {
        $this->deleteSettings("queue.limiter.outbound.{$provider}");

        $this->setSettings([
            "queue.limiter.outbound.{$provider}.rate" => $rate,
            "queue.limiter.outbound.{$provider}.concurrency" => (string) $concurrency,
            "queue.limiter.outbound.{$provider}.match" => implode(',', $domains),
        ]);
    }
}
