<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class RateTier
{
    public function __construct(
        public int $level,
        public string $rate,
        public int $concurrency,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            new self(0, '0.05/1s', 1),
            new self(1, '0.1/1s', 1),
            new self(2, '0.3/1s', 2),
            new self(3, '0.8/1s', 3),
            new self(4, '1.5/1s', 5),
            new self(5, '3/1s', 8),
        ];
    }

    public static function forLevel(int $level): self
    {
        if ($level < 0 || $level > 5) {
            throw new \InvalidArgumentException(\sprintf('Invalid tier level: %d. Must be between 0 and 5.', $level));
        }

        return self::all()[$level];
    }

    public const int DEFAULT_LEVEL = 2;

    public const int MIN_LEVEL = 0;

    public const int MAX_LEVEL = 5;
}
