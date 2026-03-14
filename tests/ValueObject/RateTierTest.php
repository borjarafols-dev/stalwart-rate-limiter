<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\RateTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateTierTest extends TestCase
{
    #[Test]
    public function allReturnsSixTiers(): void
    {
        $tiers = RateTier::all();

        self::assertCount(6, $tiers);
    }

    #[Test]
    #[DataProvider('tierDataProvider')]
    public function forLevelReturnsCorrectTier(int $level, string $expectedRate, int $expectedConcurrency): void
    {
        $tier = RateTier::forLevel($level);

        self::assertSame($level, $tier->level);
        self::assertSame($expectedRate, $tier->rate);
        self::assertSame($expectedConcurrency, $tier->concurrency);
    }

    /**
     * @return iterable<string, array{int, string, int}>
     */
    public static function tierDataProvider(): iterable
    {
        yield 'level 0 - emergency' => [0, '0.05/1s', 1];
        yield 'level 1 - cautious' => [1, '0.1/1s', 1];
        yield 'level 2 - default' => [2, '0.3/1s', 2];
        yield 'level 3 - warming' => [3, '0.8/1s', 3];
        yield 'level 4 - warm' => [4, '1.5/1s', 5];
        yield 'level 5 - established' => [5, '3/1s', 8];
    }

    #[Test]
    public function forLevelThrowsOnInvalidLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RateTier::forLevel(6);
    }

    #[Test]
    public function forLevelThrowsOnNegativeLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RateTier::forLevel(-1);
    }

    #[Test]
    public function constantsAreCorrect(): void
    {
        self::assertSame(0, RateTier::MIN_LEVEL);
        self::assertSame(5, RateTier::MAX_LEVEL);
        self::assertSame(2, RateTier::DEFAULT_LEVEL);
    }

    #[Test]
    public function allTiersMatchForLevelLookup(): void
    {
        $tiers = RateTier::all();

        foreach ($tiers as $index => $tier) {
            $looked = RateTier::forLevel($index);
            self::assertSame($tier->level, $looked->level);
            self::assertSame($tier->rate, $looked->rate);
            self::assertSame($tier->concurrency, $looked->concurrency);
        }
    }
}
