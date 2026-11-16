<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Participation;
use App\Models\Poll;

/**
 * Regras de votação.
 *  - sanitize(): validação pura (sem banco), fácil de testar.
 *  - submit(): revalida no servidor e grava participação + respostas em UMA transação.
 * Sem conta de participante, o voto único é por DISPOSITIVO (veja Device e a seção "Voto único" do README):
 * a regra vale no servidor e no banco; o localStorage do navegador é só experiência de uso.
 */
final class VotingService
{
    public const QUESTIONS = ['q_time', 'q_fun', 'q_replay'];

    /**
     * @param string $type     'catalog' | 'options'
     * @param int[]  $validIds ids de jogos (catalog) ou alternativas (options) da enquete
     * @param array  $input    $_POST: ratings[gameId][q_time|q_fun|q_replay] ou option
     * @return array{data: array, errors: string[]}
     */
    public static function sanitize(string $type, array $validIds, array $input): array
    {
        $errors = [];
        $valid = array_flip(array_map('intval', $validIds));

        if ($type === 'options') {
            $opt = filter_var($input['option'] ?? null, FILTER_VALIDATE_INT);
            if ($opt === false || !isset($valid[$opt])) {
                return ['data' => [], 'errors' => ['Escolha uma das alternativas.']];
            }
            return ['data' => ['option' => $opt], 'errors' => []];
        }

        $ratings = [];
        $raw = $input['ratings'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        foreach ($raw as $gameId => $answers) {
            $gid = filter_var($gameId, FILTER_VALIDATE_INT);
            if ($gid === false || !isset($valid[$gid])) {
                $errors[] = 'Há um jogo que não pertence a esta enquete.';
                continue;
            }
            if (!is_array($answers)) {
                continue;
            }
            $row = [];
            $given = 0;
            foreach (self::QUESTIONS as $q) {
                if (!isset($answers[$q]) || $answers[$q] === '') {
                    continue;
                }
                $given++;
                $n = filter_var($answers[$q], FILTER_VALIDATE_INT);
                if ($n === false || $n < 1 || $n > 5) {
                    $errors[] = 'As notas devem ser números inteiros de 1 a 5.';
                    continue 2;
                }
                $row[$q] = $n;
            }
            if ($given === 0) {
                continue; // jogo não avaliado
            }
            if ($given !== count(self::QUESTIONS)) {
                $errors[] = 'Responda as 3 perguntas de cada jogo que você avaliar.';
                continue;
            }
            $ratings[$gid] = $row;
        }
        if (!$errors && !$ratings) {
            $errors[] = 'Avalie ao menos um jogo.';
        }
        return ['data' => ['ratings' => $ratings], 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Registra o voto. Retorna ['ok' => bool, 'status' => int HTTP, 'errors' => string[], 'code' => ?string].
     * Qualquer falha desfaz tudo (nada é salvo, nenhuma confirmação falsa).
     *
     * Voto único por dispositivo, validado AQUI (servidor) e garantido pelo banco:
     *  1. a elegibilidade é checada antes de gravar qualquer coisa;
     *  2. participação + dispositivos + respostas entram na MESMA transação;
     *  3. UNIQUE (poll_id, device_hash) recusa o segundo voto mesmo se duas requisições passarem juntas pela checagem.
     *
     * @param array{ids: string[], token?: string, fresh?: bool} $device resultado de Device::resolve()
     */
    public static function submit(string $slug, array $input, array $device): array
    {
        // Deadlock entre votos simultâneos é raro, mas possível: repete. Na repetição, a checagem
        // de elegibilidade já enxerga o voto que venceu e responde "já votou".
        for ($try = 1; ; $try++) {
            try {
                return self::attempt($slug, $input, $device);
            } catch (\PDOException $e) {
                $driver = (int) ($e->errorInfo[1] ?? 0);
                if (in_array($driver, [1213, 1205], true) && $try < 3) {
                    usleep(random_int(20, 120) * 1000);
                    continue;
                }
                error_log('FatecPoll voto falhou: ' . $e->getMessage());
                return self::fail(500, 'Não foi possível registrar seu voto. Tente novamente.');
            } catch (\Throwable $e) {
                error_log('FatecPoll voto falhou: ' . $e->getMessage());
                return self::fail(500, 'Não foi possível registrar seu voto. Tente novamente.');
            }
        }
    }

    private static function fail(int $status, string $message, ?string $code = null): array
    {
        return ['ok' => false, 'status' => $status, 'errors' => [$message], 'code' => $code];
    }

    /** O dispositivo desta requisição já votou nesta enquete? (usado pela página e pelo /status) */
    public static function deviceHasVoted(int $pollId, array $device): bool
    {
        $hashes = array_map(static fn(string $id): string => Device::deviceHash($pollId, $id), $device['ids'] ?? []);
        return Participation::deviceHasVoted(Database::pdo(), $pollId, $hashes);
    }

    private static function attempt(string $slug, array $input, array $device): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Trava a linha da enquete em modo compartilhado: encerrar a votação espera este envio terminar.
            $q = $pdo->prepare("SELECT * FROM polls WHERE slug = ? AND status IN ('published','closed') LIMIT 1 LOCK IN SHARE MODE");
            $q->execute([$slug]);
            $poll = $q->fetch();

            if (!$poll) {
                $pdo->rollBack();
                return self::fail(404, 'Enquete não encontrada.');
            }
            if (!Poll::isOpen($poll)) {
                $pdo->rollBack();
                return self::fail(409, 'Esta enquete não está aceitando votos.');
            }

            $pollId = (int) $poll['id'];
            $hashes = array_map(static fn(string $id): string => Device::deviceHash($pollId, $id), $device['ids'] ?? []);
            if (!$hashes) {
                $pdo->rollBack();
                return self::fail(400, 'Não foi possível identificar o dispositivo. Recarregue a página e tente de novo.');
            }

            // 1) Elegibilidade ANTES de gravar: este dispositivo já participou?
            if (Participation::deviceHasVoted($pdo, $pollId, $hashes)) {
                $pdo->rollBack();
                return self::fail(409, 'Você já votou nesta enquete. Cada participante pode votar apenas uma vez.', 'already_voted');
            }

            $ids = array_column(Poll::items($poll), 'id');
            $res = self::sanitize($poll['type'], $ids, $input);
            if ($res['errors']) {
                $pdo->rollBack();
                return ['ok' => false, 'status' => 422, 'errors' => $res['errors'], 'code' => null];
            }

            // 2) Sinal antifraude: dispositivo sem histórico, mas com o mesmo fingerprint e a mesma rede de um voto anterior
            $ipHash = Device::ipHash($pollId);
            $fpHash = Device::fingerprintHash($pollId, $input['fp'] ?? null);
            $risk = 0;
            if ($ipHash !== null && $fpHash !== null && Participation::signalExists($pdo, $pollId, $fpHash, $ipHash)) {
                if (config('duplicate_signal') === 'block') {
                    $pdo->rollBack();
                    return self::fail(409, 'Já existe uma participação deste aparelho e desta rede nesta enquete.', 'already_voted');
                }
                $risk = 1;
            }

            // 3) Grava tudo na mesma transação; o UNIQUE do banco é a trava final contra votos simultâneos.
            $participationId = Participation::create($pdo, $pollId, $ipHash, $fpHash, $risk);
            Participation::claimDevices($pdo, $pollId, $participationId, $hashes);

            if ($poll['type'] === 'options') {
                $pdo->prepare('INSERT INTO option_votes (participation_id, option_id) VALUES (?, ?)')
                    ->execute([$participationId, $res['data']['option']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO game_ratings (participation_id, game_id, q_time, q_fun, q_replay) VALUES (?, ?, ?, ?, ?)');
                foreach ($res['data']['ratings'] as $gameId => $r) {
                    $ins->execute([$participationId, $gameId, $r['q_time'], $r['q_fun'], $r['q_replay']]);
                }
            }
            $pdo->commit();
            return ['ok' => true, 'status' => 200, 'errors' => [], 'code' => null];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 1062 = chave duplicada: outra requisição do mesmo dispositivo gravou primeiro
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 && str_contains((string) $e->getMessage(), 'uq_pd_poll_device')) {
                return self::fail(409, 'Você já votou nesta enquete. Cada participante pode votar apenas uma vez.', 'already_voted');
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
