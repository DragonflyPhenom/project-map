<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Framework;

use IaroslavKhmel\ProjectMap\Framework\Laravel\LaravelModelScanner;
use PHPUnit\Framework\TestCase;

final class LaravelModelScannerTest extends TestCase
{
    public function testItAddsFieldsFromCreateAndTableMigrations(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-laravel-model-' . uniqid();
        mkdir($dir . '/app/Models', 0777, true);
        mkdir($dir . '/database/migrations', 0777, true);
        $model = $dir . '/app/Models/User.php';
        file_put_contents($model, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $table = 'users';
}
PHP);
        file_put_contents($dir . '/database/migrations/2024_01_01_000000_create_users.php', <<<'PHP'
<?php

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('team_id');
    $table->timestamps();
});
PHP);
        file_put_contents($dir . '/database/migrations/2024_01_02_000000_update_users.php', <<<'PHP'
<?php

Schema::table('users', function (Blueprint $table) {
    $table->json('settings');
    $table->softDeletes();
});
PHP);

        $models = (new LaravelModelScanner())->scan([$model], $dir);

        self::assertCount(1, $models);
        self::assertSame(
            ['id', 'name', 'team_id', 'created_at', 'updated_at', 'settings', 'deleted_at'],
            array_column($models[0]['fields'], 'name'),
        );
    }
}
