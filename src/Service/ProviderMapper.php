<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ProviderMapperInterface;

final readonly class ProviderMapper implements ProviderMapperInterface
{
    /** @var array<string, string> */
    private const array DOMAIN_MAP = [
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

        return self::DOMAIN_MAP[$normalized] ?? 'default';
    }

    /**
     * @return list<string>
     */
    public function domainsForProvider(string $provider): array
    {
        return array_keys(array_filter(
            self::DOMAIN_MAP,
            static fn (string $p): bool => $p === $provider,
        ));
    }
}
