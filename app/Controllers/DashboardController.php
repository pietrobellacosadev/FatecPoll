<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Poll;
use App\Services\RankingService;

final class DashboardController
{
    /** Resumo geral + destaques de cada enquete (abertas primeiro, depois encerradas). */
    public function index(): void
    {
        require_admin();

        $boards = [];
        foreach (Poll::listWithMeta(['published', 'closed'], Poll::scopeOwner()) as $p) {
            $boards[] = ['poll' => $p, 'hl' => RankingService::highlights($p)];
        }
        usort($boards, fn($a, $b) => [$a['poll']['status'] === 'published' ? 0 : 1] <=> [$b['poll']['status'] === 'published' ? 0 : 1]);

        // Seleção: ?e=<id> mostra uma só; ?e=all mostra todas; sem parâmetro, a primeira (mais relevante)
        $ids = array_map(fn($b) => (string) $b['poll']['id'], $boards);
        $sel = (string) ($_GET['e'] ?? '');
        if ($sel !== 'all' && !in_array($sel, $ids, true)) {
            $sel = $ids[0] ?? 'all';
        }

        view('admin/dashboard', [
            'title'    => 'Dashboard',
            'stats'    => Poll::stats(Poll::scopeOwner()),
            'boards'   => $boards,
            'selected' => $sel,
            'withCharts' => true,
        ]);
    }
}
