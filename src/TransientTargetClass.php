<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetClass
{
    /**
     * @var class-string
     */
    public string $attribute;
    /**
     * @var array<int|string, mixed>
     */
    public array $arguments;
    /**
     * @param class-string $attribute The attribute class.
     * @param array<int|string, mixed> $arguments The attribute arguments.
     */
    public function __construct(string $attribute, array $arguments)
    {
        $this->attribute = $attribute;
        $this->arguments = $arguments;
    }
}
