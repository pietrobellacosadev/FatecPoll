<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Poll;
use App\Services\Device;
use App\Services\VotingService;

final class VoteController
{
    /** POST /e/{slug}/votar — responde JSON para o fetch do voting.js; sem JS, redireciona. */
    public function store(string $slug): void
    {
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

        // Usuários autenticados no painel (admin ou professor) não podem votar.
        // A validação é obrigatória no backend, mesmo em requisições manuais.
        if (is_admin()) {
            $this->respond($wantsJson, $slug, ['ok' => false, 'status' => 403,
                'errors' => ['Administradores e professores não podem participar da votação.']]);
            return;
        }

        if (!csrf_valid()) {
            $this->respond($wantsJson, $slug, ['ok' => false, 'status' => 419, 'errors' => ['Sessão expirada. Recarregue a página e tente de novo.']]);
            return;
        }

        // Identidade do dispositivo definida pelo servidor (cookie assinado + cópia do navegador): o cliente não escolhe o id.
        $device = Device::resolve();
        Device::remember($device['token']);

        $result = VotingService::submit($slug, $_POST, $device);
        if ($result['ok'] || ($result['code'] ?? null) === 'already_voted') {
            // Registro da confirmação nesta sessão: a tela de confirmação só abre depois de um voto salvo.
            $_SESSION['voted'][$slug] = time();
        }
        $this->respond($wantsJson, $slug, $result);
    }

    /** GET /e/{slug}/confirmacao — só aparece depois do servidor confirmar o voto. */
    public function confirmation(string $slug): void
    {
        $poll = Poll::findPublicBySlug($slug);
        if (!$poll) {
            http_response_code(404);
            view('public/404', ['title' => 'Enquete não encontrada']);
            return;
        }
        // Abre depois de um voto desta sessão ou para quem (por este dispositivo) já votou: o servidor decide.
        if (empty($_SESSION['voted'][$slug]) && !VotingService::deviceHasVoted((int) $poll['id'], Device::resolve())) {
            redirect('/e/' . $slug);
        }
        view('public/confirmation', ['title' => 'Voto registrado', 'poll' => $poll]);
    }

    private function respond(bool $json, string $slug, array $r): void
    {
        $already = ($r['code'] ?? null) === 'already_voted';
        $redirect = url('/e/' . $slug . ($r['ok'] ? '/confirmacao' : ''));
        if ($json) {
            http_response_code($r['status']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $r['ok'], 'code' => $r['code'] ?? null, 'errors' => $r['errors'], 'redirect' => $redirect], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$r['ok']) {
            flash('error', implode(' ', $r['errors']));
            if ($already) {
                $redirect = url('/e/' . $slug);
            }
        }
        header('Location: ' . $redirect);
    }
}
