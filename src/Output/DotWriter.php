<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;

final class DotWriter
{
    public function write(ProjectGraph $graph, string $outputPath, string $scope = 'all', ?int $maxDepth = null): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $classes = $this->classesWithExternalTargets($graph, $scope, $maxDepth);
        $models = $this->modelsByClass($graph);
        $lines = [
            'digraph ProjectMap {',
            '  graph [rankdir=LR, bgcolor="white", concentrate=true, splines=ortho, nodesep=0.6, ranksep=1.0];',
            '  node [shape=plaintext, fontname="Arial"];',
            '  edge [fontname="Arial", fontsize=10, color="#475569", arrowsize=0.8];',
        ];

        foreach ($classes as $class) {
            $lines[] = '  "' . $this->id($class['name']) . '" [label=<' . $this->classTable($class, $models[$class['name']] ?? null) . '>];';
        }

        if ($scope === 'routes' || $scope === 'all') {
            foreach ($graph->routes as $index => $route) {
                $routeId = $this->routeId($route, $index);
                $label = trim((string) ($route['http_method'] ?? 'ANY') . ' ' . (string) ($route['uri'] ?? $route['path'] ?? ''));
                $lines[] = '  "' . $routeId . '" [shape=box, style="rounded,filled", fillcolor="#f5f3ff", color="#7c3aed", label="' . $this->dotEsc($label) . '"];';
                if (!empty($route['controller_method'])) {
                    [$class, $method] = $this->splitMethod((string) $route['controller_method']);
                    $lines[] = '  "' . $routeId . '" -> "' . $this->id($class) . '":' . $this->port($method) . ' [label="route", color="#7c3aed"];';
                }
            }
        }

        if ($scope === 'classes' || $scope === 'all') {
            foreach ($this->callEdges($graph, $maxDepth) as $call) {
                [$sourceClass, $sourceMethod] = $this->splitMethod((string) $call['source_method']);
                [$targetClass, $targetMethod] = $this->splitMethod((string) $call['target_method']);
                $label = ($call['kind'] ?? '') === 'new' ? 'new' : 'calls';
                $lines[] = '  "' . $this->id($sourceClass) . '":' . $this->port($sourceMethod)
                    . ' -> "' . $this->id($targetClass) . '":' . $this->port($targetMethod)
                    . ' [label="' . $label . '"];';
            }
        }

        if ($scope === 'models' || $scope === 'all') {
            foreach ($graph->models as $model) {
                foreach ($model['relations'] ?? [] as $relation) {
                    if (!empty($relation['target_model'])) {
                        $lines[] = '  "' . $this->id((string) $model['class']) . '" -> "' . $this->id((string) $relation['target_model']) . '" [label="relation", color="#db2777"];';
                    }
                }
            }
        }

        if ($scope === 'classes' || $scope === 'all') {
            foreach ($graph->classes as $class) {
                if (!empty($class['extends'])) {
                    $lines[] = '  "' . $this->id((string) $class['name']) . '" -> "' . $this->id((string) $class['extends']) . '" [label="extends", color="#2563eb"];';
                }
                foreach ($class['implements'] ?? [] as $interface) {
                    $lines[] = '  "' . $this->id((string) $class['name']) . '" -> "' . $this->id((string) $interface) . '" [label="implements", color="#0f766e"];';
                }
                foreach ($class['traits'] ?? [] as $trait) {
                    $lines[] = '  "' . $this->id((string) $class['name']) . '" -> "' . $this->id((string) $trait) . '" [label="uses_trait", color="#9333ea"];';
                }
            }
        }

        $lines[] = '}';

