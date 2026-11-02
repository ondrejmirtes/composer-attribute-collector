<?php

namespace Acme81\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Conditional
{
    /**
     * @param Route|array<string, Route> $attribute
     */
    public function __construct(
        public Route|array $attribute,
        public string $if,
    ) {
    }
}
