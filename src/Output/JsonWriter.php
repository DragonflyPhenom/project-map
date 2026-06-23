<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

final class JsonWriter
{
    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload, string $outputPath): string
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $file = rtrim($outputPath, '/') . '/project-map.json';
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $file;
    }
}
