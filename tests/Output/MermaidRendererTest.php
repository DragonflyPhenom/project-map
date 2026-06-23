<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Output;

use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;
use IaroslavKhmel\ProjectMap\Output\HtmlWriter;
use IaroslavKhmel\ProjectMap\Output\MermaidRenderer;
use PHPUnit\Framework\TestCase;

final class MermaidRendererTest extends TestCase
{
    public function testItRendersProjectGraphAsMermaid(): void
    {
        $graph = new ProjectGraph();
        $graph->addClass([
            'name' => 'App\Controller\UserController',
            'short_name' => 'UserController',
            'type' => 'class',
            'extends' => 'App\Controller\Controller',
            'implements' => [],
            'traits' => [],
            'methods' => [],
            'file' => 'app/Controller/UserController.php',
        ]);
        $graph->addMethod([
            'id' => 'App\Controller\UserController::index',
            'class' => 'App\Controller\UserController',
            'name' => 'index',
            'visibility' => 'public',
            'parameters' => [['name' => 'id', 'type' => 'int']],
            'return_type' => 'Response',
            'signature' => 'public index(int $id): Response',
        ]);
        $graph->addRoute([
            'http_method' => 'GET',
            'uri' => '/users',
            'name' => null,
            'middleware' => [],
            'controller' => 'App\Controller\UserController',
            'controller_method' => 'App\Controller\UserController::index',
            'source_file' => 'routes/web.php',
        ]);

        $mermaid = (new MermaidRenderer())->render($graph);

        self::assertStringStartsWith('flowchart LR', $mermaid);
        self::assertStringContainsString('App\Controller\UserController::index(id: int): Response', $mermaid);
        self::assertStringContainsString('|route|', $mermaid);
        self::assertStringContainsString('|extends|', $mermaid);
        self::assertStringContainsString('publicMethod', $mermaid);
    }

    public function testHtmlWriterEmbedsMermaidDiagram(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-html-' . uniqid();
        mkdir($dir);
        $payload = [
            'meta' => ['framework' => 'generic'],
            'classes' => [],
            'routes' => [],
            'models' => [],
        ];

        $file = (new HtmlWriter())->write($payload, $dir, "flowchart LR\n  A --> B\n");
        $html = file_get_contents($file);

        self::assertIsString($html);
        self::assertStringContainsString('cdn.jsdelivr.net/npm/mermaid', $html);
        self::assertStringContainsString('<pre class="mermaid">', $html);
        self::assertStringContainsString('flowchart LR', $html);
    }
}
