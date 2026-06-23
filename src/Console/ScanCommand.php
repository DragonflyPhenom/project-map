<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Console;

use IaroslavKhmel\ProjectMap\Config\ScanConfig;
use IaroslavKhmel\ProjectMap\Output\DotWriter;
use IaroslavKhmel\ProjectMap\Output\HtmlWriter;
use IaroslavKhmel\ProjectMap\Output\JsonWriter;
use IaroslavKhmel\ProjectMap\Output\MermaidRenderer;
use IaroslavKhmel\ProjectMap\Scanner\ProjectScanner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ScanCommand extends Command
{
    protected static $defaultName = 'scan';
    protected static $defaultDescription = 'Scan a PHP project and build a technical project map.';

    public function __construct(
        private readonly ProjectScanner $scanner = new ProjectScanner(),
        private readonly JsonWriter $jsonWriter = new JsonWriter(),
        private readonly MermaidRenderer $mermaidRenderer = new MermaidRenderer(),
        private readonly DotWriter $dotWriter = new DotWriter(),
        private readonly HtmlWriter $htmlWriter = new HtmlWriter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('scan')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Project path', '.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory', '.project-map')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Comma-separated formats: json,mmd,html,dot', 'json,mmd,html')
            ->addOption('framework', null, InputOption::VALUE_REQUIRED, 'auto, laravel, symfony or generic', 'auto')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Comma-separated excluded directories', 'vendor,node_modules,storage,bootstrap/cache,var/cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('path');
        $outputPath = (string) $input->getOption('output');
        if (!$this->isAbsolutePath($outputPath)) {
            $outputPath = rtrim($path, '/') . '/' . $outputPath;
        }

        $config = ScanConfig::fromOptions(
            $path,
            $outputPath,
            (string) $input->getOption('format'),
            (string) $input->getOption('framework'),
            (string) $input->getOption('exclude'),
        );

        $result = $this->scanner->scan($config);
        $payload = $result['graph']->toArray($config->projectPath, $result['framework']->value);
        $mermaid = $this->mermaidRenderer->render($result['graph']);
        $written = [];

        if (in_array('json', $config->formats, true)) {
            $written[] = $this->jsonWriter->write($payload, $config->outputPath);
        }

        if (in_array('mmd', $config->formats, true) || in_array('mermaid', $config->formats, true)) {
            $written[] = $this->mermaidRenderer->write($result['graph'], $config->outputPath);
        }

        if (in_array('dot', $config->formats, true)) {
            $written[] = $this->dotWriter->write($result['graph'], $config->outputPath);
        }

        if (in_array('html', $config->formats, true)) {
            $written[] = $this->htmlWriter->write($payload, $config->outputPath, $mermaid);
        }

        $output->writeln('<info>Project map generated.</info>');
        foreach ($written as $file) {
            $output->writeln(' - ' . $file);
        }

        if ($payload['warnings'] !== []) {
            $output->writeln('<comment>Warnings: ' . count($payload['warnings']) . '</comment>');
        }

        return Command::SUCCESS;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
