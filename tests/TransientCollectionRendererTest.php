<?php

namespace tests\olvlvl\ComposerAttributeCollector;

use Acme\Attribute\Get;
use Acme\Attribute\Permission;
use Acme\Attribute\Wrapper;
use olvlvl\ComposerAttributeCollector\AttributeGroupsReflector;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
use olvlvl\ComposerAttributeCollector\TransientCollection;
use olvlvl\ComposerAttributeCollector\TransientCollectionRenderer;
use olvlvl\ComposerAttributeCollector\TransientNewExpression;
use olvlvl\ComposerAttributeCollector\TransientTargetClass;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class TransientCollectionRendererTest extends TestCase
{
    /**
     * `new` in attribute arguments, collected from the AST like under PHP < 8.
     */
    public function testNewExpressionFromAst(): void
    {
        $code = <<<'PHP'
        <?php

        namespace Acme;

        use Acme\Attribute\Get;
        use Acme\Attribute\Permission;
        use Acme\Attribute\Wrapper;

        #[Wrapper(new Permission('is_admin'))]
        #[Wrapper([new Get('/{id}', 'show'), 'key' => new Get()])]
        class Wrapped
        {
        }
        PHP;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse($code);
        $this->assertNotNull($stmts);
        $traverser = new NodeTraverser(new NameResolver());
        $stmts = $traverser->traverse($stmts);
        $class = $stmts[0]->stmts[3]; // @phpstan-ignore property.notFound
        $this->assertInstanceOf(Class_::class, $class);

        $attributes = (new AttributeGroupsReflector('Acme\Wrapped'))->attrGroupsToAttributes($class->attrGroups);
        $targets = [];
        foreach ($attributes as $attribute) {
            /** @var class-string $name */
            $name = $attribute->getName();
            $targets[] = new TransientTargetClass($name, $attribute->getArguments());
        }

        $this->assertEquals([
            new TransientTargetClass(Wrapper::class, [
                new TransientNewExpression(Permission::class, ['is_admin']),
            ]),
            new TransientTargetClass(Wrapper::class, [
                [
                    new TransientNewExpression(Get::class, ['/{id}', 'show']),
                    'key' => new TransientNewExpression(Get::class, []),
                ],
            ]),
        ], $targets);

        $collection = new TransientCollection();
        $collection->addClassAttributes('Acme\Wrapped', $targets);

        $file = (string) tempnam(sys_get_temp_dir(), 'attributes');
        file_put_contents($file, TransientCollectionRenderer::render($collection));
        $previous = Attributes::with(fn() => null); // @phpstan-ignore argument.type

        try {
            require $file;

            $this->assertEquals([
                new TargetClass(new Wrapper(new Permission('is_admin')), 'Acme\Wrapped'),
                new TargetClass(new Wrapper([new Get('/{id}', 'show'), 'key' => new Get()]), 'Acme\Wrapped'),
            ], Attributes::findTargetClasses(Wrapper::class));
        } finally {
            unlink($file);
            if ($previous !== null) {
                Attributes::with($previous);
            }
        }
    }
}
