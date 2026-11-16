<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Game;

/**
 * Rankings e médias.
 * Catálogo: média de cada pergunta por jogo e pontuação geral (pesos iguais entre as 3 perguntas).
 * Opções: votos, percentual e ordenação por quantidade (popularidade, não nota).
 */
final class RankingService
{
    /** Ranking de jogos (média das 3 perguntas, pesos iguais). */
    public static function gameRanking(int $pollId, ?int $limit = null): array
    {
        $sql = 'SELECT g.id, g.name, g.cover_path, COUNT(r.id) AS votes,
                       AVG(r.q_time) AS q_time, AVG(r.q_fun) AS q_fun, AVG(r.q_replay) AS q_replay,
                       AVG((r.q_time + r.q_fun + r.q_replay) / 3.0) AS score
                  FROM games g LEFT JOIN game_ratings r ON r.game_id = g.id
                 WHERE g.poll_id = :id AND g.active = 1
                 GROUP BY g.id, g.name, g.cover_path
                 ORDER BY score DESC, votes DESC, g.id';
        if ($limit) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $pollId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['votes'] = (int) $r['votes'];
            foreach (['q_time', 'q_fun', 'q_replay', 'score'] as $k) {
                $r[$k] = $r[$k] === null ? null : (float) $r[$k];
            }
        }
        return $rows;
    }

    /** Resultado de enquete de alternativas. */
    public static function optionRanking(int $pollId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.id, o.label AS name, COUNT(v.id) AS votes
               FROM poll_options o LEFT JOIN option_votes v ON v.option_id = o.id
              WHERE o.poll_id = :id AND o.active = 1
              GROUP BY o.id, o.label
              ORDER BY votes DESC, o.id'
        );
        $stmt->execute(['id' => $pollId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['votes'] = (int) $r['votes'];
        }
        return $rows;
    }

    /**
     * Destaques do dashboard.
     * Catálogo: jogo mais bem avaliado no geral e em cada uma das 3 perguntas.
     * Enquete: alternativa mais votada (com aviso de empate).
     */
    public static function highlights(array $poll): array
    {
        $pollId = (int) $poll['id'];

        if ($poll['type'] === 'options') {
            $rows = self::optionRanking($pollId);
            $total = array_sum(array_column($rows, 'votes'));
            $top = $total > 0 ? array_values(array_filter($rows, fn($r) => $r['votes'] === $rows[0]['votes'])) : [];
            return [
                'type' => 'options', 'empty' => $total === 0, 'rows' => $rows, 'total' => $total,
                'winner' => $top[0] ?? null, 'tied' => array_slice($top, 1),
                'percent' => $total > 0 ? $rows[0]['votes'] / $total * 100 : 0,
            ];
        }

        $rows = self::gameRanking($pollId);
        $rated = array_values(array_filter($rows, fn($r) => $r['votes'] > 0));
        if (!$rated) {
            return ['type' => 'catalog', 'empty' => true, 'rows' => $rows, 'total' => 0];
        }

        $best = static function (string $key) use ($rated): array {
            $copy = $rated;
            usort($copy, fn($a, $b) => [$b[$key], $b['votes'], $a['id']] <=> [$a[$key], $a['votes'], $b['id']]);
            return $copy[0];
        };
        $overall = $best('score');
        $byQuestion = [];
        foreach (array_keys(rating_questions()) as $k) {
            $byQuestion[$k] = $best($k);
        }

        $ids = array_unique(array_merge([$overall['id']], array_column($byQuestion, 'id')));
        $members = Game::membersFor($ids);
        $overall['members'] = $members[$overall['id']] ?? [];
        foreach ($byQuestion as &$g) {
            $g['members'] = $members[$g['id']] ?? [];
        }

        return [
            'type' => 'catalog', 'empty' => false, 'rows' => $rows,
            'total' => array_sum(array_column($rows, 'votes')),
            'overall' => $overall, 'by_question' => $byQuestion,
        ];
    }

    /**
     * Dados para o charts.js (Apache ECharts). Só jogos com ao menos 1 avaliação entram nos gráficos.
     */
    public static function chartData(array $poll, array $hl): array
    {
        if ($poll['type'] === 'options') {
            return [
                'type'    => 'options',
                'total'   => (int) $hl['total'],
                'options' => array_map(fn($r) => ['name' => $r['name'], 'votes' => (int) $r['votes']], $hl['rows']),
            ];
        }
        $games = [];
        foreach ($hl['rows'] as $r) {
            if ($r['votes'] > 0) {
                $games[] = [
                    'name' => $r['name'], 'votes' => $r['votes'],
                    'score' => round((float) $r['score'], 2),
                    'q_time' => round((float) $r['q_time'], 2),
                    'q_fun' => round((float) $r['q_fun'], 2),
                    'q_replay' => round((float) $r['q_replay'], 2),
                ];
            }
        }
        return ['type' => 'catalog', 'labels' => rating_labels(), 'games' => $games];
    }
}
