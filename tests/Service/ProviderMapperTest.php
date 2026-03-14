<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProviderMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderMapperTest extends TestCase
{
    private ProviderMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProviderMapper();
    }

    #[Test]
    #[DataProvider('domainProvider')]
    public function resolveMapsDomainToProvider(string $input, string $expectedProvider): void
    {
        self::assertSame($expectedProvider, $this->mapper->resolve($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function domainProvider(): iterable
    {
        yield 'gmail.com' => ['gmail.com', 'gmail'];
        yield 'googlemail.com' => ['googlemail.com', 'gmail'];
        yield 'outlook.com' => ['outlook.com', 'microsoft'];
        yield 'hotmail.com' => ['hotmail.com', 'microsoft'];
        yield 'live.com' => ['live.com', 'microsoft'];
        yield 'msn.com' => ['msn.com', 'microsoft'];
        yield 'yahoo.com' => ['yahoo.com', 'yahoo'];
        yield 'ymail.com' => ['ymail.com', 'yahoo'];
        yield 'aol.com' => ['aol.com', 'yahoo'];
        yield 'yahoo.co.uk' => ['yahoo.co.uk', 'yahoo'];
        yield 'icloud.com' => ['icloud.com', 'apple'];
        yield 'me.com' => ['me.com', 'apple'];
        yield 'mac.com' => ['mac.com', 'apple'];
        yield 'unknown domain' => ['example.org', 'default'];
        yield 'email with gmail' => ['user@gmail.com', 'gmail'];
        yield 'email with outlook' => ['user@outlook.com', 'microsoft'];
        yield 'email with unknown' => ['user@custom.org', 'default'];
        yield 'uppercase domain' => ['GMAIL.COM', 'gmail'];
        yield 'mixed case email' => ['User@GmAiL.CoM', 'gmail'];
    }
}
