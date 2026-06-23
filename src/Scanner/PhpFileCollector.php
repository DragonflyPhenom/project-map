<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Scanner;

use IaroslavKhmel\ProjectMap\Support\PathNormalizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PhpFileCollector
{
    /**
     * @param list<string> $exclude
     * @return list<string>
     */
    public function collect(string $projectPath, array $exclude): array
    {
        $files = [];
        $root = rtrim(PathNormalizer::normalize(realpath($projectPath) ?: $projectPath), '/');
        $excluded = array_map(static fn (string $path): string => trim(PathNormalizer::normalize($path), '/'), $exclude);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $path = PathNormalizer::normalize($file->getPathname());
            $relative = PathNormalizer::relative($path, $root);

            if ($this->isExcluded($relative, $excluded)) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $excluded
     */
    private function isExcluded(string $relativePath, array $excluded): bool
    {
        foreach ($excluded as $exclude) {
            if ($exclude === '' || $exclude === '.') {
                continue;
            }

            if ($relativePath === $exclude || str_starts_with($relativePath, $exclude . '/')) {
                return true;
            }
        }

        return false;
    }
}
