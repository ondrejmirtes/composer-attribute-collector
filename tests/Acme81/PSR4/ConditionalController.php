<?php

namespace Acme81\PSR4;

use Acme81\Attribute\Conditional;
use Acme81\Attribute\Get;
use Acme81\Attribute\Method;
use Acme81\Attribute\Route;

#[Conditional(new Route('/conditional', Method::POST, 'conditional'), 'feature')]
class ConditionalController
{
    #[Conditional(['get' => new Get('/{id}')], 'other')]
    public function show()
    {
    }
}
