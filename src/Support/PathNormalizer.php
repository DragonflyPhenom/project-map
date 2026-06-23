<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Support;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function relative(string $path, string $root): string
    {
        $path = self::normalize($path);
        $root = rtrim(self::normalize($root), '/') . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
