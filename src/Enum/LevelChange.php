<?php

declare(strict_types=1);

namespace App\Enum;

enum LevelChange
{
    case None;
    case Decreased;
    case Increased;
}
