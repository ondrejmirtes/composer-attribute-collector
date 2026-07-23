<?php

namespace olvlvl\ComposerAttributeCollector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Locates class-like AST nodes through the class map, without autoloading anything.
 *
 * @internal
 */
class ClassAstLocator
{
    private CachedParser $cachedParser;

    /** @var array<class-string, string> */
    private array $classMap = [];

    public function __construct(CachedParser $cachedParser)
    {
        $this->cachedParser = $cachedParser;
    }

    /**
     * @param array<class-string, string> $classMap
     *     Where _key_ is a class and _value_ its pathname.
     */
    public function setClassMap(array $classMap): void
    {
        $this->classMap = $classMap;
    }

    public function findFilepath(string $class): ?string
    {
        return $this->classMap[$class] ?? null;
    }

    public function findClassNode(string $class): ?ClassLike
    {
        $filepath = $this->findFilepath($class);

        if ($filepath === null) {
            return null;
        }

        $ast = $this->cachedParser->parse($filepath);
        $visitor = new class ($class) extends NodeVisitorAbstract {
            private string $className;

            public ?ClassLike $found = null;

            public function __construct(string $className)
            {
                $this->className = $className;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof ClassLike) {
                    if ($node->namespacedName !== null && $node->namespacedName->toString() === $this->className) {
                        $this->found = $node;
                    }
                }

                return null;
            }
        };
        (new NodeTraverser($visitor))->traverse($ast);

        return $visitor->found;
    }
}
