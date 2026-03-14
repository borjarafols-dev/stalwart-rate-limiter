<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Contract\ProviderMapperInterface;

final class InMemoryProviderMapper implements ProviderMapperInterface
{
    /** @var array<string, string> */
    private array $domainMap = [
        'gmail.com' => 'gmail',
        'googlemail.com' => 'gmail',
        'outlook.com' => 'microsoft',
        'hotmail.com' => 'microsoft',
        'live.com' => 'microsoft',
        'msn.com' => 'microsoft',
        'yahoo.com' => 'yahoo',
        'ymail.com' => 'yahoo',
        'aol.com' => 'yahoo',
        'yahoo.co.uk' => 'yahoo',
        'icloud.com' => 'apple',
        'me.com' => 'apple',
        'mac.com' => 'apple',
    ];

    public function resolve(string $domain): string
    {
        $normalized = strtolower($domain);

        if (str_contains($normalized, '@')) {
            $normalized = substr($normalized, strrpos($normalized, '@') + 1);
        }

        return $this->domainMap[$normalized] ?? 'default';
    }

    /**
     * @return list<string>
     */
    public function domainsForProvider(string $provider): array
    {
        return array_values(array_keys(array_filter(
            $this->domainMap,
            static fn (string $p): bool => $p === $provider,
        )));
    }

    public function addMapping(string $domain, string $provider): void
    {
        $this->domainMap[strtolower($domain)] = $provider;
    }

    public function reset(): void
    {
        $this->domainMap = [];
    }
}
