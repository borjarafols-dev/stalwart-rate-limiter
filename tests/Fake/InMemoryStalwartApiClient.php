<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\StalwartApiClientInterface;

final class InMemoryStalwartApiClient implements StalwartApiClientInterface
{
    /** @var array<string, string> */
    private array $settings = [];

    /**
     * @return array<string, string>
     */
    public function getSettings(string $prefix): array
    {
        $result = [];
        foreach ($this->settings as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $keyValues
     */
    public function setSettings(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            $this->settings[$key] = $value;
        }
    }

    public function deleteSettings(string $prefix): void
    {
        $normalizedPrefix = str_ends_with($prefix, '.') ? $prefix : $prefix.'.';

        foreach (array_keys($this->settings) as $key) {
            if (str_starts_with($key, $normalizedPrefix)) {
                unset($this->settings[$key]);
            }
        }
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

    /**
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return $this->settings;
    }

    public function reset(): void
    {
        $this->settings = [];
    }
}
