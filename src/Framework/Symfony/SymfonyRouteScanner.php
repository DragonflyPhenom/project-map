<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework\Symfony;

use IaroslavKhmel\ProjectMap\Support\PathNormalizer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class SymfonyRouteScanner
{
    /**
     * @param list<string> $files
     * @return list<array<string, mixed>>
     */
    public function scan(array $files, string $projectPath): array
    {
        $routes = [];
        foreach ($files as $file) {
            $routes = array_merge($routes, $this->scanFile($file, $projectPath));
        }

        return $routes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scanFile(string $file, string $projectPath): array
    {
        $code = file_get_contents($file);
        if (!is_string($code)) {
            return [];
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?? [];
            $resolver = new NodeTraverser();
            $resolver->addVisitor(new NameResolver());
            $ast = $resolver->traverse($ast);
        } catch (\Throwable) {
            return [];
        }

        $visitor = new class($file, $projectPath) extends NodeVisitorAbstract {
            /** @var list<array<string, mixed>> */
            public array $routes = [];
            private ?string $className = null;
            /** @var array<string, mixed> */
            private array $classRoute = [];

            public function __construct(private readonly string $file, private readonly string $projectPath)
            {
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->className = $node->namespacedName?->toString();
                    $this->classRoute = $this->routeAttribute($node) ?? [];
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    $route = $this->routeAttribute($node);
                    if ($route !== null) {
                        $prefix = $this->classRoute['path'] ?? '';
                        $route['path'] = $prefix . ($route['path'] ?? '');
                        $route['controller'] = $this->className;
                        $route['controller_method'] = $this->className ? $this->className . '::' . $node->name->toString() : $node->name->toString();
                        $route['source_file'] = PathNormalizer::relative($this->file, $this->projectPath);
                        $route['middleware'] = [];
                        $route['http_method'] = implode('|', $route['methods'] ?: ['ANY']);
                        $route['uri'] = $route['path'];
                        $this->routes[] = $route;
                    }
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->className = null;
                    $this->classRoute = [];
                }

                return null;
            }

            /**
             * @return array<string, mixed>|null
             */
            private function routeAttribute(Node $node): ?array
            {
                foreach ($node->attrGroups as $group) {
                    foreach ($group->attrs as $attr) {
                        if (!str_ends_with($attr->name->toString(), 'Route')) {
                            continue;
                        }

                        $route = ['path' => '', 'name' => null, 'methods' => []];
                        foreach ($attr->args as $arg) {
                            $name = $arg->name?->toString();
                            if (($name === null || $name === 'path') && $arg->value instanceof Node\Scalar\String_) {
                                $route['path'] = $arg->value->value;
                            } elseif ($name === 'name' && $arg->value instanceof Node\Scalar\String_) {
                                $route['name'] = $arg->value->value;
                            } elseif ($name === 'methods' && $arg->value instanceof Node\Expr\Array_) {
                                foreach ($arg->value->items as $item) {
                                    if ($item?->value instanceof Node\Scalar\String_) {
                                        $route['methods'][] = $item->value->value;
                                    }
                                }
                            }
                        }

                        return $route;
                    }
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->routes;
    }
}
