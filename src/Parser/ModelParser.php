<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class ModelParser
{
    public const LARAVEL_RELATIONS = [
        'hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany', 'morphTo', 'morphToMany',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function parseLaravelFile(string $file): array
    {
        $ast = $this->ast($file);
        if ($ast === null) {
            return [];
        }

        $visitor = new class extends NodeVisitorAbstract {
            /** @var list<array<string, mixed>> */
            public array $models = [];

            public function enterNode(Node $node)
            {
                if (!$node instanceof Node\Stmt\Class_) {
                    return null;
                }

                $className = $node->namespacedName?->toString();
                if ($className === null || !$this->isEloquent($node)) {
                    return null;
                }

                $model = [
                    'class' => $className,
                    'kind' => 'eloquent',
                    'table' => $this->table($node, $className),
                    'fillable' => $this->arrayProperty($node, 'fillable'),
                    'guarded' => $this->arrayProperty($node, 'guarded'),
                    'casts' => $this->arrayProperty($node, 'casts', true),
                    'hidden' => $this->arrayProperty($node, 'hidden'),
                    'appends' => $this->arrayProperty($node, 'appends'),
                    'fields' => $this->phpDocProperties($node),
                    'relations' => $this->relations($node),
                ];

                $this->models[] = $model;

                return null;
            }

            private function isEloquent(Node\Stmt\Class_ $class): bool
            {
                return $class->extends instanceof Node\Name && str_ends_with($class->extends->toString(), 'Model');
            }

            private function table(Node\Stmt\Class_ $class, string $className): string
            {
                $table = $this->scalarProperty($class, 'table');
                if ($table !== null) {
                    return $table;
                }

                $short = substr($className, strrpos($className, '\\') + 1);
                $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));

                return str_ends_with($snake, 's') ? $snake : $snake . 's';
            }

            private function scalarProperty(Node\Stmt\Class_ $class, string $name): ?string
            {
                foreach ($class->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\Property) {
                        continue;
                    }

                    foreach ($stmt->props as $property) {
                        if ($property->name->toString() === $name && $property->default instanceof Node\Scalar\String_) {
                            return $property->default->value;
                        }
                    }
                }

                return null;
            }

            /**
             * @return array<int|string, mixed>
             */
            private function arrayProperty(Node\Stmt\Class_ $class, string $name, bool $preserveKeys = false): array
            {
                foreach ($class->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\Property) {
                        continue;
                    }

                    foreach ($stmt->props as $property) {
                        if ($property->name->toString() !== $name || !$property->default instanceof Node\Expr\Array_) {
                            continue;
                        }

                        $values = [];
                        foreach ($property->default->items as $item) {
                            if ($item === null) {
                                continue;
                            }
                            $value = $item->value instanceof Node\Scalar\String_ ? $item->value->value : (new Standard())->prettyPrintExpr($item->value);
                            if ($preserveKeys && $item->key instanceof Node\Scalar\String_) {
                                $values[$item->key->value] = $value;
                            } else {
                                $values[] = $value;
                            }
                        }

                        return $values;
                    }
                }

                return [];
            }

            /**
             * @return list<array<string, string|null>>
             */
            private function relations(Node\Stmt\Class_ $class): array
            {
                $relations = [];
                foreach ($class->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\ClassMethod) {
                        continue;
                    }

                    foreach (($stmt->stmts ?? []) as $inner) {
                        if (!$inner instanceof Node\Stmt\Return_ || !$inner->expr instanceof Node\Expr\MethodCall) {
                            continue;
                        }

                        $call = $inner->expr;
                        if (!$call->name instanceof Node\Identifier || !in_array($call->name->toString(), ModelParser::LARAVEL_RELATIONS, true)) {
                            continue;
                        }

                        $relations[] = [
                            'method' => $stmt->name->toString(),
                            'type' => $call->name->toString(),
                            'target_model' => $this->firstClassArgument($call),
                            'foreign_key' => $call->args[1]->value instanceof Node\Scalar\String_ ? $call->args[1]->value->value : null,
                        ];
                    }
                }

                return $relations;
            }

            private function firstClassArgument(Node\Expr\MethodCall $call): ?string
            {
                $first = $call->args[0]->value ?? null;
                if ($first instanceof Node\Expr\ClassConstFetch && $first->class instanceof Node\Name) {
                    return $first->class->toString();
                }

                if ($first instanceof Node\Scalar\String_) {
                    return $first->value;
                }

                return null;
            }

            /**
             * @return list<array{name: string, type: string|null}>
             */
            private function phpDocProperties(Node\Stmt\Class_ $class): array
            {
                $doc = $class->getDocComment()?->getText() ?? '';
                preg_match_all('/@property(?:-read|-write)?\s+([^\s]+)\s+\$([A-Za-z_][A-Za-z0-9_]*)/', $doc, $matches, PREG_SET_ORDER);

                return array_map(static fn (array $match): array => ['name' => $match[2], 'type' => $match[1]], $matches);
            }
        };

        $this->traverse($ast, $visitor);

        return $visitor->models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseDoctrineFile(string $file): array
    {
        $ast = $this->ast($file);
        if ($ast === null) {
            return [];
        }

        $visitor = new class extends NodeVisitorAbstract {
            /** @var list<array<string, mixed>> */
            public array $models = [];

            public function enterNode(Node $node)
            {
                if (!$node instanceof Node\Stmt\Class_) {
                    return null;
                }

                $className = $node->namespacedName?->toString();
                if ($className === null || !$this->hasAttribute($node, 'Entity')) {
                    return null;
                }

                $this->models[] = [
                    'class' => $className,
                    'kind' => 'doctrine',
                    'table' => $this->table($node, $className),
                    'fields' => $this->fields($node),
                    'relations' => $this->relations($node),
                ];

                return null;
            }

            private function hasAttribute(Node $node, string $suffix): bool
            {
                foreach ($node->attrGroups as $group) {
                    foreach ($group->attrs as $attr) {
                        if (str_ends_with($attr->name->toString(), $suffix)) {
                            return true;
                        }
                    }
                }

                return false;
            }

            private function table(Node\Stmt\Class_ $class, string $className): string
            {
                foreach ($class->attrGroups as $group) {
                    foreach ($group->attrs as $attr) {
                        if (!str_ends_with($attr->name->toString(), 'Table')) {
                            continue;
                        }
                        foreach ($attr->args as $arg) {
                            if (($arg->name?->toString() === 'name' || $arg->name === null) && $arg->value instanceof Node\Scalar\String_) {
                                return $arg->value->value;
                            }
                        }
                    }
                }

                $short = substr($className, strrpos($className, '\\') + 1);

                return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
            }

            /**
             * @return list<array<string, mixed>>
             */
            private function fields(Node\Stmt\Class_ $class): array
            {
                $fields = [];
                foreach ($class->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\Property) {
                        continue;
                    }

                    foreach ($stmt->props as $property) {
                        foreach ($stmt->attrGroups as $group) {
                            foreach ($group->attrs as $attr) {
                                if (!str_ends_with($attr->name->toString(), 'Column')) {
                                    continue;
                                }
                                $fields[] = [
                                    'name' => $property->name->toString(),
                                    'type' => $this->arg($attr, 'type'),
                                    'nullable' => $this->boolArg($attr, 'nullable'),
                                ];
                            }
                        }
                    }
                }

                return $fields;
            }

            /**
             * @return list<array<string, mixed>>
             */
            private function relations(Node\Stmt\Class_ $class): array
            {
                $relations = [];
                $relationTypes = ['OneToMany', 'ManyToOne', 'OneToOne', 'ManyToMany'];
                foreach ($class->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\Property) {
                        continue;
                    }

                    foreach ($stmt->props as $property) {
                        foreach ($stmt->attrGroups as $group) {
                            foreach ($group->attrs as $attr) {
                                $name = $attr->name->toString();
                                foreach ($relationTypes as $type) {
                                    if (str_ends_with($name, $type)) {
                                        $relations[] = [
                                            'method' => $property->name->toString(),
                                            'type' => $type,
                                            'target_model' => $this->arg($attr, 'targetEntity'),
                                            'foreign_key' => null,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

                return $relations;
            }

            private function arg(Node\Attribute $attr, string $name): ?string
            {
                foreach ($attr->args as $arg) {
                    if ($arg->name?->toString() !== $name) {
                        continue;
                    }
                    if ($arg->value instanceof Node\Scalar\String_) {
                        return $arg->value->value;
                    }
                    if ($arg->value instanceof Node\Expr\ClassConstFetch && $arg->value->class instanceof Node\Name) {
                        return $arg->value->class->toString();
                    }
                }

                return null;
            }

            private function boolArg(Node\Attribute $attr, string $name): bool
            {
                foreach ($attr->args as $arg) {
                    if ($arg->name?->toString() === $name && $arg->value instanceof Node\Expr\ConstFetch) {
                        return strtolower($arg->value->name->toString()) === 'true';
                    }
                }

                return false;
            }
        };

        $this->traverse($ast, $visitor);

        return $visitor->models;
    }

    /**
     * @return list<Node>|null
     */
    private function ast(string $file): ?array
    {
        $code = file_get_contents($file);
        if (!is_string($code)) {
            return null;
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?? [];
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());

            return $traverser->traverse($ast);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<Node> $ast
     */
    private function traverse(array $ast, NodeVisitorAbstract $visitor): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
    }
}
