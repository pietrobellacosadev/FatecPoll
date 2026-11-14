<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Enquetes: consultas compartilhadas entre as páginas públicas e administrativas. */
final class Poll
{
    /** Quem deve ter as enquetes filtradas no painel: null = administrador (vê tudo), id = professor (só as dele). */
    public static function scopeOwner(): ?int
    {
        return is_super_admin() ? null : admin_id();
    }

    /** A pessoa logada pode ver/editar esta enquete? Administrador sempre; professor só se for o dono. */
    public static function canManage(array $poll): bool
    {
        return is_super_admin() || (!empty($poll['owner_id']) && (int) $poll['owner_id'] === admin_id());
    }

    /**
     * Enquetes com nº de itens, nº de votos e nomes para o mosaico. $statuses = null → todas.
     * $ownerId != null → só as enquetes desse dono (professor).
     */
    public static function listWithMeta(?array $statuses = null, ?int $ownerId = null): array
    {
        $pdo = Database::pdo();
        $conds = [];
        $args = [];
        if ($statuses) {
            $conds[] = 'p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            $args = array_values($statuses);
        }
        if ($ownerId !== null) {
            $conds[] = 'p.owner_id = ?';
            $args[] = $ownerId;
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $stmt = $pdo->prepare(
            "SELECT p.id, p.owner_id, (SELECT a.name FROM admins a WHERE a.id = p.owner_id) AS owner_name,
                    p.title, p.slug, p.description, p.type, p.status, p.created_at,
                    CASE WHEN p.type = 'catalog'
                         THEN (SELECT COUNT(*) FROM games g WHERE g.poll_id = p.id AND g.active = 1)
                         ELSE (SELECT COUNT(*) FROM poll_options o WHERE o.poll_id = p.id AND o.active = 1) END AS items,
                    (SELECT COUNT(*) FROM game_ratings r JOIN games g2 ON g2.id = r.game_id WHERE g2.poll_id = p.id)
                  + (SELECT COUNT(*) FROM option_votes v JOIN poll_options o2 ON o2.id = v.option_id WHERE o2.poll_id = p.id) AS votes,
                    (SELECT COUNT(*) FROM participations pa WHERE pa.poll_id = p.id) AS participations
               FROM polls p $where
              ORDER BY p.created_at DESC, p.id DESC"
        );
        $stmt->execute($args);
        $polls = $stmt->fetchAll();
        if (!$polls) {
            return [];
        }

        $ids = array_column($polls, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $names = [];
        foreach ([
            "SELECT poll_id, name, cover_path AS cover FROM games WHERE poll_id IN ($in) AND active = 1 ORDER BY sort_order, id",
            "SELECT poll_id, label AS name, NULL AS cover FROM poll_options WHERE poll_id IN ($in) AND active = 1 ORDER BY sort_order, id",
        ] as $sql) {
            $q = $pdo->prepare($sql);
            $q->execute($ids);
            foreach ($q->fetchAll() as $row) {
                $names[$row['poll_id']][] = ['name' => $row['name'], 'cover' => $row['cover']];
            }
        }
        foreach ($polls as &$p) {
            $p['covers'] = array_slice($names[$p['id']] ?? [], 0, 4);
            $p['items'] = (int) $p['items'];
            $p['votes'] = (int) $p['votes'];
            $p['participations'] = (int) $p['participations'];
        }
        return $polls;
    }

    /** Números da faixa da home / dashboard. $ownerId != null → só as enquetes desse dono. */
    public static function stats(?int $ownerId = null): array
    {
        $pdo = Database::pdo();
        $today = date('Y-m-d 00:00:00');
        $own = $ownerId !== null ? ' AND p.owner_id = ' . (int) $ownerId : '';
        $count = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();

        $votes = $count("SELECT COUNT(*) FROM game_ratings r JOIN games g ON g.id = r.game_id JOIN polls p ON p.id = g.poll_id WHERE 1=1 $own")
               + $count("SELECT COUNT(*) FROM option_votes v JOIN poll_options o ON o.id = v.option_id JOIN polls p ON p.id = o.poll_id WHERE 1=1 $own");

        $t = $pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM game_ratings r JOIN participations pa ON pa.id = r.participation_id JOIN polls p ON p.id = pa.poll_id WHERE pa.created_at >= :d1 $own)
                  + (SELECT COUNT(*) FROM option_votes v JOIN participations pb ON pb.id = v.participation_id JOIN polls p ON p.id = pb.poll_id WHERE pb.created_at >= :d2 $own)"
        );
        $t->execute(['d1' => $today, 'd2' => $today]);

        return [
            'votes'  => $votes,
            'today'  => (int) $t->fetchColumn(),
            'items'  => $count("SELECT COUNT(*) FROM games g JOIN polls p ON p.id = g.poll_id WHERE p.status = 'published' AND g.active = 1 $own")
                      + $count("SELECT COUNT(*) FROM poll_options o JOIN polls p ON p.id = o.poll_id WHERE p.status = 'published' AND o.active = 1 $own"),
            'open'   => $count("SELECT COUNT(*) FROM polls p WHERE p.status = 'published' $own"),
            'polls'  => $count("SELECT COUNT(*) FROM polls p WHERE 1=1 $own"),
            'drafts' => $count("SELECT COUNT(*) FROM polls p WHERE p.status = 'draft' $own"),
            'closed' => $count("SELECT COUNT(*) FROM polls p WHERE p.status = 'closed' $own"),
        ];
    }

