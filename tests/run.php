<?php
declare(strict_types=1);

/**
 * Testes unitários simples (sem framework e sem banco de dados).
 * Uso: php tests/run.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\VotingService;

$passed = 0;
$failed = 0;

function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) { $passed++; echo "  ok   $name\n"; }
    else { $failed++; echo "  FAIL $name\n"; }
}

echo "VotingService::sanitize — catálogo\n";
$ids = [10, 11, 12];

$r = VotingService::sanitize('catalog', $ids, ['ratings' => [10 => ['q_time' => '5', 'q_fun' => '4', 'q_replay' => '3']]]);
check('aceita um jogo com as 3 notas', !$r['errors'] && $r['data']['ratings'][10] === ['q_time' => 5, 'q_fun' => 4, 'q_replay' => 3]);

$r = VotingService::sanitize('catalog', $ids, ['ratings' => [
    10 => ['q_time' => 1, 'q_fun' => 1, 'q_replay' => 1],
    11 => ['q_time' => '', 'q_fun' => '', 'q_replay' => ''],
]]);
check('ignora jogo não avaliado', !$r['errors'] && array_keys($r['data']['ratings']) === [10]);

$r = VotingService::sanitize('catalog', $ids, ['ratings' => [10 => ['q_time' => 5, 'q_fun' => 4]]]);
check('recusa jogo com notas incompletas', (bool) $r['errors']);

foreach (['0', '6', '-1', '3.5', 'abc'] as $bad) {
    $r = VotingService::sanitize('catalog', $ids, ['ratings' => [10 => ['q_time' => $bad, 'q_fun' => 3, 'q_replay' => 3]]]);
    check("recusa nota inválida '$bad'", (bool) $r['errors']);
}

$r = VotingService::sanitize('catalog', $ids, ['ratings' => [99 => ['q_time' => 5, 'q_fun' => 5, 'q_replay' => 5]]]);
check('recusa jogo de outra enquete', (bool) $r['errors']);

check('recusa envio vazio', (bool) VotingService::sanitize('catalog', $ids, [])['errors']);
check('recusa ratings que não é array', (bool) VotingService::sanitize('catalog', $ids, ['ratings' => 'x'])['errors']);

echo "VotingService::sanitize — opções\n";
$r = VotingService::sanitize('options', [1, 2, 3], ['option' => '2']);
check('aceita alternativa válida', !$r['errors'] && $r['data']['option'] === 2);
check('recusa alternativa de outra enquete', (bool) VotingService::sanitize('options', [1, 2, 3], ['option' => '9'])['errors']);
check('recusa sem alternativa', (bool) VotingService::sanitize('options', [1, 2, 3], [])['errors']);
check('recusa alternativa não numérica', (bool) VotingService::sanitize('options', [1, 2, 3], ['option' => '1; DROP'])['errors']);

echo "Helpers\n";
check('slugify remove acentos', slugify('Feira de Tecnologia — Avaliação 2026!') === 'feira-de-tecnologia-avaliacao-2026');
check('slugify nunca fica vazio', slugify('!!!') === 'enquete');
check('even_grid 5 itens → 3+2', even_grid_class(0, 5) === 's4' && even_grid_class(3, 5) === 's6');
check('even_grid 8 itens → 4+4', even_grid_class(0, 8) === 's3' && even_grid_class(7, 8) === 's3');
check('fmt_score formata com vírgula', fmt_score(4.3) === '4,30' && fmt_score(null) === '—');
check('e() escapa HTML', e('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');

echo "Cadastro de professores — e-mail institucional\n";
$ok = fn(string $e) => \App\Services\TeacherRegistration::isInstitutionalEmail($e);
check('aceita nome@cps.sp.gov.br', $ok('maria.silva@cps.sp.gov.br'));
check('aceita maiúsculas e espaços', $ok('  Maria@CPS.SP.GOV.BR '));
check('recusa gmail', !$ok('maria@gmail.com'));
check('recusa subdomínio', !$ok('maria@x.cps.sp.gov.br'));
check('recusa domínio com sufixo', !$ok('maria@cps.sp.gov.br.evil.com'));
check('recusa domínio parecido', !$ok('maria@evilcps.sp.gov.br'));
check('recusa dois @', !$ok('a@evil.com@cps.sp.gov.br'));
check('recusa vazio', !$ok(''));
check('normalizeCode só dígitos', \App\Services\TeacherRegistration::normalizeCode('123 456') === '123456');

echo "Poll::canManage / scopeOwner — professor x administrador\n";
$mine = ['owner_id' => 7]; $other = ['owner_id' => 8]; $orphan = ['owner_id' => null];
$_SESSION = ['admin_id' => 7, 'admin_role' => 'teacher'];
check('professor gerencia a própria enquete', \App\Models\Poll::canManage($mine));
check('professor não gerencia a de outro', !\App\Models\Poll::canManage($other));
check('professor não gerencia enquete sem dono', !\App\Models\Poll::canManage($orphan));
check('professor é filtrado pelo próprio id', \App\Models\Poll::scopeOwner() === 7);
$_SESSION = ['admin_id' => 1, 'admin_role' => 'admin'];
check('admin gerencia qualquer enquete', \App\Models\Poll::canManage($other) && \App\Models\Poll::canManage($orphan));
check('admin não é filtrado', \App\Models\Poll::scopeOwner() === null);
$_SESSION = ['admin_id' => 7];
check('sessão sem papel vale como professor', !is_super_admin());

echo "Device — identificador de dispositivo (assinado pelo servidor)\n";
$GLOBALS['config']['app_key'] = 'chave-de-teste-unitario'; // evita criar storage/app.key nos testes
$reset = static function (): void { $_COOKIE = []; unset($_SERVER['HTTP_X_DEVICE_TOKEN'], $_SERVER['REMOTE_ADDR']); };
$reset();
$tok = \App\Services\Device::mint();
$id  = \App\Services\Device::verify($tok);
check('token emitido pelo servidor é autêntico', is_string($id) && strlen($id) === 22);
check('dois tokens emitidos são diferentes', \App\Services\Device::mint() !== $tok);
$tampered = substr($tok, 0, -1) . (substr($tok, -1) === 'A' ? 'B' : 'A');
check('assinatura alterada é recusada', \App\Services\Device::verify($tampered) === null);
check('id inventado pelo cliente é recusado', \App\Services\Device::verify('AAAAAAAAAAAAAAAAAAAAAA.AAAAAAAAAAAAAAAAAAAAAA') === null);
check('id trocado mantendo a assinatura é recusado', \App\Services\Device::verify('BBBBBBBBBBBBBBBBBBBBBB.' . explode('.', $tok)[1]) === null);
check('lixo, vazio, array e null são recusados', \App\Services\Device::verify('') === null && \App\Services\Device::verify('x') === null
    && \App\Services\Device::verify(['a']) === null && \App\Services\Device::verify(null) === null);

$r = \App\Services\Device::resolve();
check('sem cookie: gera identidade nova', $r['fresh'] === true && count($r['ids']) === 1 && \App\Services\Device::verify($r['token']) === $r['ids'][0]);
$_COOKIE[\App\Services\Device::COOKIE] = $tok;
$r = \App\Services\Device::resolve();
check('cookie válido: mantém a identidade', !$r['fresh'] && $r['ids'] === [$id] && $r['token'] === $tok);
$_COOKIE[\App\Services\Device::COOKIE] = $tampered;
$r = \App\Services\Device::resolve();
check('cookie adulterado é ignorado (vira identidade nova)', $r['fresh'] && $r['ids'] !== [$id]);
$reset();
$_SERVER['HTTP_X_DEVICE_TOKEN'] = $tok;
$r = \App\Services\Device::resolve();
check('cookie apagado: a cópia do navegador restaura a identidade', !$r['fresh'] && $r['ids'] === [$id] && $r['token'] === $tok);
$tok2 = \App\Services\Device::mint(); $id2 = \App\Services\Device::verify($tok2);
$_COOKIE[\App\Services\Device::COOKIE] = $tok2;
$r = \App\Services\Device::resolve();
check('cookie novo + cópia antiga: considera as duas e prioriza a antiga', $r['ids'] === [$id, $id2] && $r['token'] === $tok);
$_SERVER['HTTP_X_DEVICE_TOKEN'] = 'inventado.inventado';
$r = \App\Services\Device::resolve();
check('cópia inválida é ignorada; vale o cookie', $r['ids'] === [$id2] && !$r['fresh']);
$reset();

$h = \App\Services\Device::deviceHash(1, $id);
check('hash do dispositivo: 64 hex, estável e por enquete', preg_match('/^[0-9a-f]{64}$/', $h) === 1
    && $h === \App\Services\Device::deviceHash(1, $id) && $h !== \App\Services\Device::deviceHash(2, $id));
check('hash do dispositivo não contém o id em claro', !str_contains($h, $id));
check('fingerprint válido vira hash; lixo é recusado', \App\Services\Device::fingerprintHash(1, '390|844|24|3|America/Sao_Paulo|pt-BR|iPhone|x1') !== null
    && \App\Services\Device::fingerprintHash(1, '<script>alert(1)</script>') === null
    && \App\Services\Device::fingerprintHash(1, 'curto') === null && \App\Services\Device::fingerprintHash(1, null) === null
    && \App\Services\Device::fingerprintHash(1, str_repeat('a', 301)) === null);
check('sem IP, o hash de rede é nulo', \App\Services\Device::ipHash(1) === null);
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$ip4 = \App\Services\Device::ipHash(1);
$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
check('IPv4 diferentes geram hashes diferentes', $ip4 !== null && $ip4 !== \App\Services\Device::ipHash(1));
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa:bbbb:cccc:dddd'; $a = \App\Services\Device::ipHash(1);
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:1111:2222:3333:4444'; $b = \App\Services\Device::ipHash(1);
check('IPv6 do mesmo /64 gera o mesmo hash', $a !== null && $a === $b);
$reset();

echo "\n$passed passaram, $failed falharam\n";
exit($failed > 0 ? 1 : 0);
