<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph;

final class ProjectGraph
{
    /** @var list<array<string, mixed>> */
    public array $classes = [];
    /** @var list<array<string, mixed>> */
    public array $methods = [];
    /** @var list<array<string, mixed>> */
    public array $calls = [];
    /** @var list<array<string, mixed>> */
    public array $routes = [];
    /** @var list<array<string, mixed>> */
    public array $models = [];
    /** @var list<string> */
    public array $warnings = [];
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];
    /** @var array<string, array<string, mixed>> */
    private array $edges = [];

    public function addClass(array $class): void
    {
        $this->classes[$class['name']] = $class;
        $classId = $this->classNodeId($class['name']);
        $this->addNode($classId, 'class', $class['short_name'] ?? $class['name'], [
            'class' => $class['name'],
            'type' => $class['type'] ?? 'class',
            'file' => $class['file'] ?? null,
            'external' => false,
        ]);

        if (!empty($class['extends'])) {
            $parentId = $this->classNodeId($class['extends']);
            $this->addNode($parentId, 'class', $this->shortName($class['extends']), ['class' => $class['extends'], 'external' => true]);
            $this->addEdge($classId, $parentId, 'extends', 'extends');
        }

        foreach ($class['implements'] ?? [] as $interface) {
            $interfaceId = $this->classNodeId($interface);
            $this->addNode($interfaceId, 'class', $this->shortName($interface), ['class' => $interface, 'external' => true]);
            $this->addEdge($classId, $interfaceId, 'implements', 'implements');
        }

        foreach ($class['traits'] ?? [] as $trait) {
            $traitId = $this->classNodeId($trait);
            $this->addNode($traitId, 'class', $this->shortName($trait), ['class' => $trait, 'external' => true]);
            $this->addEdge($classId, $traitId, 'uses_trait', 'uses_trait');
        }
    }

    public function addMethod(array $method): void
    {
        $this->methods[$method['id']] = $method;
        $methodId = $this->methodNodeId($method['id']);
        $this->addNode($methodId, 'method', $this->methodLabel($method), [
            'class' => $method['class'] ?? null,
            'method' => $method['name'] ?? null,
            'visibility' => $method['visibility'] ?? 'public',
            'signature' => $method['signature'] ?? null,
            'external' => false,
        ]);

        if (!empty($method['class'])) {
            $classId = $this->classNodeId($method['class']);
            $this->addNode($classId, 'class', $this->shortName($method['class']), ['class' => $method['class'], 'external' => true]);
            $this->addEdge($classId, $methodId, 'contains', 'method');
        }
    }

    public function addCall(array $call): void
    {
        $this->calls[] = $call;
        if (($call['kind'] ?? '') === 'unknown_call') {
            $this->warn('Unknown call in ' . ($call['source_method'] ?? 'unknown method'));

            return;
        }

        $source = $call['source_method'] ?? null;
        $target = $call['target_method'] ?? null;
        if (!is_string($source) || !is_string($target)) {
            return;
        }

        $sourceId = $this->methodNodeId($source);
        $targetId = $this->methodNodeId($target);
        $this->addNode($sourceId, 'method', $source, ['external' => true]);
        $this->addNode($targetId, 'method', $target, ['external' => true]);
        $this->addEdge($sourceId, $targetId, 'calls', 'calls');
    }

    public function addRoute(array $route): void
    {
        $this->routes[] = $route;
        $index = array_key_last($this->routes);
        $routeId = 'route:' . $index;
        $method = (string) ($route['http_method'] ?? 'ANY');
        $uri = (string) ($route['uri'] ?? $route['path'] ?? '');
        $this->addNode($routeId, 'route', trim($method . ' ' . $uri), [
            'http_method' => $method,
            'uri' => $uri,
            'name' => $route['name'] ?? null,
            'source_file' => $route['source_file'] ?? null,
        ]);

        if (!empty($route['controller_method'])) {
            $targetId = $this->methodNodeId($route['controller_method']);
            $this->addNode($targetId, 'method', $route['controller_method'], ['external' => true]);
            $this->addEdge($routeId, $targetId, 'route_to_method', 'route');
        }
    }

    public function addModel(array $model): void
    {
        $this->models[$model['class']] = $model;
        $modelId = $this->modelNodeId($model['class']);
        $this->addNode($modelId, 'model', $this->shortName($model['class']), [
            'class' => $model['class'],
            'table' => $model['table'] ?? null,
            'kind' => $model['kind'] ?? null,
            'external' => false,
        ]);

        foreach ($model['relations'] ?? [] as $relation) {
            if (empty($relation['target_model'])) {
                continue;
            }

            $targetId = $this->modelNodeId($relation['target_model']);
            $this->addNode($targetId, 'model', $this->shortName($relation['target_model']), ['class' => $relation['target_model'], 'external' => true]);
            $this->addEdge($modelId, $targetId, 'relation', 'relation');
        }
    }

    public function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $projectPath, string $framework): array
    {
        return [
            'meta' => [
                'generated_at' => gmdate(DATE_ATOM),
                'project_path' => $projectPath,
                'framework' => $framework,
                'php_version' => PHP_VERSION,
            ],
            'nodes' => array_values($this->nodes),
            'edges' => array_values($this->edges),
            'classes' => array_values($this->classes),
            'methods' => array_values($this->methods),
            'calls' => $this->calls,
            'routes' => $this->routes,
            'models' => array_values($this->models),
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function edges(): array
    {
        return array_values($this->edges);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function addNode(string $id, string $type, string $label, array $attributes = []): void
    {
        $existing = $this->nodes[$id] ?? [];
        if (($existing['external'] ?? null) === false && ($attributes['external'] ?? null) === true) {
            $attributes['external'] = false;
            $label = (string) ($existing['label'] ?? $label);
        }

        $this->nodes[$id] = array_filter(array_merge($existing, $attributes, [
            'id' => $id,
            'type' => $type,
            'label' => $label,
        ]), static fn (mixed $value): bool => $value !== null);
    }

    private function addEdge(string $from, string $to, string $type, string $label): void
    {
        $id = $from . '->' . $to . ':' . $type;
        $this->edges[$id] = [
            'id' => $id,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'label' => $label,
        ];
    }

    private function classNodeId(string $class): string
    {
        return 'class:' . $class;
    }

    private function methodNodeId(string $method): string
    {
        return 'method:' . $method;
    }

    private function modelNodeId(string $model): string
    {
        return 'model:' . $model;
    }

    private function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }

    /**
     * @param array<string, mixed> $method
     */
    private function methodLabel(array $method): string
    {
        $parameters = array_map(static function (array $parameter): string {
            $name = (string) ($parameter['name'] ?? 'unknown');
            $type = $parameter['type'] ?? null;

            return $name . ($type ? ': ' . $type : '');
        }, $method['parameters'] ?? []);

        return (string) ($method['class'] ?? '')
            . '::'
            . (string) ($method['name'] ?? '')
            . '('
            . implode(', ', $parameters)
            . ')'
            . (!empty($method['return_type']) ? ': ' . $method['return_type'] : '');
    }
}
