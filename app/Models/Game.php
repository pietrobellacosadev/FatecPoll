<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Jogos de um catálogo. */
final class Game
{
    /** Integrantes por jogo: [game_id => [nomes]]. */
    public static function membersFor(array $gameIds): array
    {
        if (!$gameIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($gameIds), '?'));
        $q = Database::pdo()->prepare("SELECT game_id, name FROM game_members WHERE game_id IN ($in) ORDER BY id");
        $q->execute(array_values($gameIds));
        $out = [];
        foreach ($q->fetchAll() as $row) {
            $out[(int) $row['game_id']][] = $row['name'];
        }
        return $out;
    }
}
