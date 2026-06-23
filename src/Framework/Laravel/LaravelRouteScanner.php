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
        foreach ($this->routeFiles($routesPath) as $file) {
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

        $visitor = new class($file, $projectPath, $this->serviceProviderPrefix($file, $projectPath)) extends NodeVisitorAbstract {
            /** @var list<array<string, mixed>> */
            public array $routes = [];
            /** @var list<array{prefix: string, middleware: list<string>, controller: string|null}> */
            private array $groups = [];

            public function __construct(
                private readonly string $file,
                private readonly string $projectPath,
                private readonly string $basePrefix,
            ) {
            }

            public function enterNode(Node $node)
            {
                if ($this->isGroupCall($node)) {
                    $this->groups[] = $this->contextFromChain($node);
                }

                if ($node instanceof Node\Expr\StaticCall) {
                    $route = $this->routeFromStaticCall($node);
                    if ($route !== null) {
                        $this->routes[] = $route;
                    }
                }

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($this->isGroupCall($node)) {
                    array_pop($this->groups);
                }

                return null;
            }

            /**
             * @return array<string, mixed>|null
             */
            private function routeFromStaticCall(Node\Expr\StaticCall $node): ?array
            {
                if (!$node->name instanceof Node\Identifier || !$node->class instanceof Node\Name || !str_ends_with($node->class->toString(), 'Route')) {
                    return null;
                }

                $method = $node->name->toString();
                if (!in_array($method, LaravelRouteScanner::METHODS, true)) {
                    return null;
                }

                $context = $this->currentContext();
                $uri = $this->stringValue($node->args[0]->value ?? null) ?? '';
                $action = $node->args[1]->value ?? null;
                $controller = null;
                $controllerMethod = null;

                if ($method === 'resource' || $method === 'apiResource') {
                    $controller = $this->classValue($action);
                    $controllerMethod = $controller ? $controller . '::' . $method : null;
                } else {
                    [$controller, $controllerMethod] = $this->controllerAction($action, $context['controller']);
                }

                $uri = $this->joinUri($context['prefix'], $uri);

                return [
                    'http_method' => $this->httpMethod($method),
                    'uri' => $uri,
                    'name' => null,
                    'middleware' => $context['middleware'],
                    'controller' => $controller,
                    'controller_method' => $controllerMethod,
                    'source_file' => PathNormalizer::relative($this->file, $this->projectPath),
                ];
            }

            /**
             * @return array{0: string|null, 1: string|null}
             */
            private function controllerAction(?Node\Expr $action, ?string $groupController): array
            {
                if ($action instanceof Node\Expr\Array_ && count($action->items) >= 2) {
                    $controller = $this->classValue($action->items[0]?->value);
                    $method = $this->stringValue($action->items[1]?->value);

                    return [$controller, $controller && $method ? $controller . '::' . $method : null];
                }

                if ($action instanceof Node\Scalar\String_) {
                    if (str_contains($action->value, '@')) {
                        [$controller, $method] = explode('@', $action->value, 2);

                        return [$controller, $controller . '::' . $method];
                    }

                    if ($groupController !== null) {
                        return [$groupController, $groupController . '::' . $action->value];
                    }
                }

                return [$groupController, null];
            }

            private function isGroupCall(Node $node): bool
            {
                if (!$node instanceof Node\Expr\MethodCall && !$node instanceof Node\Expr\StaticCall) {
                    return false;
                }

                return $node->name instanceof Node\Identifier && $node->name->toString() === 'group';
            }

            /**
             * @return array{prefix: string, middleware: list<string>, controller: string|null}
             */
            private function contextFromChain(Node\Expr\MethodCall|Node\Expr\StaticCall $node): array
            {
                $context = ['prefix' => '', 'middleware' => [], 'controller' => null];
                $cursor = $node;
                while ($cursor instanceof Node\Expr\MethodCall || $cursor instanceof Node\Expr\StaticCall) {
                    if ($cursor->name instanceof Node\Identifier) {
                        $name = $cursor->name->toString();
                        if ($name === 'prefix') {
                            $context['prefix'] = $this->joinUri($this->stringValue($cursor->args[0]->value ?? null) ?? '', $context['prefix']);
                        } elseif ($name === 'middleware') {
                            $context['middleware'] = array_values(array_merge($this->middlewareValues($cursor->args[0]->value ?? null), $context['middleware']));
                        } elseif ($name === 'controller') {
                            $context['controller'] = $this->classValue($cursor->args[0]->value ?? null) ?? $context['controller'];
                        }
                    }

                    $cursor = $cursor instanceof Node\Expr\MethodCall ? $cursor->var : null;
                }

                return $context;
            }

            /**
             * @return array{prefix: string, middleware: list<string>, controller: string|null}
             */
            private function currentContext(): array
            {
                $context = ['prefix' => $this->basePrefix, 'middleware' => [], 'controller' => null];
                foreach ($this->groups as $group) {
                    $context = $this->mergeContext($context, $group);
                }

                return $context;
            }

            /**
             * @param array{prefix: string, middleware: list<string>, controller: string|null} $base
             * @param array{prefix: string, middleware: list<string>, controller: string|null} $next
             * @return array{prefix: string, middleware: list<string>, controller: string|null}
             */
            private function mergeContext(array $base, array $next): array
            {
                return [
                    'prefix' => $this->joinUri($base['prefix'], $next['prefix']),
                    'middleware' => array_values(array_merge($base['middleware'], $next['middleware'])),
                    'controller' => $next['controller'] ?? $base['controller'],
                ];
            }

            private function httpMethod(string $method): string
            {
                return match ($method) {
                    'resource', 'apiResource' => 'RESOURCE',
                    default => strtoupper($method),
                };
            }

            private function joinUri(string $prefix, string $uri): string
            {
                $joined = trim($prefix, '/') . '/' . trim($uri, '/');
                $joined = '/' . trim($joined, '/');

                return $joined === '/' ? '/' : $joined;
            }

            private function stringValue(?Node $node): ?string
            {
                return $node instanceof Node\Scalar\String_ ? $node->value : null;
            }

            private function classValue(?Node $node): ?string
            {
                if ($node instanceof Node\Expr\ClassConstFetch && $node->class instanceof Node\Name) {
                    return $node->class->toString();
                }

                return $this->stringValue($node);
            }

            /**
             * @return list<string>
             */
            private function middlewareValues(?Node $node): array
            {
                if ($node instanceof Node\Scalar\String_) {
                    return [$node->value];
                }

                if (!$node instanceof Node\Expr\Array_) {
                    return [];
                }

                $values = [];
                foreach ($node->items as $item) {
                    $value = $this->stringValue($item?->value);
                    if ($value !== null) {
                        $values[] = $value;
                    }
                }

                return $values;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->routes;
    }

    /**
     * @return list<string>
     */
    private function routeFiles(string $routesPath): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($routesPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function serviceProviderPrefix(string $routeFile, string $projectPath): string
    {
        $provider = rtrim($projectPath, '/') . '/app/Providers/RouteServiceProvider.php';
        $code = is_file($provider) ? file_get_contents($provider) : false;
        if (!is_string($code)) {
            return '';
        }

        $routeName = basename($routeFile);
        $pattern = "/Route::prefix\\(['\"]([^'\"]+)['\"]\\).*?routes\\/" . preg_quote($routeName, '/') . "/s";
        if (preg_match($pattern, $code, $match)) {
            return $match[1];
        }

        return '';
    }
}
