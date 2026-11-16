<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/** Participação confirmada: um envio completo de votos em uma enquete (sem dados pessoais). */
final class Participation
{
    /** Cria a participação. Deve ser chamada dentro da transação do voto. $risk = 1 marca sinal de duplicidade. */
    public static function create(PDO $pdo, int $pollId, ?string $ipHash = null, ?string $fpHash = null, int $risk = 0): int
    {
        $pdo->prepare('INSERT INTO participations (poll_id, ip_hash, fp_hash, risk) VALUES (?, ?, ?, ?)')
            ->execute([$pollId, $ipHash, $fpHash, $risk]);
        return (int) $pdo->lastInsertId();
    }

    /** Algum destes dispositivos já participou desta enquete? */
    public static function deviceHasVoted(PDO $pdo, int $pollId, array $deviceHashes): bool
    {
        if (!$deviceHashes) {
            return false;
        }
        $in = implode(',', array_fill(0, count($deviceHashes), '?'));
        $q = $pdo->prepare("SELECT 1 FROM participation_devices WHERE poll_id = ? AND device_hash IN ($in) LIMIT 1");
        $q->execute([$pollId, ...array_values($deviceHashes)]);
        return (bool) $q->fetchColumn();
    }

    /**
     * Amarra os dispositivos à participação. A restrição UNIQUE (poll_id, device_hash) faz o banco
     * recusar (PDOException, código 1062) um dispositivo que já participou, mesmo com requisições simultâneas.
     */
    public static function claimDevices(PDO $pdo, int $pollId, int $participationId, array $deviceHashes): void
    {
        $ins = $pdo->prepare('INSERT INTO participation_devices (poll_id, participation_id, device_hash) VALUES (?, ?, ?)');
        foreach (array_unique($deviceHashes) as $hash) {
            $ins->execute([$pollId, $participationId, $hash]);
        }
    }

    /** Já existe participação nesta enquete com o mesmo fingerprint e a mesma rede? (sinal antifraude) */
    public static function signalExists(PDO $pdo, int $pollId, string $fpHash, string $ipHash): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM participations WHERE poll_id = ? AND fp_hash = ? AND ip_hash = ? LIMIT 1');
        $q->execute([$pollId, $fpHash, $ipHash]);
        return (bool) $q->fetchColumn();
    }

    public static function countForPoll(PDO $pdo, int $pollId): int
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM participations WHERE poll_id = ?');
        $q->execute([$pollId]);
        return (int) $q->fetchColumn();
    }

    public static function countRiskForPoll(PDO $pdo, int $pollId): int
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM participations WHERE poll_id = ? AND risk = 1');
        $q->execute([$pollId]);
        return (int) $q->fetchColumn();
    }
}
