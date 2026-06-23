<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Framework;

use IaroslavKhmel\ProjectMap\Framework\Laravel\LaravelRouteScanner;
use PHPUnit\Framework\TestCase;

final class LaravelRouteScannerTest extends TestCase
{
    public function testItParsesStaticRoutes(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-laravel-' . uniqid();
        mkdir($dir . '/routes', 0777, true);
        file_put_contents($dir . '/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', 'LegacyController@store');
PHP);

        $routes = (new LaravelRouteScanner())->scan($dir);

        self::assertCount(2, $routes);
        self::assertSame('GET', $routes[0]['http_method']);
        self::assertSame('/users', $routes[0]['uri']);
        self::assertSame('App\Http\Controllers\UserController::index', $routes[0]['controller_method']);
        self::assertSame('LegacyController::store', $routes[1]['controller_method']);
    }
}
