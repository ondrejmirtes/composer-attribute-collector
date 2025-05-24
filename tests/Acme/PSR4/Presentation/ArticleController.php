<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\PSR4\Presentation;

use Acme\Attribute\Resource;
use Acme\Attribute\Route;

#[Resource("articles")]
final class ArticleController
{
    #[Route("/articles", 'GET', 'articles:list')]
    public function list(): void
    {
    }

    #[Route("/articles/{id}", 'GET', 'articles:show')]
    public function show(int $id): void
    {
    }
}
