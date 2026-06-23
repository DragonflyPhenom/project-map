<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;

final class MermaidRenderer
{
    public function write(ProjectGraph $graph, string $outputPath): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $file = rtrim($outputPath, '/') . '/project-map.mmd';
        file_put_contents($file, $this->render($graph));

        return $file;
    }

    public function render(ProjectGraph $graph): string
    {
        $lines = [
            'flowchart LR',
            '  classDef classNode fill:#e0f2fe,stroke:#0284c7,color:#0f172a;',
            '  classDef routeNode fill:#f5f3ff,stroke:#7c3aed,color:#0f172a;',
            '  classDef modelNode fill:#fce7f3,stroke:#db2777,color:#0f172a;',
            '  classDef publicMethod fill:#dcfce7,stroke:#16a34a,color:#052e16;',
            '  classDef protectedMethod fill:#fef9c3,stroke:#ca8a04,color:#422006;',
            '  classDef privateMethod fill:#fee2e2,stroke:#dc2626,color:#450a0a;',
            '  classDef externalNode fill:#f8fafc,stroke:#94a3b8,color:#475569,stroke-dasharray:4 3;',
        ];

        foreach ($graph->nodes() as $node) {
            $shape = $this->shape($node);
            $lines[] = '  ' . $this->nodeId($node['id']) . $shape[0] . $this->esc((string) $node['label']) . $shape[1];
        }

        foreach ($graph->edges() as $edge) {
            $lines[] = '  ' . $this->nodeId($edge['from']) . ' -->|' . $this->esc((string) $edge['label']) . '| ' . $this->nodeId($edge['to']);
        }

        foreach ($graph->nodes() as $node) {
            $classes = $this->classes($node);
            if ($classes !== []) {
                $lines[] = '  class ' . $this->nodeId($node['id']) . ' ' . implode(',', $classes) . ';';
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $node
     * @return array{0: string, 1: string}
     */
    private function shape(array $node): array
    {
        return match ($node['type'] ?? '') {
            'route' => ['(["', '"])'],
            'model' => ['[("', '")]'],
            default => ['["', '"]'],
        };
    }

    /**
     * @param array<string, mixed> $node
     * @return list<string>
     */
    private function classes(array $node): array
    {
        $classes = [];
        if (($node['external'] ?? false) === true) {
            $classes[] = 'externalNode';
        }

        if (($node['type'] ?? '') === 'method') {
            $classes[] = match ($node['visibility'] ?? 'public') {
                'protected' => 'protectedMethod',
                'private' => 'privateMethod',
                default => 'publicMethod',
            };
        } elseif (($node['type'] ?? '') === 'route') {
            $classes[] = 'routeNode';
        } elseif (($node['type'] ?? '') === 'model') {
            $classes[] = 'modelNode';
        } else {
            $classes[] = 'classNode';
        }

        return $classes;
    }

    private function nodeId(string $id): string
    {
        return 'n' . substr(sha1($id), 0, 16);
    }

    private function esc(string $value): string
    {
        return str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $value);
    }
}
