<?php
// Executado no start do container (e manualmente: php database/seed.php).
// Idempotente: só cria o admin se não houver nenhum e só cria a demonstração se não houver enquetes.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Somente via linha de comando.');
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

Database::waitUntilReady();
$pdo = Database::pdo();

// ---------- Migrações incrementais (002+): idempotentes, rodam a cada subida ----------
foreach (glob(__DIR__ . '/migrations/*.sql') ?: [] as $file) {
    if (basename($file) === '001_schema.sql') {
        continue; // o schema inicial é aplicado pelo MariaDB na criação do banco
    }
    $sql = preg_replace('~--[^\r\n]*~', '', (string) file_get_contents($file));
    $sql = preg_replace('/^\s*USE\s+[^;]+;/mi', '', (string) $sql);
    foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $statement) {
        $pdo->exec($statement);
    }
}

// ---------- Pasta de imagens enviadas ----------
$covers = BASE_PATH . '/public/uploads/covers';
if (!is_dir($covers)) {
    @mkdir($covers, 0777, true);
}
@chmod(dirname($covers), 0777);
@chmod($covers, 0777);

// ---------- Pasta de armazenamento (log de e-mails quando MAIL_DRIVER=log) ----------
$storage = BASE_PATH . '/storage';
if (!is_dir($storage)) {
    @mkdir($storage, 0777, true);
}
@chmod($storage, 0777);

// ---------- Admin ----------
if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)')->execute([
        'Administrador',
        config('admin.email'),
        password_hash((string) config('admin.password'), PASSWORD_DEFAULT),
    ]);
    echo "Admin criado: " . config('admin.email') . "\n";
}

// ---------- Dono das enquetes sem dono (anteriores à migração 003): o primeiro administrador ----------
$firstAdmin = (int) $pdo->query("SELECT MIN(id) FROM admins WHERE role = 'admin'")->fetchColumn();
if ($firstAdmin > 0) {
    $pdo->prepare('UPDATE polls SET owner_id = ? WHERE owner_id IS NULL')->execute([$firstAdmin]);
}

// ---------- Demonstração ----------
if (!config('seed_demo') || (int) $pdo->query('SELECT COUNT(*) FROM polls')->fetchColumn() > 0) {
    exit(0);
}

mt_srand(2026); // dados repetíveis

/** Integrantes fictícios por jogo. */
$members = [
    'Cyber Vila' => ['Ana Souza', 'Bruno Lima', 'Carla Dias'],
    'Rumba do Cafuzal' => ['Diego Alves', 'Elisa Prado'],
    'Pixel Quest' => ['Felipe Nunes', 'Gabi Torres', 'Heitor Melo'],
    'Neon Drift' => ['Igor Santana', 'Julia Ramos'],
    'Forte Storm' => ['Kaique Matos', 'Larissa Cruz'],
    'Mangue Run' => ['Marcos Vieira', 'Natália Reis', 'Otávio Leal'],
    'Sabiá Sprint' => ['Paula Barros', 'Rafael Costa'],
    'Código Zero' => ['Sofia Mendes', 'Tiago Ferraz'],
];

/** Enquete de opções de demonstração: [rótulo, nº de votos]. */
$optionsDemo = [
    'title' => 'Próximo gênero a desenvolver', 'slug' => 'proximo-genero-a-desenvolver',
    'question' => 'Qual gênero de jogo a Fatec deve explorar no próximo semestre?',
    'status' => 'published', 'days_ago' => [0, 4],
    'options' => [['Plataforma', 18], ['Puzzle', 31], ['Corrida', 24], ['Aventura / RPG', 27]],
];

/** [nome, descrição, nº de avaliações, nota média alvo] */
$demo = [
    [
        'title' => 'Melhor jogo do semestre passado', 'slug' => 'melhor-jogo-semestre-passado',
        'status' => 'closed', 'days_ago' => [20, 45],
        'games' => [
            ['Cyber Vila', 'Aventura cyberpunk em uma vila futurista.', 40, 4.1],
            ['Rumba do Cafuzal', 'Ritmo e cultura em um jogo de dança.', 28, 3.8],
            ['Pixel Quest', 'Plataforma retrô com chefes memoráveis.', 19, 3.5],
        ],
    ],
    [
        'title' => 'Game Jam ADS — 3º semestre', 'slug' => 'game-jam-ads-3-semestre',
        'status' => 'published', 'days_ago' => [2, 6],
        'games' => [
            ['Neon Drift', 'Corrida em pistas de neon.', 24, 4.4],
            ['Pixel Quest', 'Plataforma retrô com chefes memoráveis.', 15, 3.9],
            ['Forte Storm', 'Defenda o forte de ondas de tempestade.', 14, 3.7],
            ['Mangue Run', 'Corrida infinita pelo manguezal.', 12, 3.6],
        ],
    ],
    [
        'title' => 'Feira de Jogos Fatec 2026', 'slug' => 'feira-de-jogos-fatec-2026',
        'status' => 'published', 'days_ago' => [0, 3],
        'games' => [
            ['Neon Drift', 'Corrida em pistas de neon.', 56, 4.6],
            ['Mangue Run', 'Corrida infinita pelo manguezal.', 36, 4.3],
            ['Pixel Quest', 'Plataforma retrô com chefes memoráveis.', 27, 4.0],
            ['Cyber Vila', 'Aventura cyberpunk em uma vila futurista.', 22, 3.6],
            ['Sabiá Sprint', 'Corrida de obstáculos pela cidade.', 14, 3.3],
            ['Código Zero', 'Puzzle de lógica e programação.', 10, 3.0],
        ],
    ],
];

