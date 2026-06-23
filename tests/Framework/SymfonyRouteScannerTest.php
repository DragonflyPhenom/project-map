<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Framework;

use IaroslavKhmel\ProjectMap\Framework\Symfony\SymfonyRouteScanner;
use PHPUnit\Framework\TestCase;

final class SymfonyRouteScannerTest extends TestCase
{
    public function testItParsesRouteAttributes(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-symfony-' . uniqid();
        mkdir($dir);
        $file = $dir . '/UserController.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class UserController
{
    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function index(): Response
    {
    }
}
PHP);

        $routes = (new SymfonyRouteScanner())->scan([$file], $dir);

        self::assertCount(1, $routes);
        self::assertSame('/api/users', $routes[0]['path']);
        self::assertSame('users_index', $routes[0]['name']);
        self::assertSame('GET', $routes[0]['http_method']);
        self::assertSame('App\Controller\UserController::index', $routes[0]['controller_method']);
    }
}
