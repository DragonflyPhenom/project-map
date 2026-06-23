<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Parser;

use IaroslavKhmel\ProjectMap\Support\PathNormalizer;
use IaroslavKhmel\ProjectMap\Support\TypeFormatter;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class ClassParser
{
    public function __construct(
        private readonly MethodParser $methodParser = new MethodParser(),
        private readonly CallParser $callParser = new CallParser(),
    ) {
    }

    /**
     * @return array{classes: list<array<string, mixed>>, methods: list<array<string, mixed>>, calls: list<array<string, mixed>>, warnings: list<string>}
     */
    public function parseFile(string $file, string $projectPath): array
    {
        $code = file_get_contents($file);
        if (!is_string($code)) {
            return ['classes' => [], 'methods' => [], 'calls' => [], 'warnings' => ['Unable to read ' . $file]];
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?? [];
        } catch (\Throwable $exception) {
            return ['classes' => [], 'methods' => [], 'calls' => [], 'warnings' => ['Parse error in ' . $file . ': ' . $exception->getMessage()]];
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $ast = $traverser->traverse($ast);

        $visitor = new class($file, $projectPath, $this->methodParser, $this->callParser) extends NodeVisitorAbstract {
            /** @var list<array<string, mixed>> */
            public array $classes = [];
            /** @var list<array<string, mixed>> */
            public array $methods = [];
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            private ?string $currentClass = null;
            /** @var array<string, string> */
            private array $classProperties = [];
            /** @var list<array<string, mixed>> */
            private array $currentClassMethods = [];

            public function __construct(
                private readonly string $file,
                private readonly string $projectPath,
                private readonly MethodParser $methodParser,
                private readonly CallParser $callParser,
            ) {
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_ || $node instanceof Node\Stmt\Enum_) {
                    $this->enterClassLike($node);
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_ || $node instanceof Node\Stmt\Enum_) {
                    $class = array_pop($this->classes);
                    $class['methods'] = $this->currentClassMethods;
                    $this->classes[] = $class;
                    $this->currentClass = null;
                    $this->classProperties = [];
                    $this->currentClassMethods = [];
                }

                return null;
            }

            private function enterClassLike(Node $node): void
            {
                $name = $node->namespacedName ?? $node->name ?? null;
                if (!$name instanceof Node\Name && !$name instanceof Node\Identifier) {
                    return;
                }

                $className = $name->toString();
                $this->currentClass = $className;
                $this->classProperties = $this->properties($node);

                $methods = [];
                foreach ($node->stmts as $stmt) {
                    if (!$stmt instanceof Node\Stmt\ClassMethod) {
                        continue;
                    }

                    $method = $this->methodParser->parse($stmt, $className);
                    $methods[] = $method;
                    $this->methods[] = $method;
                    $this->calls = array_merge($this->calls, $this->calls($stmt, $method['id'], $className));
                }

                $this->currentClassMethods = $methods;
                $parts = explode('\\', $className);
                $namespace = count($parts) > 1 ? implode('\\', array_slice($parts, 0, -1)) : null;

                $this->classes[] = [
                    'name' => $className,
                    'short_name' => end($parts),
                    'namespace' => $namespace,
                    'type' => $this->type($node),
                    'extends' => $node instanceof Node\Stmt\Class_ && $node->extends instanceof Node\Name ? $node->extends->toString() : null,
                    'implements' => $node instanceof Node\Stmt\Class_ ? array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements) : [],
                    'traits' => $this->traits($node),
                    'methods' => $methods,
                    'file' => PathNormalizer::relative($this->file, $this->projectPath),
                ];
            }

            private function type(Node $node): string
            {
                return match (true) {
                    $node instanceof Node\Stmt\Interface_ => 'interface',
                    $node instanceof Node\Stmt\Trait_ => 'trait',
                    $node instanceof Node\Stmt\Enum_ => 'enum',
                    default => 'class',
                };
            }

            /**
             * @return list<string>
             */
            private function traits(Node $node): array
            {
                if (!$node instanceof Node\Stmt\Class_ && !$node instanceof Node\Stmt\Trait_) {
                    return [];
                }

                $traits = [];
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\TraitUse) {
                        foreach ($stmt->traits as $trait) {
                            $traits[] = $trait->toString();
                        }
                    }
                }

                return $traits;
            }

            /**
             * @return array<string, string>
             */
            private function properties(Node $node): array
            {
                if (!$node instanceof Node\Stmt\Class_) {
                    return [];
                }

                $properties = [];
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Property) {
                        $type = TypeFormatter::format($stmt->type);
                        foreach ($stmt->props as $property) {
                            if ($type !== null) {
                                $properties[$property->name->toString()] = $type;
                            }
                        }
                    }

                    if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                        foreach ($stmt->params as $param) {
                            if (($param->flags & (Node\Stmt\Class_::MODIFIER_PUBLIC | Node\Stmt\Class_::MODIFIER_PROTECTED | Node\Stmt\Class_::MODIFIER_PRIVATE)) === 0) {
                                continue;
                            }

                            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                                $type = TypeFormatter::format($param->type);
                                if ($type !== null) {
                                    $properties[$param->var->name] = $type;
                                }
                            }
                        }
                    }
                }

                return $properties;
            }

            /**
             * @return list<array<string, mixed>>
             */
            private function calls(Node\Stmt\ClassMethod $method, string $sourceMethod, string $className): array
            {
                $knownTypes = $this->classProperties;

                foreach ($method->params as $param) {
                    if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                        $type = TypeFormatter::format($param->type);
                        if ($type !== null) {
                            $knownTypes[$param->var->name] = $type;
                        }
                    }
                }

                $visitor = new class($sourceMethod, $className, $knownTypes, $this->callParser) extends NodeVisitorAbstract {
                    /** @var list<array<string, mixed>> */
                    public array $calls = [];

                    /** @param array<string, string> $knownTypes */
                    public function __construct(
                        private readonly string $sourceMethod,
                        private readonly string $className,
                        private readonly array $knownTypes,
                        private readonly CallParser $callParser,
                    ) {
                    }

                    public function enterNode(Node $node)
                    {
                        $call = $this->callParser->parse($node, $this->sourceMethod, $this->className, $this->knownTypes);
                        if ($call !== null) {
                            $this->calls[] = $call;
                        }

                        return null;
                    }
                };
                $traverser = new NodeTraverser();
                $traverser->addVisitor($visitor);
                $traverser->traverse($method->stmts ?? []);

                return $visitor->calls;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return ['classes' => $visitor->classes, 'methods' => $visitor->methods, 'calls' => $visitor->calls, 'warnings' => []];
    }
}
