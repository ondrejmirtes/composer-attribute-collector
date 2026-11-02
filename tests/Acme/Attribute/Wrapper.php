<?php

namespace Acme\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Wrapper
{
    /**
     * @var object|array<object>
     */
    public $attribute;

    /**
     * @param object|array<object> $attribute
     */
    public function __construct($attribute)
    {
        $this->attribute = $attribute;
    }
}
