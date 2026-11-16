<?php
declare(strict_types=1);

/**
 * Teste de INTEGRAÇÃO do voto único (usa o MariaDB de verdade; cria e remove a própria enquete).
 * Requer o banco com as migrações aplicadas (docker compose up). Uso:
 *   docker compose exec web php tests/duplicates.php
 * As tentativas simultâneas rodam em processos PHP separados (conexões separadas), disparados no mesmo instante.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\Device;
use App\Services\VotingService;

$GLOBALS['config']['app_key'] = $GLOBALS['config']['app_key'] ?: 'chave-de-teste-integracao';

// ---------- Modo worker: uma tentativa de voto, resultado em JSON na saída ----------
if (($argv[1] ?? '') === 'worker') {
    [, , $slug, $token, $optionId, $startAt] = $argv;
    $_COOKIE[Device::COOKIE] = $token === '-' ? '' : $token;
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    $device = Device::resolve();
    while (microtime(true) < (float) $startAt) {
        usleep(200); // todos os workers largam juntos
    }
    $r = VotingService::submit($slug, ['option' => $optionId], $device);
    echo json_encode(['ok' => $r['ok'], 'status' => $r['status'], 'code' => $r['code'] ?? null]);
    exit(0);
}

$passed = 0; $failed = 0;
function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  ok   $name\n"; } else { $failed++; echo "  FAIL $name\n"; }
}

$pdo = Database::pdo();
$slug = 'teste-' . bin2hex(random_bytes(6));
$pdo->prepare("INSERT INTO polls (title, slug, type, question, status, results_public) VALUES ('Teste voto único', ?, 'options', 'Qual?', 'published', 1)")->execute([$slug]);
$pollId = (int) $pdo->lastInsertId();
$opts = [];
foreach (['A', 'B', 'C'] as $i => $label) {
    $pdo->prepare('INSERT INTO poll_options (poll_id, label, sort_order) VALUES (?, ?, ?)')->execute([$pollId, $label, $i]);
    $opts[] = (int) $pdo->lastInsertId();
}
$count = static function (string $table) use ($pdo, $pollId): int {
    $sql = match ($table) {
        'participations' => 'SELECT COUNT(*) FROM participations WHERE poll_id = ?',
        'devices'        => 'SELECT COUNT(*) FROM participation_devices WHERE poll_id = ?',
        'votes'          => 'SELECT COUNT(*) FROM option_votes v JOIN poll_options o ON o.id = v.option_id WHERE o.poll_id = ?',
    };
    $q = $pdo->prepare($sql); $q->execute([$pollId]);
    return (int) $q->fetchColumn();
};
$as = static function (?string $cookie, ?string $header = null): array {
    $_COOKIE = $cookie ? [Device::COOKIE => $cookie] : [];
    unset($_SERVER['HTTP_X_DEVICE_TOKEN']);
    if ($header) { $_SERVER['HTTP_X_DEVICE_TOKEN'] = $header; }
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    return Device::resolve();
};

try {
    echo "Voto único — sequencial\n";
    $tokA = Device::mint();
    $r = VotingService::submit($slug, ['option' => (string) $opts[0]], $as($tokA));
    check('primeiro voto é aceito', $r['ok'] === true && $count('votes') === 1);
    $r = VotingService::submit($slug, ['option' => (string) $opts[1]], $as($tokA));
    check('segundo voto do mesmo dispositivo é recusado (409)', !$r['ok'] && $r['status'] === 409 && $r['code'] === 'already_voted');
    check('nada foi gravado na tentativa repetida', $count('participations') === 1 && $count('votes') === 1 && $count('devices') === 1);
    check('voto original não foi alterado', (int) $pdo->query("SELECT option_id FROM option_votes v JOIN poll_options o ON o.id = v.option_id WHERE o.poll_id = $pollId")->fetchColumn() === $opts[0]);

    $r = VotingService::submit($slug, ['option' => (string) $opts[2]], $as(null, $tokA));
    check('cookies apagados, mas cópia do navegador: continua bloqueado', !$r['ok'] && $r['code'] === 'already_voted');
    $r = VotingService::submit($slug, ['option' => (string) $opts[2]], $as($tokA, 'inventado.inventado'));
    check('cabeçalho forjado não libera nem atrapalha (cookie vale)', !$r['ok'] && $r['code'] === 'already_voted');
    $r = VotingService::submit($slug, ['option' => (string) $opts[2]], $as(Device::mint(), $tokA));
    check('cookie novo + cópia antiga: bloqueado', !$r['ok'] && $r['code'] === 'already_voted');
    check('GET/página: dispositivo que votou é reconhecido', VotingService::deviceHasVoted($pollId, $as($tokA)) === true);

    $tokB = Device::mint();
    $r = VotingService::submit($slug, ['option' => (string) $opts[1]], $as($tokB));
    check('outro dispositivo pode votar', $r['ok'] === true && $count('participations') === 2);

    $fp = '390|844|24|3|America/Sao_Paulo|pt-BR|iPhone|4|8|5|abc123';
    $r = VotingService::submit($slug, ['option' => (string) $opts[0], 'fp' => $fp], $as(Device::mint()));
    $r2 = VotingService::submit($slug, ['option' => (string) $opts[0], 'fp' => $fp], $as(Device::mint()));
    check('fingerprint + rede repetidos (identificador novo): vota, mas fica marcado', $r['ok'] && $r2['ok']
        && (int) $pdo->query("SELECT COUNT(*) FROM participations WHERE poll_id = $pollId AND risk = 1")->fetchColumn() === 1);

    echo "Validação antes de registrar\n";
    $before = $count('participations');
    $r = VotingService::submit($slug, ['option' => '999999'], $as(Device::mint()));
    check('alternativa inválida: recusa e não consome o voto', !$r['ok'] && $r['status'] === 422 && $count('participations') === $before);
    $pdo->prepare("UPDATE polls SET status = 'closed' WHERE id = ?")->execute([$pollId]);
    $r = VotingService::submit($slug, ['option' => (string) $opts[0]], $as(Device::mint()));
    check('enquete encerrada não aceita voto', !$r['ok'] && $r['status'] === 409 && $count('participations') === $before);
    $pdo->prepare("UPDATE polls SET status = 'published' WHERE id = ?")->execute([$pollId]);

    echo "Restrição do banco (UNIQUE)\n";
    $dup = false;
    try {
        $partId = (int) $pdo->query("SELECT MIN(id) FROM participations WHERE poll_id = $pollId")->fetchColumn();
        $h = Device::deviceHash($pollId, (string) Device::verify($tokA));
        $pdo->prepare('INSERT INTO participation_devices (poll_id, participation_id, device_hash) VALUES (?, ?, ?)')->execute([$pollId, $partId, $h]);
    } catch (PDOException $e) {
        $dup = (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
    check('o banco recusa o mesmo dispositivo duas vezes na mesma enquete', $dup);

    echo "Voto único — requisições simultâneas (processos separados)\n";
    $base = [$count('participations'), $count('votes'), $count('devices')];
    $tokC = Device::mint();
    $startAt = microtime(true) + 2.0;
    $procs = [];
    for ($i = 0; $i < 20; $i++) {
        $cmd = [PHP_BINARY, __FILE__, 'worker', $slug, $tokC, (string) $opts[$i % 3], (string) $startAt];
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $ok = 0; $dupes = 0; $other = [];
    foreach ($procs as [$p, $pipes]) {
        $out = trim((string) stream_get_contents($pipes[1]));
        stream_get_contents($pipes[2]);
        proc_close($p);
        $j = json_decode($out, true);
        if (($j['ok'] ?? false) === true) { $ok++; }
        elseif (($j['code'] ?? '') === 'already_voted') { $dupes++; }
        else { $other[] = $out; }
    }
    check('20 tentativas do MESMO dispositivo: exatamente 1 aceita', $ok === 1);
    check('as outras 19 responderam "já votou"', $dupes === 19 && !$other);
    if ($other) { echo '       respostas inesperadas: ' . implode(' | ', array_slice($other, 0, 3)) . "\n"; }
    check('só 1 participação, 1 voto e 1 dispositivo foram gravados',
        $count('participations') === $base[0] + 1 && $count('votes') === $base[1] + 1 && $count('devices') === $base[2] + 1);
} finally {
    // Limpeza: remove tudo o que o teste criou
    $pdo->exec("DELETE v FROM option_votes v JOIN poll_options o ON o.id = v.option_id WHERE o.poll_id = $pollId");
    $pdo->exec("DELETE FROM participation_devices WHERE poll_id = $pollId");
    $pdo->exec("DELETE FROM participations WHERE poll_id = $pollId");
    $pdo->exec("DELETE FROM poll_options WHERE poll_id = $pollId");
    $pdo->exec("DELETE FROM polls WHERE id = $pollId");
}

echo "\n$passed passaram, $failed falharam\n";
exit($failed > 0 ? 1 : 0);
