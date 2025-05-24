<?php

namespace tests\olvlvl\ComposerAttributeCollector;

use Acme\Attribute\ActiveRecord\Id;
use Acme\Attribute\ActiveRecord\Index;
use Acme\Attribute\ActiveRecord\SchemaAttribute;
use Acme\Attribute\ActiveRecord\Serial;
use Acme\Attribute\ActiveRecord\Text;
use Acme\Attribute\ActiveRecord\Varchar;
use Acme\Attribute\Get;
use Acme\Attribute\Post;
use Acme\Attribute\Route;
use Acme\Presentation\FileController;
use Acme\Presentation\ImageController;
use Acme\PSR4\ActiveRecord\Article;
use Acme\PSR4\Presentation\ArticleController;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\Collection;
use olvlvl\ComposerAttributeCollector\TargetClass;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use olvlvl\ComposerAttributeCollector\TargetProperty;
use PHPUnit\Framework\TestCase;

use function in_array;

final class CollectionTest extends TestCase
{
    public function testFilterTargetClasses(): void
    {
        $collection = new Collection(
            [
                Route::class => [
                    [ [ '/articles' ], ArticleController::class ],
                    [ [ '/images' ], ImageController::class ],
                    [ [ '/files' ], FileController::class ],
                ],
            ],
            [
            ],
            [
            ]
        );

        $actual = $collection->filterTargetClasses(
            fn($a, $c) => in_array($c, [ ArticleController::class, ImageController::class ])
        );

        $this->assertEquals([
            new TargetClass(new Route('/articles'), ArticleController::class),
            new TargetClass(new Route('/images'), ImageController::class),
        ], $actual);
    }

    public function testFilterTargetMethods(): void
    {
        $collection = new Collection(
            [
            ],
            [
                Route::class => [
                    [ [ '/recent' ], ArticleController::class, 'recent' ],
                ],
                Get::class => [
                    [ [ ], ArticleController::class, 'show' ],
                ],
                Post::class => [
                    [ [ ], ArticleController::class, 'create' ],
                ],
            ],
            [
            ]
        );

        $actual = $collection->filterTargetMethods(fn($a) => is_a($a, Route::class, true));

        $this->assertEquals([
            new TargetMethod(new Route('/recent'), ArticleController::class, 'recent'),
            new TargetMethod(new Get(), ArticleController::class, 'show'),
            new TargetMethod(new Post(), ArticleController::class, 'create'),
        ], $actual);
    }

    public function testFilterTargetProperties(): void
    {
        $collection = new Collection(
            [
            ],
            [
                Route::class => [
                    [ [ '/recent' ], ArticleController::class, 'recent' ],
                ],
                Get::class => [
                    [ [ ], ArticleController::class, 'show' ],
                ],
                Post::class => [
                    [ [ ], ArticleController::class, 'create' ],
                ],
            ],
            [
                Id::class => [
                    [ [ ], Article::class, 'id' ],
                ],
                Serial::class => [
                    [ [ ], Article::class, 'id' ],
                ],
                Varchar::class => [
                    [ [ 80 ], Article::class, 'title' ],
                ],
                Text::class => [
                    [ [ ], Article::class, 'body' ],
                ]
            ]
        );

        $actual = $collection->filterTargetProperties(
            Attributes::predicateForAttributeInstanceOf(SchemaAttribute::class)
        );

        $this->assertEquals([
            new TargetProperty(new Id(), Article::class, 'id'),
            new TargetProperty(new Serial(), Article::class, 'id'),
            new TargetProperty(new Varchar(80), Article::class, 'title'),
            new TargetProperty(new Text(), Article::class, 'body'),
        ], $actual);
    }

    public function testForClass(): void
    {
        $collection = new Collection(
            [
                Index::class => [
                    [ [ 'slug', true ], Article::class ],
                ],
                Route::class => [ // trap
                    [ [ '/articles' ], ArticleController::class ],
                ],
            ],
            [
                Route::class => [ // trap
                    [ [ '/recent' ], ArticleController::class, 'recent' ],
                ],
            ],
            [
                Id::class => [
                    [ [ ], Article::class, 'id' ],
                ],
                Serial::class => [
                    [ [ ], Article::class, 'id' ],
                ],
                Varchar::class => [
                    [ [ 80 ], Article::class, 'title' ],
                    [ [ 80 ], Article::class, 'slug' ],
                ],
                Text::class => [
                    [ [ ], Article::class, 'body' ],
                ]
            ]
        );

        $actual = $collection->forClass(Article::class);

        $this->assertEquals([
            new Index('slug', true),
        ], $actual->classAttributes);

        $this->assertEmpty($actual->methodsAttributes);

        $this->assertEquals([
            'id' => [
                new Id(),
                new Serial(),
            ],
            'title' => [
                new Varchar(80),
            ],
            'slug' => [
                new Varchar(80),
            ],
            'body' => [
                new Text(),
            ]
        ], $actual->propertyAttributes);
    }
}
