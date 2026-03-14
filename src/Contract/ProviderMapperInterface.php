<?php

declare(strict_types=1);

namespace App\Contract;

interface ProviderMapperInterface
{
    public function resolve(string $domain): string;

    /**
     * @return list<string>
     */
    public function domainsForProvider(string $provider): array;
}
