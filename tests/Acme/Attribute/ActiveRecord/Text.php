<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Text implements SchemaAttribute
{
    /**
     * @var string|null
     */
    public $size;
    public bool $null;
    public bool $unique;
    public ?string $collate;
    /**
     * @param string|null $size
     */
    public function __construct($size = null, bool $null = false, bool $unique = false, ?string $collate = null)
    {
        $this->size = $size;
        $this->null = $null;
        $this->unique = $unique;
        $this->collate = $collate;
    }
}
