<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 * @internal
 */
final class TransientTargetProperty
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
     * @var non-empty-string
     */
    public string $name;
    /**
     * @param class-string $attribute The attribute class.
     * @param array<int|string, mixed> $arguments The attribute arguments.
     * @param non-empty-string $name The target property.
     */
    public function __construct(string $attribute, array $arguments, string $name)
    {
        $this->attribute = $attribute;
        $this->arguments = $arguments;
        $this->name = $name;
    }
}
