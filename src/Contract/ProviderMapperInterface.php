<?php

declare(strict_types=1);

namespace App\Contract;

interface ProviderMapperInterface
{
    public function resolve(string $domain): string;
}
