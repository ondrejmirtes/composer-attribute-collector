<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Boolean implements SchemaAttribute
{
    public bool $null;
    public function __construct(bool $null = false)
    {
        $this->null = $null;
    }
}
