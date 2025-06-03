<?php

namespace olvlvl\ComposerAttributeCollector;

use Composer\IO\IOInterface;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * @internal
 */
class CachedParser
{
    private Parser $parser;

    /** @var array<string, Node[]> */
    private array $parserCache = [];

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @return Node[]
     */
    public function parse(string $file): array
    {
        if (isset($this->parserCache[$file])) {
            return $this->parserCache[$file];
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $ast = $this->parser->parse($contents);
        assert($ast !== null);
        $nameTraverser = new NodeTraverser(new NameResolver());

        return $this->parserCache[$file] = $nameTraverser->traverse($ast);
    }
}
