<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework;

enum FrameworkType: string
{
    case Auto = 'auto';
    case Laravel = 'laravel';
    case Symfony = 'symfony';
    case Generic = 'generic';

    public static function fromInput(string $framework): self
    {
        return self::tryFrom(strtolower($framework)) ?? self::Auto;
    }
}
