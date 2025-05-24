<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\PSR4;

use Acme\Attribute\Permission;

#[Permission('is_admin')]
#[Permission('can_delete_menu')]
final class DeleteMenu
{
    public int $id;
    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
