<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * A `new Foo(...)` attribute argument collected from the AST, where the class must not be autoloaded.
 * It is rendered back as a `new` expression in the generated file.
 *
 * @readonly
 * @internal
 */
final class TransientNewExpression
{
    /**
     * @var class-string
     */
    public string $class;
    /**
     * @var array<int|string, mixed>
     */
    public array $arguments;

    /**
     * @param class-string $class
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(string $class, array $arguments)
    {
        $this->class = $class;
        $this->arguments = $arguments;
    }
}
