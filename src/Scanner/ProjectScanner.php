<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Scanner;

use IaroslavKhmel\ProjectMap\Config\ScanConfig;
use IaroslavKhmel\ProjectMap\Framework\FrameworkDetector;
use IaroslavKhmel\ProjectMap\Framework\FrameworkType;
use IaroslavKhmel\ProjectMap\Framework\Laravel\LaravelModelScanner;
use IaroslavKhmel\ProjectMap\Framework\Laravel\LaravelRouteScanner;
use IaroslavKhmel\ProjectMap\Framework\Symfony\SymfonyDoctrineScanner;
use IaroslavKhmel\ProjectMap\Framework\Symfony\SymfonyRouteScanner;
use IaroslavKhmel\ProjectMap\Graph\GraphBuilder;
use IaroslavKhmel\ProjectMap\Graph\ProjectGraph;

final class ProjectScanner
{
    public function __construct(
        private readonly PhpFileCollector $collector = new PhpFileCollector(),
        private readonly FileScanner $fileScanner = new FileScanner(),
        private readonly FrameworkDetector $detector = new FrameworkDetector(),
        private readonly GraphBuilder $graphBuilder = new GraphBuilder(),
    ) {
    }

    /**
     * @return array{graph: ProjectGraph, framework: FrameworkType}
     */
    public function scan(ScanConfig $config): array
    {
        $framework = $this->detector->detect($config->projectPath, $config->framework);
        $graph = $this->graphBuilder->build();
        $files = $this->collector->collect($config->projectPath, $config->exclude);

        foreach ($files as $file) {
            $this->fileScanner->scan($file, $config->projectPath, $graph);
        }

        if ($framework === FrameworkType::Laravel) {
            foreach ((new LaravelRouteScanner())->scan($config->projectPath) as $route) {
                $graph->addRoute($route);
            }

            foreach ((new LaravelModelScanner())->scan($files, $config->projectPath) as $model) {
                $graph->addModel($model);
            }
        }

        if ($framework === FrameworkType::Symfony) {
            foreach ((new SymfonyRouteScanner())->scan($files, $config->projectPath) as $route) {
                $graph->addRoute($route);
            }

            foreach ((new SymfonyDoctrineScanner())->scan($files) as $model) {
                $graph->addModel($model);
            }

            if (is_file($config->projectPath . '/config/routes.yaml')) {
                $graph->warn('Symfony YAML route scanning is not implemented in MVP: config/routes.yaml');
            }
        }

        return ['graph' => $graph, 'framework' => $framework];
    }
}
