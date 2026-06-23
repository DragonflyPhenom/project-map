<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework\Laravel;

use IaroslavKhmel\ProjectMap\Support\PathNormalizer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class LaravelRouteScanner
{
    public const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'resource', 'apiResource'];

    /**
     * @return list<array<string, mixed>>
     */
    public function scan(string $projectPath): array
    {
        $routesPath = rtrim($projectPath, '/') . '/routes';
        if (!is_dir($routesPath)) {
            return [];
        }

        $routes = [];
        foreach (glob($routesPath . '/*.php') ?: [] as $file) {
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

            public function __construct(private readonly string $file, private readonly string $projectPath)
            {
            }

            public function enterNode(Node $node)
            {
                if (!$node instanceof Node\Expr\StaticCall || !$node->name instanceof Node\Identifier) {
                    return null;
                }

                if (!$node->class instanceof Node\Name || !str_ends_with($node->class->toString(), 'Route')) {
                    return null;
                }

                $method = $node->name->toString();
                if (!in_array($method, LaravelRouteScanner::METHODS, true)) {
                    return null;
                }

                $uri = $node->args[0]->value ?? null;
                $action = $node->args[1]->value ?? null;

                $route = [
                    'http_method' => $this->httpMethod($method),
                    'uri' => $uri instanceof Node\Scalar\String_ ? $uri->value : '',
                    'name' => null,
                    'middleware' => [],
                    'controller' => null,
                    'controller_method' => null,
                    'source_file' => PathNormalizer::relative($this->file, $this->projectPath),
                ];

                if ($action instanceof Node\Expr\Array_ && count($action->items) >= 2) {
                    $class = $action->items[0]?->value;
                    $controllerMethod = $action->items[1]?->value;
                    if ($class instanceof Node\Expr\ClassConstFetch && $class->class instanceof Node\Name) {
                        $route['controller'] = $class->class->toString();
                    }
                    if ($controllerMethod instanceof Node\Scalar\String_) {
                        $route['controller_method'] = ($route['controller'] ? $route['controller'] . '::' : '') . $controllerMethod->value;
                    }
                } elseif ($action instanceof Node\Scalar\String_ && str_contains($action->value, '@')) {
                    [$controller, $controllerMethod] = explode('@', $action->value, 2);
                    $route['controller'] = $controller;
                    $route['controller_method'] = $controller . '::' . $controllerMethod;
                }

                $this->routes[] = $route;

                return null;
            }

            private function httpMethod(string $method): string
            {
                return match ($method) {
                    'resource', 'apiResource' => 'RESOURCE',
                    default => strtoupper($method),
                };
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->routes;
    }
}
