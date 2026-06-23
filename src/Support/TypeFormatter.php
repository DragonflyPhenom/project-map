<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Support;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;

final class TypeFormatter
{
    public static function format(Node|string|null $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (is_string($type)) {
            return $type;
        }

        if ($type instanceof Name || $type instanceof Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType) {
            return '?' . self::format($type->type);
        }

        if ($type instanceof Node\UnionType) {
            return implode('|', array_map([self::class, 'format'], $type->types));
        }

        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map([self::class, 'format'], $type->types));
        }

        return $type instanceof Node\Expr ? (new Standard())->prettyPrintExpr($type) : null;
    }
}
