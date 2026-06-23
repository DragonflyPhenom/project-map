<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;

final class DotWriter
{
    public function write(ProjectGraph $graph, string $outputPath): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $lines = [
            'digraph ProjectMap {',
            '  graph [rankdir=LR, bgcolor="white"];',
            '  node [shape=plaintext, fontname="Arial"];',
            '  edge [fontname="Arial"];',
        ];

        foreach ($graph->classes as $class) {
            $id = $this->id($class['name']);
            $rows = [
                '<TR><TD BGCOLOR="#eef2ff"><B>' . $this->esc($class['short_name']) . '</B></TD></TR>',
                '<TR><TD><FONT POINT-SIZE="10">' . $this->esc($class['namespace'] ?? '') . '</FONT></TD></TR>',
                '<TR><TD><FONT POINT-SIZE="10">' . $this->esc($class['type']) . '</FONT></TD></TR>',
            ];

            foreach ($class['methods'] as $method) {
                $rows[] = '<TR><TD BGCOLOR="' . $this->visibilityColor($method['visibility']) . '">' . $this->esc($method['signature']) . '</TD></TR>';
            }

            $lines[] = '  "' . $id . '" [label=<<TABLE BORDER="1" CELLBORDER="1" CELLSPACING="0">' . implode('', $rows) . '</TABLE>>];';

            foreach (($class['extends'] ? [$class['extends']] : []) as $parent) {
                $lines[] = '  "' . $id . '" -> "' . $this->id($parent) . '" [label="extends"];';
            }

            foreach ($class['implements'] as $interface) {
                $lines[] = '  "' . $id . '" -> "' . $this->id($interface) . '" [label="implements"];';
            }

            foreach ($class['traits'] as $trait) {
                $lines[] = '  "' . $id . '" -> "' . $this->id($trait) . '" [label="uses trait"];';
            }
        }

        foreach ($graph->calls as $call) {
            if (($call['kind'] ?? '') === 'unknown_call') {
                continue;
            }

            $target = $call['target_method'] ?? $call['target_class'] ?? null;
            if ($target !== null) {
                $lines[] = '  "' . $this->id($call['source_method']) . '" -> "' . $this->id($target) . '" [label="' . $this->esc($call['kind'] ?? 'calls') . '"];';
            }
        }

        foreach ($graph->routes as $index => $route) {
            $routeId = 'route_' . $index;
            $label = implode(' ', array_filter([$route['http_method'] ?? 'ANY', $route['uri'] ?? $route['path'] ?? '']));
            $lines[] = '  "' . $routeId . '" [shape=box, label="' . $this->esc($label) . '"];';
            if (!empty($route['controller_method'])) {
                $lines[] = '  "' . $routeId . '" -> "' . $this->id($route['controller_method']) . '" [label="route"];';
            }
        }

        foreach ($graph->models as $model) {
            foreach ($model['relations'] ?? [] as $relation) {
                if (!empty($relation['target_model'])) {
                    $lines[] = '  "' . $this->id($model['class']) . '" -> "' . $this->id($relation['target_model']) . '" [label="relation: ' . $this->esc($relation['type']) . '"];';
                }
            }
        }

        $lines[] = '}';

        $file = rtrim($outputPath, '/') . '/project-map.dot';
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);

        return $file;
    }

    private function id(string $value): string
    {
        return str_replace('\\', '\\\\', $value);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
