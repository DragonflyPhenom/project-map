<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Framework\Laravel;

use IaroslavKhmel\ProjectMap\Parser\ModelParser;

final class LaravelModelScanner
{
    public function __construct(private readonly ModelParser $parser = new ModelParser())
    {
    }

    /**
     * @param list<string> $files
     * @return list<array<string, mixed>>
     */
    public function scan(array $files, string $projectPath): array
    {
        $models = [];
        foreach ($files as $file) {
            foreach ($this->parser->parseLaravelFile($file) as $model) {
                $models[$model['class']] = $model;
            }
        }

        $migrationFields = $this->migrationFields($projectPath);
        foreach ($models as &$model) {
            $table = $model['table'] ?? null;
            if ($table !== null && isset($migrationFields[$table])) {
                $model['fields'] = array_values(array_merge($model['fields'] ?? [], $migrationFields[$table]));
            }
        }

        return array_values($models);
    }

    /**
     * @return array<string, list<array{name: string, type: string|null}>>
     */
    private function migrationFields(string $projectPath): array
    {
        $dir = rtrim($projectPath, '/') . '/database/migrations';
        if (!is_dir($dir)) {
            return [];
        }

        $fields = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $code = file_get_contents($file);
            if (!is_string($code) || !preg_match("/Schema::create\\(['\"]([^'\"]+)['\"]/", $code, $tableMatch)) {
                continue;
            }

            $table = $tableMatch[1];
            preg_match_all("/\\\$table->(string|integer|bigInteger|foreignId|boolean|text|dateTime|timestamp|uuid)\\(['\"]([^'\"]+)['\"]/", $code, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $fields[$table][] = ['name' => $match[2], 'type' => $match[1]];
            }
            if (str_contains($code, '$table->timestamps()')) {
                $fields[$table][] = ['name' => 'created_at', 'type' => 'timestamp'];
                $fields[$table][] = ['name' => 'updated_at', 'type' => 'timestamp'];
            }
        }

        return $fields;
    }
}
