<?php

namespace olvlvl\ComposerAttributeCollector;

use function count;
use function is_array;
use function is_string;

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
     * Code downgraded for PHP < 8.1 cannot have `new` in attribute arguments. It can have
     * `[MARKER => [Foo::class, [arguments]]]` instead, which is rendered as `new \Foo(...arguments)`.
     */
    public const MARKER = 'olvlvl\\ComposerAttributeCollector\\NewExpression';

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

    /**
     * @param mixed $value
     */
    public static function fromMarker($value): ?self
    {
        if (!is_array($value) || count($value) !== 1 || !isset($value[self::MARKER])) {
            return null;
        }

        $new = $value[self::MARKER];
        if (!is_array($new) || count($new) !== 2 || !is_string($new[0] ?? null) || !is_array($new[1] ?? null)) {
            return null;
        }

        /** @var class-string $class */
        $class = $new[0];

        return new self($class, $new[1]);
    }
}
