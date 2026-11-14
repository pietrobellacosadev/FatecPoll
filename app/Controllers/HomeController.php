<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Poll;

final class HomeController
{
    public function index(): void
    {
        view('public/home', [
            'title' => 'Início',
            'stats' => Poll::stats(),
            'open'  => array_slice(Poll::listWithMeta(['published']), 0, 6),
            'hero'  => true,
        ]);
    }
}