        $file = rtrim($outputPath, '/') . '/project-map.dot';
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);

        return $file;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function classesWithExternalTargets(ProjectGraph $graph, string $scope, ?int $maxDepth): array
    {
        $classes = match ($scope) {
            'routes' => [],
            'models' => array_intersect_key($graph->classes, array_flip(array_keys($graph->models))),
            default => $graph->classes,
        };

        if ($scope === 'classes' || $scope === 'all') {
            foreach ($this->callEdges($graph, $maxDepth) as $call) {
                foreach ([$call['source_method'] ?? null, $call['target_method'] ?? null] as $methodId) {
                    if (!is_string($methodId) || !str_contains($methodId, '::')) {
                        continue;
                    }
                    [$class, $method] = $this->splitMethod($methodId);
                    if (!isset($classes[$class])) {
                        $classes[$class] = $this->externalClass($class, $method);
                    } elseif (!$this->hasMethod($classes[$class], $method)) {
                        $classes[$class]['methods'][] = $this->externalMethod($class, $method);
                    }
                }
            }
        }

        if ($scope === 'routes' || $scope === 'all') {
            foreach ($graph->routes as $route) {
                if (empty($route['controller_method'])) {
                    continue;
                }
                [$class, $method] = $this->splitMethod((string) $route['controller_method']);
                if (!isset($classes[$class])) {
                    $classes[$class] = $this->externalClass($class, $method);
                } elseif (!$this->hasMethod($classes[$class], $method)) {
                    $classes[$class]['methods'][] = $this->externalMethod($class, $method);
                }
            }
        }

        foreach ($graph->classes as $class) {
            foreach (array_filter([$class['extends'] ?? null, ...($class['implements'] ?? []), ...($class['traits'] ?? [])]) as $related) {
                if (!isset($classes[$related])) {
                    $classes[$related] = $this->externalClass((string) $related);
                }
            }
        }

        foreach ($graph->models as $model) {
            if (($scope === 'models' || $scope === 'all') && isset($graph->classes[$model['class']])) {
                $classes[$model['class']] = $graph->classes[$model['class']];
            }
            foreach ($model['relations'] ?? [] as $relation) {
                if (!empty($relation['target_model']) && !isset($classes[$relation['target_model']])) {
                    $classes[$relation['target_model']] = $this->externalClass((string) $relation['target_model']);
                }
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function callEdges(ProjectGraph $graph, ?int $maxDepth): array
    {
        $edges = array_values(array_filter($graph->calls, static fn (array $call): bool => ($call['kind'] ?? '') !== 'unknown_call' && !empty($call['source_method']) && !empty($call['target_method'])));
        if ($maxDepth === null) {
            return $edges;
        }

        $depths = [];
        $queue = [];
        foreach ($graph->routes as $route) {
            if (!empty($route['controller_method'])) {
                $depths[$route['controller_method']] = 0;
                $queue[] = $route['controller_method'];
            }
        }

        if ($queue === []) {
            return array_slice($edges, 0, $maxDepth === 0 ? 0 : count($edges));
        }

        $bySource = [];
        foreach ($edges as $edge) {
            $bySource[$edge['source_method']][] = $edge;
        }

        $allowed = [];
        while ($queue !== []) {
            $source = array_shift($queue);
            $depth = $depths[$source] ?? 0;
            if ($depth >= $maxDepth) {
                continue;
            }
            foreach ($bySource[$source] ?? [] as $edge) {
                $allowed[$edge['source_method'] . '->' . $edge['target_method']] = $edge;
                $target = $edge['target_method'];
                if (!isset($depths[$target]) || $depths[$target] > $depth + 1) {
                    $depths[$target] = $depth + 1;
                    $queue[] = $target;
                }
            }
        }

        return array_values($allowed);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function modelsByClass(ProjectGraph $graph): array
    {
        $models = [];
        foreach ($graph->models as $model) {
            $models[(string) $model['class']] = $model;
        }

        return $models;
    }

    /**
     * @param array<string, mixed> $class
     * @param array<string, mixed>|null $model
     */
    private function classTable(array $class, ?array $model): string
    {
        $rows = [
            '<TR><TD BGCOLOR="#e0f2fe"><B>' . $this->esc((string) ($class['short_name'] ?? $class['name'])) . '</B></TD></TR>',
            '<TR><TD><FONT POINT-SIZE="10">' . $this->esc((string) ($class['namespace'] ?? '')) . '</FONT></TD></TR>',
        ];

        foreach ($class['methods'] ?? [] as $method) {
            $rows[] = '<TR><TD PORT="' . $this->port((string) $method['name']) . '" ALIGN="LEFT" BGCOLOR="' . $this->visibilityColor((string) ($method['visibility'] ?? 'public')) . '">' . $this->esc($this->methodSignature($method)) . '</TD></TR>';
        }

        if ($model !== null) {
            $rows[] = '<TR><TD ALIGN="LEFT" BGCOLOR="#fce7f3"><B>Fields</B></TD></TR>';
            foreach ($model['fields'] ?? [] as $field) {
                $rows[] = '<TR><TD ALIGN="LEFT"><FONT POINT-SIZE="10">- ' . $this->esc((string) ($field['name'] ?? 'unknown') . ': ' . (string) ($field['type'] ?? 'mixed')) . '</FONT></TD></TR>';
            }
            $rows[] = '<TR><TD ALIGN="LEFT" BGCOLOR="#fce7f3"><B>Relations</B></TD></TR>';
            foreach ($model['relations'] ?? [] as $relation) {
                $rows[] = '<TR><TD ALIGN="LEFT"><FONT POINT-SIZE="10">- ' . $this->esc((string) ($relation['method'] ?? 'relation') . ': ' . (string) ($relation['type'] ?? 'relation')) . '</FONT></TD></TR>';
            }
        }

        return '<TABLE BORDER="1" CELLBORDER="1" CELLSPACING="0" CELLPADDING="6">' . implode('', $rows) . '</TABLE>';
    }

    /**
     * @param array<string, mixed> $method
     */
    private function methodSignature(array $method): string
    {
        $visibility = match ($method['visibility'] ?? 'public') {
            'private' => '-',
            'protected' => '#',
            default => '+',
        };
        $parameters = array_map(static function (array $parameter): string {
            $type = $parameter['type'] ? $parameter['type'] . ' ' : '';

            return $type . '$' . $parameter['name'];
        }, $method['parameters'] ?? []);

        return $visibility . ' ' . (string) $method['name'] . '(' . implode(', ', $parameters) . ')' . (!empty($method['return_type']) ? ': ' . $method['return_type'] : '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMethod(string $method): array
    {
        [$class, $name] = explode('::', $method, 2) + ['', '__invoke'];

        return [$class, $name];
    }

    /**
     * @param array<string, mixed> $class
     */
    private function hasMethod(array $class, string $method): bool
    {
        foreach ($class['methods'] ?? [] as $existing) {
            if (($existing['name'] ?? null) === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function externalClass(string $class, ?string $method = null): array
    {
        $parts = explode('\\', $class);

        return [
            'name' => $class,
            'short_name' => end($parts) ?: $class,
            'namespace' => count($parts) > 1 ? implode('\\', array_slice($parts, 0, -1)) : '',
            'type' => 'external',
            'extends' => null,
            'implements' => [],
            'traits' => [],
            'methods' => $method ? [$this->externalMethod($class, $method)] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalMethod(string $class, string $method): array
    {
        return [
            'id' => $class . '::' . $method,
            'class' => $class,
            'name' => $method,
            'visibility' => 'public',
            'parameters' => [],
            'return_type' => null,
            'signature' => 'public ' . $method . '()',
        ];
    }

    private function routeId(array $route, int $index): string
    {
        $value = 'route_' . strtolower((string) ($route['http_method'] ?? 'any')) . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) ($route['uri'] ?? $route['path'] ?? $index), '/'));

        return trim($value, '_') ?: 'route_' . $index;
    }

    private function id(string $value): string
    {
        return str_replace('\\', '\\\\', $value);
    }

    private function port(string $value): string
    {
        $port = preg_replace('/[^A-Za-z0-9_]/', '_', $value) ?: 'method';

        return 'p_' . $port;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function dotEsc(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    private function visibilityColor(string $visibility): string
    {
        return match ($visibility) {
            'public' => '#dcfce7',
            'protected' => '#fef9c3',
            'private' => '#fee2e2',
            default => '#f8fafc',
        };
    }
}
