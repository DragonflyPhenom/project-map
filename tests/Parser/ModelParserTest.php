<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Tests\Parser;

use IaroslavKhmel\ProjectMap\Parser\ModelParser;
use PHPUnit\Framework\TestCase;

final class ModelParserTest extends TestCase
{
    public function testItParsesLaravelModels(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-model-' . uniqid();
        mkdir($dir);
        $file = $dir . '/Post.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 */
final class Post extends Model
{
    protected $fillable = ['title'];
    protected $casts = ['published_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
PHP);

        $models = (new ModelParser())->parseLaravelFile($file);

        self::assertCount(1, $models);
        self::assertSame('posts', $models[0]['table']);
        self::assertSame(['title'], $models[0]['fillable']);
        self::assertSame('belongsTo', $models[0]['relations'][0]['type']);
        self::assertSame('App\Models\User', $models[0]['relations'][0]['target_model']);
    }

    public function testItParsesLaravelRelationsWithOptionalArguments(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-model-' . uniqid();
        mkdir($dir);
        $file = $dir . '/Post.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function commentable()
    {
        return $this->morphTo();
    }
}
PHP);

        $models = (new ModelParser())->parseLaravelFile($file);

        self::assertCount(1, $models);
        self::assertSame('hasMany', $models[0]['relations'][0]['type']);
        self::assertSame('App\Models\Comment', $models[0]['relations'][0]['target_model']);
        self::assertNull($models[0]['relations'][0]['foreign_key']);
        self::assertSame('morphTo', $models[0]['relations'][1]['type']);
        self::assertNull($models[0]['relations'][1]['target_model']);
        self::assertNull($models[0]['relations'][1]['foreign_key']);
    }

    public function testItParsesDoctrineEntities(): void
    {
        $dir = sys_get_temp_dir() . '/project-map-doctrine-' . uniqid();
        mkdir($dir);
        $file = $dir . '/User.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
final class User
{
    #[ORM\Column(type: 'string', nullable: true)]
    private string $email;
}
PHP);

        $models = (new ModelParser())->parseDoctrineFile($file);

        self::assertCount(1, $models);
        self::assertSame('users', $models[0]['table']);
        self::assertSame('email', $models[0]['fields'][0]['name']);
        self::assertSame('string', $models[0]['fields'][0]['type']);
        self::assertTrue($models[0]['fields'][0]['nullable']);
    }
}
