<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph;

final class ProjectGraph
{
    /** @var list<array<string, mixed>> */
    public array $classes = [];
    /** @var list<array<string, mixed>> */
    public array $methods = [];
    /** @var list<array<string, mixed>> */
    public array $calls = [];
    /** @var list<array<string, mixed>> */
    public array $routes = [];
    /** @var list<array<string, mixed>> */
    public array $models = [];
    /** @var list<string> */
    public array $warnings = [];

    public function addClass(array $class): void
    {
        $this->classes[$class['name']] = $class;
    }

    public function addMethod(array $method): void
    {
        $this->methods[$method['id']] = $method;
    }

    public function addCall(array $call): void
    {
        $this->calls[] = $call;
    }

    public function addRoute(array $route): void
    {
        $this->routes[] = $route;
    }

    public function addModel(array $model): void
    {
        $this->models[$model['class']] = $model;
    }

    public function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $projectPath, string $framework): array
    {
        return [
            'meta' => [
                'generated_at' => gmdate(DATE_ATOM),
                'project_path' => $projectPath,
                'framework' => $framework,
                'php_version' => PHP_VERSION,
            ],
            'classes' => array_values($this->classes),
            'methods' => array_values($this->methods),
            'calls' => $this->calls,
            'routes' => $this->routes,
            'models' => array_values($this->models),
            'warnings' => $this->warnings,
        ];
    }
}
