<?php

namespace Acme\Attribute\ActiveRecord;

use Attribute;

/**
 * An index on one or multiple columns.
 *
 * @readonly
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Index implements SchemaAttribute
{
    /**
     * @var string|array<string>
     */
    public $columns;
    public bool $unique;
    public ?string $name;
    /**
     * @param string|array<string> $columns
     *     Identifiers of the columns making the unique index.
     */
    public function __construct($columns, bool $unique = false, ?string $name = null)
    {
        $this->columns = $columns;
        $this->unique = $unique;
        $this->name = $name;
    }
}
