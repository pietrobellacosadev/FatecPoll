<?php
declare(strict_types=1);

// Com o servidor embutido (php -S ... public/index.php), arquivos estáticos são servidos direto.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\PollController;
use App\Controllers\AuthAdminController;
use App\Controllers\DashboardController;
use App\Controllers\PollAdminController;
use App\Controllers\VoteController;
use App\Controllers\ResultController;
use App\Controllers\TeacherAuthController;
use App\Controllers\AuthorController;

$router = new Router();

// Públicas
$router->get('/', [HomeController::class, 'index']);
$router->get('/enquetes', [PollController::class, 'index']);
$router->get('/avaliar', static fn() => redirect('/enquetes')); // endereço antigo
$router->get('/e/{slug}', [PollController::class, 'show']);
$router->get('/e/{slug}/status', [PollController::class, 'status']);
$router->post('/e/{slug}/votar', [VoteController::class, 'store']);
$router->get('/e/{slug}/confirmacao', [VoteController::class, 'confirmation']);
$router->get('/resultados/{slug}', [ResultController::class, 'show']);
$router->get('/autores', [AuthorController::class, 'index']); // rota reservada: a tela de autores ainda será desenvolvida

// Cadastro de professores (e-mail institucional + código por e-mail)
$router->get('/cadastro', [TeacherAuthController::class, 'showRegister']);
$router->post('/cadastro', [TeacherAuthController::class, 'register']);
$router->get('/cadastro/verificar', [TeacherAuthController::class, 'showVerify']);
$router->post('/cadastro/verificar', [TeacherAuthController::class, 'verify']);
$router->post('/cadastro/reenviar', [TeacherAuthController::class, 'resend']);

// Administrativas (todas exigem sessão; alterações usam POST + CSRF)
$router->get('/admin/login', [AuthAdminController::class, 'showLogin']);
$router->post('/admin/login', [AuthAdminController::class, 'login']);
$router->post('/admin/logout', [AuthAdminController::class, 'logout']);
$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/enquetes', [PollAdminController::class, 'index']);
$router->get('/admin/enquetes/criar', [PollAdminController::class, 'create']);
$router->post('/admin/enquetes', [PollAdminController::class, 'store']);
$router->get('/admin/enquetes/{id}/editar', [PollAdminController::class, 'edit']);
$router->post('/admin/enquetes/{id}/editar', [PollAdminController::class, 'update']);
$router->post('/admin/enquetes/{id}/publicar', [PollAdminController::class, 'publish']);
$router->post('/admin/enquetes/{id}/encerrar', [PollAdminController::class, 'close']);
$router->get('/admin/enquetes/{id}/resultados', [PollAdminController::class, 'results']);
$router->get('/admin/enquetes/{id}/compartilhar', [PollAdminController::class, 'share']);

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
