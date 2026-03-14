<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryProviderMapperTest extends TestCase
{
    #[Test]
    public function resolveUsesDefaultMap(): void
    {
        $mapper = new InMemoryProviderMapper();

        self::assertSame('gmail', $mapper->resolve('gmail.com'));
        self::assertSame('microsoft', $mapper->resolve('outlook.com'));
        self::assertSame('default', $mapper->resolve('unknown.org'));
    }

    #[Test]
    public function addMappingOverridesExisting(): void
    {
        $mapper = new InMemoryProviderMapper();

        $mapper->addMapping('gmail.com', 'custom-gmail');

        self::assertSame('custom-gmail', $mapper->resolve('gmail.com'));
    }

    #[Test]
    public function addMappingAddsNewDomain(): void
    {
        $mapper = new InMemoryProviderMapper();

        $mapper->addMapping('custom.org', 'custom-provider');

        self::assertSame('custom-provider', $mapper->resolve('custom.org'));
    }

    #[Test]
    public function resetClearsAllMappings(): void
    {
        $mapper = new InMemoryProviderMapper();

        $mapper->reset();

        self::assertSame('default', $mapper->resolve('gmail.com'));
    }

    #[Test]
    public function resolveHandlesEmailAddresses(): void
    {
        $mapper = new InMemoryProviderMapper();

        self::assertSame('gmail', $mapper->resolve('user@gmail.com'));
    }

    #[Test]
    public function resolveIsCaseInsensitive(): void
    {
        $mapper = new InMemoryProviderMapper();

        self::assertSame('gmail', $mapper->resolve('GMAIL.COM'));
    }
}
