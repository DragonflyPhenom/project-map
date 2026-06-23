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
            if (!is_string($code)) {
                continue;
            }

            preg_match_all("/Schema::(?:create|table)\\(['\"]([^'\"]+)['\"].*?function\\s*\\([^)]*\\\$table[^)]*\\)\\s*\\{(.*?)\\}\\s*\\)/s", $code, $tables, PREG_SET_ORDER);
            foreach ($tables as $tableMatch) {
                $table = $tableMatch[1];
                foreach ($this->fieldsFromBlueprint($tableMatch[2]) as $field) {
                    $fields[$table][$field['name']] = $field;
                }
            }
        }

        return array_map('array_values', $fields);
    }

    /**
     * @return list<array{name: string, type: string|null}>
     */
    private function fieldsFromBlueprint(string $body): array
    {
        $fields = [];
        if (preg_match_all("/\\\$table->(uuid|string|text|integer|bigInteger|boolean|decimal|float|date|dateTime|timestamp|foreignId|foreignIdFor|enum|json)\\(([^)]*)\\)/", $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $this->fieldName($match[1], $match[2]);
                if ($name !== null) {
                    $fields[] = ['name' => $name, 'type' => $match[1]];
                }
            }
        }

        if (preg_match('/\\$table->id\\(/', $body) || str_contains($body, '$table->id()')) {
            array_unshift($fields, ['name' => 'id', 'type' => 'bigint']);
        }

        if (str_contains($body, '$table->timestamps()')) {
            $fields[] = ['name' => 'created_at', 'type' => 'timestamp'];
            $fields[] = ['name' => 'updated_at', 'type' => 'timestamp'];
        }

        if (str_contains($body, '$table->softDeletes()')) {
            $fields[] = ['name' => 'deleted_at', 'type' => 'timestamp'];
        }

        return $fields;
    }

    private function fieldName(string $type, string $arguments): ?string
    {
        if ($type === 'foreignIdFor' && preg_match('/([A-Za-z_][A-Za-z0-9_\\\\]*)::class/', $arguments, $match)) {
            $class = basename(str_replace('\\', '/', $match[1]));
            $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

            return $snake . '_id';
        }

        if (preg_match('/[\'"]([^\'"]+)[\'"]/', $arguments, $match)) {
            return $match[1];
        }

        return null;
    }
}
