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

    public function testItParsesNestedGroupsControllerGroupsAndResources(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-laravel-' . uniqid();
        mkdir($dir . '/routes/api', 0777, true);
        file_put_contents($dir . '/routes/api/v1.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PostController;

Route::prefix('api')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('v1')->controller(UserController::class)->group(function () {
        Route::get('/users', 'index');
    });

    Route::apiResource('/posts', PostController::class);
});
PHP);

        $routes = (new LaravelRouteScanner())->scan($dir);

        self::assertCount(2, $routes);
        self::assertSame('/api/v1/users', $routes[0]['uri']);
        self::assertSame(['auth:sanctum'], $routes[0]['middleware']);
        self::assertSame('App\Http\Controllers\Api\UserController::index', $routes[0]['controller_method']);
        self::assertSame('RESOURCE', $routes[1]['http_method']);
        self::assertSame('/api/posts', $routes[1]['uri']);
        self::assertSame('App\Http\Controllers\Api\PostController::apiResource', $routes[1]['controller_method']);
    }

    public function testItUsesRouteServiceProviderPrefixForRouteFiles(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-laravel-' . uniqid();
        mkdir($dir . '/routes', 0777, true);
        mkdir($dir . '/app/Providers', 0777, true);
        file_put_contents($dir . '/app/Providers/RouteServiceProvider.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(base_path('routes/api.php'));
PHP);
        file_put_contents($dir . '/routes/api.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

Route::get('/users', [UserController::class, 'index']);
PHP);

        $routes = (new LaravelRouteScanner())->scan($dir);

        self::assertCount(1, $routes);
        self::assertSame('/api/v1/users', $routes[0]['uri']);
    }
}