    /** Enquete "ao vivo": a de catálogo publicada mais recente. */
    public static function livePoll(): ?array
    {
        $row = Database::pdo()->query(
            "SELECT id, title, slug FROM polls WHERE type = 'catalog' AND status = 'published' ORDER BY created_at DESC, id DESC LIMIT 1"
        )->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM polls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Enquete visível ao público (publicada ou encerrada) pelo slug. */
    public static function findPublicBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM polls WHERE slug = :slug AND status IN ('published','closed') LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    /** Itens votáveis: jogos (catálogo, com integrantes) ou alternativas (opções). */
    public static function items(array $poll): array
    {
        $pdo = Database::pdo();
        if ($poll['type'] === 'catalog') {
            $q = $pdo->prepare('SELECT id, name, description, cover_path FROM games WHERE poll_id = :id AND active = 1 ORDER BY sort_order, id');
        } else {
            $q = $pdo->prepare('SELECT id, label AS name, NULL AS description, NULL AS cover_path FROM poll_options WHERE poll_id = :id AND active = 1 ORDER BY sort_order, id');
        }
        $q->execute(['id' => $poll['id']]);
        $items = $q->fetchAll();

        if ($poll['type'] === 'catalog') {
            $members = Game::membersFor(array_map('intval', array_column($items, 'id')));
            foreach ($items as &$it) {
                $it['members'] = $members[(int) $it['id']] ?? [];
            }
            unset($it);
        }
        return $items;
    }

    /** Votos já registrados por item: [id do jogo/alternativa => nº de respostas]. */
    public static function voteCounts(array $poll): array
    {
        $sql = $poll['type'] === 'catalog'
            ? 'SELECT r.game_id AS item_id, COUNT(*) AS n FROM game_ratings r JOIN games g ON g.id = r.game_id WHERE g.poll_id = ? GROUP BY r.game_id'
            : 'SELECT v.option_id AS item_id, COUNT(*) AS n FROM option_votes v JOIN poll_options o ON o.id = v.option_id WHERE o.poll_id = ? GROUP BY v.option_id';
        $q = Database::pdo()->prepare($sql);
        $q->execute([$poll['id']]);
        $out = [];
        foreach ($q->fetchAll() as $row) {
            $out[(int) $row['item_id']] = (int) $row['n'];
        }
        return $out;
    }

    /** A enquete aceita votos agora? (publicada e dentro da janela de datas, se houver) */
    public static function isOpen(array $poll): bool
    {
        if ($poll['status'] !== 'published') {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        return (empty($poll['starts_at']) || $poll['starts_at'] <= $now)
            && (empty($poll['ends_at']) || $poll['ends_at'] >= $now);
    }
}
