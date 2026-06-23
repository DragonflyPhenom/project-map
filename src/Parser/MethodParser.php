<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Parser;

use IaroslavKhmel\ProjectMap\Support\TypeFormatter;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\PrettyPrinter\Standard;

final class MethodParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(ClassMethod $method, string $className): array
    {
        $visibility = $this->visibility($method);
        $parameters = array_map([$this, 'parameter'], $method->params);
        $returnType = TypeFormatter::format($method->returnType);

        return [
            'id' => $className . '::' . $method->name->toString(),
            'class' => $className,
            'name' => $method->name->toString(),
            'visibility' => $visibility,
            'parameters' => $parameters,
            'return_type' => $returnType,
            'signature' => $this->signature($visibility, $method->name->toString(), $parameters, $returnType),
        ];
    }

    private function visibility(ClassMethod $method): string
    {
        if ($method->isPrivate()) {
            return 'private';
        }

        if ($method->isProtected()) {
            return 'protected';
        }

        return 'public';
    }

    /**
     * @return array<string, mixed>
     */
    private function parameter(Node\Param $param): array
    {
        $type = TypeFormatter::format($param->type);

        return [
            'name' => $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : 'unknown',
            'type' => $type,
            'nullable' => $param->type instanceof Node\NullableType,
            'default_exists' => $param->default !== null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $parameters
     */
    private function signature(string $visibility, string $name, array $parameters, ?string $returnType): string
    {
        $params = array_map(static function (array $parameter): string {
            $type = $parameter['type'] ? $parameter['type'] . ' ' : '';

            return $type . '$' . $parameter['name'];
        }, $parameters);

        return $visibility . ' ' . $name . '(' . implode(', ', $params) . ')' . ($returnType ? ': ' . $returnType : '');
    }
}
