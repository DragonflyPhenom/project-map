<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Config;

use IaroslavKhmel\ProjectMap\Framework\FrameworkType;

final readonly class ScanConfig
{
    /**
     * @param list<string> $formats
     * @param list<string> $exclude
     */
    public function __construct(
        public string $projectPath,
        public string $outputPath,
        public array $formats = ['json', 'mmd', 'html'],
        public FrameworkType $framework = FrameworkType::Auto,
        public array $exclude = ['vendor', 'node_modules', 'storage', 'bootstrap/cache', 'var/cache'],
    ) {
    }

    /**
     * @param list<string>|string $formats
     * @param list<string>|string $exclude
     */
    public static function fromOptions(
        string $projectPath,
        string $outputPath,
        array|string $formats,
        string $framework,
        array|string $exclude,
    ): self {
        $formatList = is_array($formats) ? $formats : explode(',', $formats);
        $excludeList = is_array($exclude) ? $exclude : explode(',', $exclude);

        return new self(
            realpath($projectPath) ?: $projectPath,
            $outputPath,
            array_values(array_filter(array_map('trim', $formatList))),
            FrameworkType::fromInput($framework),
            array_values(array_unique(array_filter(array_map('trim', array_merge(['vendor'], $excludeList))))),
        );
    }
}
