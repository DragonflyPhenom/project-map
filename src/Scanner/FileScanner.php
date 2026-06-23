<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Scanner;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;
use IaroslavKhmel\ProjectMap\Parser\ClassParser;

final class FileScanner
{
    public function __construct(private readonly ClassParser $classParser = new ClassParser())
    {
    }

    public function scan(string $file, string $projectPath, ProjectGraph $graph): void
    {
        $result = $this->classParser->parseFile($file, $projectPath);

        foreach ($result['classes'] as $class) {
            $graph->addClass($class);
        }

        foreach ($result['methods'] as $method) {
            $graph->addMethod($method);
        }

        foreach ($result['calls'] as $call) {
            $graph->addCall($call);
        }

        foreach ($result['warnings'] as $warning) {
            $graph->warn($warning);
        }
    }
}
