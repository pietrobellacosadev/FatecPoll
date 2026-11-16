<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Poll;
use App\Services\RankingService;

final class ResultController
{
    /** GET /resultados/{slug} — só quando a enquete permite resultados públicos. */
    public function show(string $slug): void
    {
        $poll = Poll::findPublicBySlug($slug);
        if (!$poll || !(int) $poll['results_public']) {
            http_response_code(404);
            view('public/404', ['title' => 'Resultados indisponíveis']);
            return;
        }
        view('public/results', [
            'title'      => 'Resultados · ' . $poll['title'],
            'poll'       => $poll,
            'hl'         => RankingService::highlights($poll),
            'withCharts' => true,
        ]);
    }
}
