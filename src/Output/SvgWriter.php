<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Output;

final class SvgWriter
{
    public function write(string $dotFile, string $outputPath): ?string
    {
        if (!$this->hasDot()) {
            return null;
        }

        $file = rtrim($outputPath, '/') . '/project-map.svg';
        $process = proc_open(
            ['dot', '-Tsvg', $dotFile, '-o', $file],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            return null;
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return proc_close($process) === 0 && is_file($file) ? $file : null;
    }

    private function hasDot(): bool
    {
        $process = proc_open(
            ['dot', '-V'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return proc_close($process) === 0;
    }
}
