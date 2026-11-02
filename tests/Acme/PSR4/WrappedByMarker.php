<?php

namespace Acme\PSR4;

use Acme\Attribute\Get;
use Acme\Attribute\Permission;
use Acme\Attribute\Wrapper;

// `new` in attribute arguments as written by a downgrade for PHP < 8.1
#[Wrapper(['olvlvl\ComposerAttributeCollector\NewExpression' => [Permission::class, ['is_admin']]])]
#[Wrapper([['olvlvl\ComposerAttributeCollector\NewExpression' => [Get::class, ['/{id}', 'show']]], 'key' => 'not a new expression'])]
final class WrappedByMarker
{
}
