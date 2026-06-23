<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

final class HtmlWriter
{
    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload, string $outputPath, string $mermaid): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Project Map</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:32px;color:#111827}.diagram{width:100%;min-height:520px;overflow:auto;border:1px solid #d1d5db;padding:16px;background:#fff}table{border-collapse:collapse;width:100%;margin:12px 0 28px}td,th{border:1px solid #d1d5db;padding:8px;text-align:left}code{background:#f3f4f6;padding:2px 4px}</style>'
            . '<script type="module">import mermaid from "https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs";mermaid.initialize({startOnLoad:true,securityLevel:"loose"});</script>'
            . '</head><body><h1>Project Map</h1>'
            . '<p>Framework: <code>' . $this->esc((string) $payload['meta']['framework']) . '</code></p>'
            . '<h2>Graph</h2><div class="diagram"><pre class="mermaid">' . $this->esc($mermaid) . '</pre></div>'
            . '<p><a href="project-map.mmd">project-map.mmd</a> · <a href="project-map.json">project-map.json</a></p>'
            . $this->table('Classes', ['Name', 'Type', 'File'], array_map(fn ($class): array => [$class['name'], $class['type'], $class['file']], $payload['classes']))
            . $this->table('Routes', ['Method', 'URI', 'Name', 'Controller'], array_map(fn ($route): array => [$route['http_method'] ?? '', $route['uri'] ?? $route['path'] ?? '', $route['name'] ?? '', $route['controller_method'] ?? $route['controller'] ?? ''], $payload['routes']))
            . $this->table('Models', ['Class', 'Table', 'Relations'], array_map(fn ($model): array => [$model['class'], $model['table'] ?? '', count($model['relations'] ?? [])], $payload['models']))
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
