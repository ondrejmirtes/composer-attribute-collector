<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Varchar implements SchemaAttribute
{
    public int $size;
    public bool $null;
    public bool $unique;
    public ?string $collate;
    public function __construct(int $size = 255, bool $null = false, bool $unique = false, ?string $collate = null)
    {
        $this->size = $size;
        $this->null = $null;
        $this->unique = $unique;
        $this->collate = $collate;
    }
}
