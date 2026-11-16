<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Poll;
use App\Services\Device;
use App\Services\VotingService;

final class PollController
{
    /** GET /enquetes — enquetes publicadas e encerradas, para o público. */
    public function index(): void
    {
        view('public/avaliar', [
            'title' => 'Enquetes',
            'polls' => Poll::listWithMeta(['published', 'closed']),
        ]);
    }

    /** GET /e/{slug} — página pública da enquete e modal de votação. */
    public function show(string $slug): void
    {
        $poll = Poll::findPublicBySlug($slug);
        if (!$poll) {
            http_response_code(404);
            view('public/404', ['title' => 'Enquete não encontrada']);
            return;
        }

        if (is_admin()) {
            http_response_code(403);
            view('public/404', ['title' => 'Votação indisponível para usuários administrativos']);
            return;
        }

        // Identifica o dispositivo (cookie assinado, persistente) e consulta no servidor se ele já votou.
        $device = Device::resolve();
        Device::remember($device['token']);
        header('Cache-Control: private, no-store'); // a página carrega o token do dispositivo: nunca em cache compartilhado

        view('public/poll', [
            'title'    => $poll['title'],
            'poll'     => $poll,
            'items'    => Poll::items($poll),
            'isOpen'   => Poll::isOpen($poll),
            'hasVoted' => VotingService::deviceHasVoted((int) $poll['id'], $device),
            'deviceToken' => $device['token'],
            'extraJs'  => ['voting.js'],
        ]);
    }

    /**
     * GET /e/{slug}/status — o dispositivo (cookie ou cópia enviada no cabeçalho X-Device-Token) já votou?
     * Só informa o estado do PRÓPRIO dispositivo; devolve o token canônico para o navegador manter a cópia.
     */
    public function status(string $slug): void
    {
        $poll = Poll::findPublicBySlug($slug);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        if (!$poll) {
            http_response_code(404);
            echo json_encode(['voted' => false]);
            return;
        }
        if (is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso não permitido.']);
            return;
        }
        $device = Device::resolve();
        Device::remember($device['token']);
        echo json_encode(['voted' => VotingService::deviceHasVoted((int) $poll['id'], $device), 'token' => $device['token']]);
    }
}
