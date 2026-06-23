<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Output;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;
use IaroslavKhmel\ProjectMap\Output\DotWriter;
use PHPUnit\Framework\TestCase;

final class DotWriterTest extends TestCase
{
    public function testItRendersMethodPortsInsideClassTables(): void
    {
        $graph = new ProjectGraph();
        $graph->addClass($this->class('App\Http\Controllers\UserController', [
            $this->method('App\Http\Controllers\UserController', 'store', 'public'),
        ]));
        $graph->addClass($this->class('App\Services\UserService', [
            $this->method('App\Services\UserService', 'create', 'public'),
        ]));
        $graph->addMethod($this->method('App\Http\Controllers\UserController', 'store', 'public'));
        $graph->addMethod($this->method('App\Services\UserService', 'create', 'public'));
        $graph->addCall([
            'source_method' => 'App\Http\Controllers\UserController::store',
            'kind' => 'calls',
            'target_class' => 'App\Services\UserService',
            'target_method' => 'App\Services\UserService::create',
            'method' => 'create',
        ]);

        $dir = sys_get_temp_dir() . '/project-map-dot-' . uniqid();
        $file = (new DotWriter())->write($graph, $dir);
        $dot = file_get_contents($file);

        self::assertIsString($dot);
        self::assertStringContainsString('PORT="p_store"', $dot);
        self::assertStringContainsString('PORT="p_create"', $dot);
        self::assertStringContainsString('"App\\\\Http\\\\Controllers\\\\UserController":p_store -> "App\\\\Services\\\\UserService":p_create [label="calls"]', $dot);
        self::assertStringNotContainsString('"App\\\\Http\\\\Controllers\\\\UserController::store"', $dot);
    }

    /**
     * @param list<array<string, mixed>> $methods
     * @return array<string, mixed>
     */
    private function class(string $class, array $methods): array
    {
        $parts = explode('\\', $class);

        return [
            'name' => $class,
            'short_name' => end($parts),
            'namespace' => implode('\\', array_slice($parts, 0, -1)),
            'type' => 'class',
            'extends' => null,
            'implements' => [],
            'traits' => [],
            'methods' => $methods,
            'file' => 'app/Foo.php',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function method(string $class, string $method, string $visibility): array
    {
        return [
            'id' => $class . '::' . $method,
            'class' => $class,
            'name' => $method,
            'visibility' => $visibility,
            'parameters' => [['name' => 'request', 'type' => 'Request']],
            'return_type' => 'JsonResponse',
            'signature' => $visibility . ' ' . $method . '(Request $request): JsonResponse',
        ];
    }
}
