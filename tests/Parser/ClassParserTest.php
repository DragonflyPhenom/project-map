<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Parser;

use IaroslavKhmel\ProjectMap\Parser\ClassParser;
use PHPUnit\Framework\TestCase;

final class ClassParserTest extends TestCase
{
    public function testItParsesClassesMethodsAndCalls(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-test-' . uniqid();
        mkdir($dir);
        $file = $dir . '/UserController.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Service\UserService;

final class UserController extends Controller
{
    public function __construct(private UserService $service)
    {
    }

    public function store(?string $name): JsonResponse
    {
        $this->normalize($name);
        $this->service->create();
        Unknown::ping();
        new UserDto();
    }

    private function normalize(?string $name): string
    {
        return trim((string) $name);
    }
}
PHP);

        $result = (new ClassParser())->parseFile($file, $dir);

        self::assertCount(1, $result['classes']);
        self::assertSame('App\Http\Controllers\UserController', $result['classes'][0]['name']);
        self::assertSame('App\Http\Controllers\Controller', $result['classes'][0]['extends']);
        self::assertCount(3, $result['methods']);
        self::assertContains('calls', array_column($result['calls'], 'kind'));
        self::assertContains('new', array_column($result['calls'], 'kind'));
        self::assertContains('App\Service\UserService::create', array_column($result['calls'], 'target_method'));
    }
}
