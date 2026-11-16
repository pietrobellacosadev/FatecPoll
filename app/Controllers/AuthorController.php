<?php
declare(strict_types=1);

namespace App\Controllers;

/** Rota /autores, reservada para a futura tela de autores (por ora, só uma página provisória). */
final class AuthorController
{
    public function index(): void
    {
        view('public/authors', ['title' => 'Autores']);
    }
}