$score = static fn(float $target): int => max(1, min(5, (int) round($target + (mt_rand(0, 100) / 100 - 0.5) * 2.4)));

$pdo->beginTransaction();
foreach ($demo as $poll) {
    $created = date('Y-m-d H:i:s', strtotime('-' . ($poll['days_ago'][1] + 1) . ' days'));
    $pdo->prepare('INSERT INTO polls (title, slug, description, type, status, results_public, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)')
        ->execute([$poll['title'], $poll['slug'], 'Avalie os jogos criados pelos estudantes.', 'catalog', $poll['status'], $created]);
    $pollId = (int) $pdo->lastInsertId();

    $gameIds = [];
    foreach ($poll['games'] as $i => $g) {
        $pdo->prepare('INSERT INTO games (poll_id, name, description, sort_order) VALUES (?, ?, ?, ?)')->execute([$pollId, $g[0], $g[1], $i]);
        $gameIds[$i] = (int) $pdo->lastInsertId();
        foreach ($members[$g[0]] ?? [] as $m) {
            $pdo->prepare('INSERT INTO game_members (game_id, name) VALUES (?, ?)')->execute([$gameIds[$i], $m]);
        }
    }

    // Participação i avalia todo jogo cujo total de votos > i
    $participants = max(array_column($poll['games'], 2));
    for ($i = 0; $i < $participants; $i++) {
        $daysAgo = mt_rand($poll['days_ago'][0], $poll['days_ago'][1]);
        $when = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days -" . mt_rand(0, 600) . ' minutes'));
        $pdo->prepare('INSERT INTO participations (poll_id, created_at) VALUES (?, ?)')->execute([$pollId, $when]);
        $partId = (int) $pdo->lastInsertId();
        foreach ($poll['games'] as $gi => $g) {
            if ($g[2] > $i) {
                $pdo->prepare('INSERT INTO game_ratings (participation_id, game_id, q_time, q_fun, q_replay) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$partId, $gameIds[$gi], $score($g[3]), $score($g[3] + 0.2), $score($g[3] - 0.1)]);
            }
        }
    }
}

// Enquete de opções: cada participação vota em uma alternativa
$o = $optionsDemo;
$pdo->prepare('INSERT INTO polls (title, slug, description, type, question, status, results_public, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
    ->execute([$o['title'], $o['slug'], 'Ajude a decidir o foco dos próximos projetos.', 'options', $o['question'], $o['status'], date('Y-m-d H:i:s', strtotime('-5 days'))]);
$optPollId = (int) $pdo->lastInsertId();
foreach ($o['options'] as $i => [$label, $count]) {
    $pdo->prepare('INSERT INTO poll_options (poll_id, label, sort_order) VALUES (?, ?, ?)')->execute([$optPollId, $label, $i]);
    $optId = (int) $pdo->lastInsertId();
    for ($n = 0; $n < $count; $n++) {
        $when = date('Y-m-d H:i:s', strtotime('-' . mt_rand($o['days_ago'][0], $o['days_ago'][1]) . ' days -' . mt_rand(0, 600) . ' minutes'));
        $pdo->prepare('INSERT INTO participations (poll_id, created_at) VALUES (?, ?)')->execute([$optPollId, $when]);
        $pdo->prepare('INSERT INTO option_votes (participation_id, option_id) VALUES (?, ?)')->execute([(int) $pdo->lastInsertId(), $optId]);
    }
}
$pdo->commit();
if ($firstAdmin > 0) {
    $pdo->prepare('UPDATE polls SET owner_id = ? WHERE owner_id IS NULL')->execute([$firstAdmin]);
}
echo "Dados de demonstração criados.\n";
