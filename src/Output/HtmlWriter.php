<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

final class HtmlWriter
{
    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload, string $outputPath): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Project Map</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:32px;color:#111827}table{border-collapse:collapse;width:100%;margin:12px 0 28px}td,th{border:1px solid #d1d5db;padding:8px;text-align:left}code{background:#f3f4f6;padding:2px 4px}</style>'
            . '</head><body><h1>Project Map</h1>'
            . '<p>Framework: <code>' . $this->esc((string) $payload['meta']['framework']) . '</code></p>'
            . $this->table('Classes', ['Name', 'Type', 'File'], array_map(fn ($class): array => [$class['name'], $class['type'], $class['file']], $payload['classes']))
            . $this->table('Routes', ['Method', 'URI', 'Name', 'Controller'], array_map(fn ($route): array => [$route['http_method'] ?? '', $route['uri'] ?? $route['path'] ?? '', $route['name'] ?? '', $route['controller_method'] ?? $route['controller'] ?? ''], $payload['routes']))
            . $this->table('Models', ['Class', 'Table', 'Relations'], array_map(fn ($model): array => [$model['class'], $model['table'] ?? '', count($model['relations'] ?? [])], $payload['models']))
            . '<h2>DOT</h2><p><a href="project-map.dot">project-map.dot</a></p>'
            . '</body></html>';

        $file = rtrim($outputPath, '/') . '/index.html';
        file_put_contents($file, $html);

        return $file;
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function table(string $title, array $headers, array $rows): string
    {
        $head = implode('', array_map(fn (string $header): string => '<th>' . $this->esc($header) . '</th>', $headers));
        $body = '';

        foreach ($rows as $row) {
            $body .= '<tr>' . implode('', array_map(fn (mixed $cell): string => '<td>' . $this->esc((string) $cell) . '</td>', $row)) . '</tr>';
        }

        return '<h2>' . $this->esc($title) . '</h2><table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
