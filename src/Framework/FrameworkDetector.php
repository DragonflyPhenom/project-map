<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework;

final class FrameworkDetector
{
    public function detect(string $projectPath, FrameworkType $requested): FrameworkType
    {
        if ($requested !== FrameworkType::Auto) {
            return $requested;
        }

        if ($this->isLaravel($projectPath)) {
            return FrameworkType::Laravel;
        }

        if ($this->isSymfony($projectPath)) {
            return FrameworkType::Symfony;
        }

        return FrameworkType::Generic;
    }

    private function isLaravel(string $projectPath): bool
    {
        return is_file($projectPath . '/artisan')
            || is_dir($projectPath . '/app/Http/Controllers')
            || is_file($projectPath . '/routes/web.php')
            || is_file($projectPath . '/routes/api.php')
            || $this->composerHas($projectPath, 'laravel/framework');
    }

    private function isSymfony(string $projectPath): bool
    {
        return is_file($projectPath . '/bin/console')
            || is_file($projectPath . '/config/routes.yaml')
            || is_dir($projectPath . '/src/Controller')
            || $this->composerHas($projectPath, 'symfony/framework-bundle');
    }

    private function composerHas(string $projectPath, string $package): bool
    {
        $composer = $projectPath . '/composer.json';
        if (!is_file($composer)) {
            return false;
        }

        $contents = file_get_contents($composer);

        return is_string($contents) && str_contains($contents, '"' . $package . '"');
    }
}
