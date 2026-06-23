<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework\Symfony;

use IaroslavKhmel\ProjectMap\Parser\ModelParser;

final class SymfonyDoctrineScanner
{
    public function __construct(private readonly ModelParser $parser = new ModelParser())
    {
    }

    /**
     * @param list<string> $files
     * @return list<array<string, mixed>>
     */
    public function scan(array $files): array
    {
        $models = [];
        foreach ($files as $file) {
            foreach ($this->parser->parseDoctrineFile($file) as $model) {
                $models[$model['class']] = $model;
            }
        }

        return array_values($models);
    }
}
