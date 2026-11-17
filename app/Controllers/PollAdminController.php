<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Participation;
use App\Models\Poll;
use App\Services\RankingService;

final class PollAdminController
{
    public function index(): void
    {
        require_admin();
        view('admin/polls', ['title' => 'Enquetes', 'polls' => Poll::listWithMeta(null, Poll::scopeOwner())]);
    }

    public function create(): void
    {
        require_admin();
        view('admin/poll_form', ['title' => 'Nova enquete', 'extraJs' => ['admin.js'], 'old' => [], 'errors' => []]);
    }

    /** Lê um campo de arquivo aninhado ($_FILES['games'][campo][i]['image']) como uma entrada simples. */
    private static function gameFile(int|string $i): array
    {
        $f = $_FILES['games'] ?? [];
        return [
            'error'    => $f['error'][$i]['image']    ?? UPLOAD_ERR_NO_FILE,
            'tmp_name' => $f['tmp_name'][$i]['image'] ?? '',
        ];
    }

    private static function splitMembers(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', $raw) ?: [] as $m) {
            $m = mb_substr(trim($m), 0, 120);
            if ($m !== '' && !in_array(mb_strtolower($m), array_map('mb_strtolower', $out), true)) {
                $out[] = $m;
            }
        }
        return array_slice($out, 0, 20);
    }

    /** Jogos enviados no formulário ($_POST['games']); ignora blocos totalmente vazios. */
    private static function collectGames(): array
    {
        $out = [];
        foreach ((array) ($_POST['games'] ?? []) as $i => $g) {
            if (!is_array($g)) {
                continue;
            }
            $game = [
                'id'          => (int) ($g['id'] ?? 0),   // 0 = jogo novo
                'name'        => trim((string) ($g['name'] ?? '')),
                'description' => trim((string) ($g['description'] ?? '')),
                'members'     => trim((string) ($g['members'] ?? '')),
                'image_url'   => trim((string) ($g['image_url'] ?? '')),
                'file'        => self::gameFile($i),
            ];
            $hasFile = $game['file']['error'] !== UPLOAD_ERR_NO_FILE;
            if ($game['id'] === 0 && $game['name'] . $game['description'] . $game['members'] . $game['image_url'] === '' && !$hasFile) {
                continue;
            }
            $out[] = $game;
        }
        return $out;
    }

    /** Alternativas enviadas: options[] (rótulos) e option_ids[] (mesma ordem; vazio = nova). */
    private static function collectOptions(): array
    {
        $ids = (array) ($_POST['option_ids'] ?? []);
        $out = [];
        foreach ((array) ($_POST['options'] ?? []) as $i => $o) {
            $label = mb_substr(trim((string) $o), 0, 190);
            if ($label !== '') {
                $out[] = ['id' => (int) ($ids[$i] ?? 0), 'label' => $label];
            }
        }
        return $out;
    }

    /**
     * Valida jogos/alternativas. $existing = [id => linha atual] dos itens que já pertencem à enquete
     * (vazio na criação); só ids desse conjunto são aceitos.
     */
    private static function itemErrors(string $type, array $old, array $existing = []): array
    {
        $errors = [];
        if ($type === 'options') {
            if ($old['question'] === '' || mb_strlen($old['question']) > 255) {
                $errors[] = 'Informe a pergunta (até 255 caracteres).';
            }
            if (count(array_unique(array_map('mb_strtolower', array_column($old['options'], 'label')))) < 2) {
                $errors[] = 'Informe ao menos 2 opções diferentes.';
            }
            if (count($old['options']) > 30) {
                $errors[] = 'No máximo 30 opções por enquete.';
            }
            foreach ($old['options'] as $o) {
                if ($o['id'] !== 0 && !isset($existing[$o['id']])) {
                    $errors[] = 'Há uma alternativa que não pertence a esta enquete. Recarregue a página e tente de novo.';
                    break;
                }
            }
            return $errors;
        }

        if (!$old['games']) {
            $errors[] = 'Cadastre ao menos 1 jogo.';
        }
        if (count($old['games']) > 30) {
            $errors[] = 'No máximo 30 jogos por catálogo.';
        }
        foreach ($old['games'] as $n => $g) {
            $label = 'Jogo ' . ($n + 1) . ($g['name'] !== '' ? ' (' . $g['name'] . ')' : '');
            if ($g['id'] !== 0 && !isset($existing[$g['id']])) {
                $errors[] = "$label: jogo não pertence a esta enquete. Recarregue a página e tente de novo.";
                continue;
            }
            if ($g['name'] === '' || mb_strlen($g['name']) > 160) {
                $errors[] = "$label: informe o nome do jogo (até 160 caracteres).";
            }
            if ($g['description'] === '') {
                $errors[] = "$label: informe a descrição.";
            }
            if (!self::splitMembers($g['members'])) {
                $errors[] = "$label: informe ao menos um integrante.";
            }
            // Jogo novo precisa de imagem; jogo existente mantém a que tem (ou segue sem capa)
            if ($g['id'] === 0 && $g['file']['error'] === UPLOAD_ERR_NO_FILE && $g['image_url'] === '') {
                $errors[] = "$label: envie uma imagem ou informe o link dela.";
            }
        }
        return $errors;
    }

    /** Grava as imagens novas (upload ou link) de $games[*]['cover']. Devolve [erros, caminhos gravados]. */
    private static function saveNewCovers(array &$games): array
    {
        $errors = [];
        $saved = [];
        foreach ($games as $n => &$g) {
            $g['cover'] = null; // null = não mudou (jogo existente) — jogo novo sempre recebe uma
            if ($g['file']['error'] === UPLOAD_ERR_NO_FILE && $g['image_url'] === '') {
                continue;
            }
            try {
                $g['cover'] = $g['file']['error'] !== UPLOAD_ERR_NO_FILE
                    ? save_cover_upload($g['file'])
                    : save_cover_from_url($g['image_url']);
                $saved[] = $g['cover'];
            } catch (\RuntimeException $ex) {
                $errors[] = 'Jogo ' . ($n + 1) . ' (' . $g['name'] . '): ' . $ex->getMessage();
            }
        }
        unset($g);
        return [$errors, $saved];
    }

    public function store(): void
    {
        require_admin();

        // Upload maior que post_max_size chega com $_POST vazio (e o CSRF seria o primeiro a falhar)
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            view('admin/poll_form', ['title' => 'Nova enquete', 'extraJs' => ['admin.js'], 'old' => [], 'errors' => [
                'As imagens enviadas passaram do limite do servidor. Use imagens de até 2 MB cada ou cole o link da imagem.',
            ]]);
            return;
        }
        csrf_check();

        $type = ($_POST['type'] ?? '') === 'options' ? 'options' : 'catalog';
        $old = [
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'type'        => $type,
            'question'    => trim((string) ($_POST['question'] ?? '')),
            'publish'     => !empty($_POST['publish']),
            'games'       => [],
            'options'     => [],
        ];

        if ($type === 'catalog') {
            $old['games'] = self::collectGames();
        } else {
            $old['options'] = self::collectOptions();
        }

        $errors = [];
        if ($old['title'] === '' || mb_strlen($old['title']) > 190) {
            $errors[] = 'Informe um título de até 190 caracteres.';
        }
        $errors = array_merge($errors, self::itemErrors($type, $old));

        // Imagens: só depois que o resto está válido (evita gravar arquivos à toa)
        $saved = [];
        if (!$errors && $type === 'catalog') {
            [$coverErrors, $saved] = self::saveNewCovers($old['games']);
            $errors = array_merge($errors, $coverErrors);
        }

        if ($errors) {
            array_map('delete_cover', $saved);
            http_response_code(422);
            view('admin/poll_form', ['title' => 'Nova enquete', 'extraJs' => ['admin.js'], 'old' => $old, 'errors' => $errors]);
            return;
        }

        $pdo = Database::pdo();
        // Link público exclusivo e imprevisível: 128 bits aleatórios (não deriva do título e não pode ser adivinhado)
        $chk = $pdo->prepare('SELECT 1 FROM polls WHERE slug = ?');
        do {
            $slug = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
            $chk->execute([$slug]);
        } while ($chk->fetchColumn());

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO polls (owner_id, title, slug, description, type, question, status, results_public) VALUES (?, ?, ?, ?, ?, ?, ?, 1)')
                ->execute([
                    admin_id(), $old['title'], $slug, $old['description'] ?: null, $type,
                    $type === 'options' ? $old['question'] : null,
                    $old['publish'] ? 'published' : 'draft',
                ]);
            $pollId = (int) $pdo->lastInsertId();

            if ($type === 'catalog') {
                $insGame = $pdo->prepare('INSERT INTO games (poll_id, name, description, cover_path, sort_order) VALUES (?, ?, ?, ?, ?)');
                $insMember = $pdo->prepare('INSERT INTO game_members (game_id, name) VALUES (?, ?)');
                foreach ($old['games'] as $i => $g) {
                    $insGame->execute([$pollId, $g['name'], $g['description'], $g['cover'], $i]);
                    $gameId = (int) $pdo->lastInsertId();
                    foreach (self::splitMembers($g['members']) as $m) {
                        $insMember->execute([$gameId, $m]);
                    }
                }
            } else {
                $insOpt = $pdo->prepare('INSERT INTO poll_options (poll_id, label, sort_order) VALUES (?, ?, ?)');
                foreach ($old['options'] as $i => $o) {
                    $insOpt->execute([$pollId, $o['label'], $i]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            array_map('delete_cover', $saved);
            throw $e;
        }

        flash('success', ($type === 'catalog' ? 'Catálogo criado' : 'Enquete criada') . ($old['publish'] ? ' e publicado.' : ' como rascunho.') . ' Link e QR Code prontos para compartilhar.');
        redirect('/admin/enquetes/' . $pollId . '/compartilhar');
    }

    /** GET /admin/enquetes/{id}/compartilhar — link público, QR Code, copiar, compartilhar e baixar. */
    public function share(string $id): void
    {
        require_admin();
        if ($poll = self::pollOr404($id)) {
            view('admin/poll_share', [
                'title'   => 'Compartilhar enquete',
                'poll'    => $poll,
                'link'    => absolute_url('/e/' . $poll['slug']),
                'extraJs' => ['qr.js', 'share.js'],
            ]);
        }
    }

    /** POST /admin/enquetes/{id}/publicar — publica um rascunho ou reabre uma enquete encerrada. */
    public function publish(string $id): void
    {
        // Publicar leva direto ao próximo passo natural: compartilhar o link
        $this->changeStatus($id, 'published', 'Enquete aberta para votação. Compartilhe o link com os participantes.', '/compartilhar');
    }

    /** POST /admin/enquetes/{id}/encerrar — a página continua acessível, mas novos votos são recusados. */
    public function close(string $id): void
    {
        $this->changeStatus($id, 'closed', 'Enquete encerrada.');
    }

    private function changeStatus(string $id, string $new, string $message, string $then = ''): void
    {
        require_admin();
        csrf_check();

        $poll = self::pollOr404($id);
        if (!$poll) {
            return;
        }
        Database::pdo()->prepare('UPDATE polls SET status = ? WHERE id = ?')->execute([$new, $poll['id']]);
        flash('success', $message);
        redirect($then !== '' ? '/admin/enquetes/' . (int) $poll['id'] . $then : '/admin/enquetes');
    }

    /** Dados da view de edição: itens atuais (com nº de votos) ou, após erro, o que foi digitado. */
    private function renderEdit(array $poll, array $errors, ?array $old = null): void
    {
        $votes = Poll::voteCounts($poll);
        $items = Poll::items($poll);
        foreach ($items as &$it) {
            $it['votes'] = $votes[(int) $it['id']] ?? 0;
            if ($poll['type'] === 'catalog') {
                $it['members'] = implode(', ', $it['members']);
            } else {
                $it['label'] = $it['name'];
            }
        }
        unset($it);

        if ($old) {
            // Reaparece o que foi digitado; a capa atual e o nº de votos continuam vindo do banco.
            $byId = array_column($items, null, 'id');
            if ($poll['type'] === 'catalog') {
                foreach ($old['games'] as &$g) {
                    $g['cover_path'] = $byId[$g['id']]['cover_path'] ?? null;
                    $g['votes']      = $byId[$g['id']]['votes'] ?? 0;
                }
                unset($g);
                $items = $old['games'];
            } else {
                foreach ($old['options'] as &$o) {
                    $o['votes'] = $byId[$o['id']]['votes'] ?? 0;
                }
                unset($o);
                $items = $old['options'];
            }
        }

        view('admin/poll_edit', [
            'title' => 'Editar enquete', 'extraJs' => ['admin.js'],
            'poll' => $poll, 'items' => $items, 'errors' => $errors,
            'participations' => Participation::countForPoll(Database::pdo(), (int) $poll['id']),
        ]);
    }

    private static function pollOr404(string $id): ?array
    {
        $poll = Poll::find((int) $id);
        // Professor que não é o dono recebe o mesmo 404 de uma enquete inexistente
        if (!$poll || !Poll::canManage($poll)) {
            http_response_code(404);
            view('public/404', ['title' => 'Enquete não encontrada']);
            return null;
        }
        return $poll;
    }

    /** GET /admin/enquetes/{id}/editar — dados gerais e também os jogos/alternativas. */
    public function edit(string $id): void
    {
        require_admin();
        if ($poll = self::pollOr404($id)) {
            $this->renderEdit($poll, []);
        }
    }

    /**
     * POST /admin/enquetes/{id}/editar
     * Itens com id são atualizados; sem id, criados; os que sumiram do formulário são removidos.
     * Item que já tem votos não é apagado (os votos ficam guardados): só sai da votação e dos resultados.
     */
    public function update(string $id): void
    {
        require_admin();

        $poll = self::pollOr404($id);
        if (!$poll) {
            return;
        }
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            $this->renderEdit($poll, ['As imagens enviadas passaram do limite do servidor. Use imagens de até 2 MB cada ou cole o link da imagem.']);
            return;
        }
        csrf_check();

        $type = $poll['type'];
        $old = [
            'title'          => trim((string) ($_POST['title'] ?? '')),
            'description'    => trim((string) ($_POST['description'] ?? '')),
            'question'       => trim((string) ($_POST['question'] ?? '')),
            'results_public' => !empty($_POST['results_public']) ? 1 : 0,
            'games'          => $type === 'catalog' ? self::collectGames() : [],
            'options'        => $type === 'options' ? self::collectOptions() : [],
        ];
        // O formulário volta com o que foi digitado
        $form = array_merge($poll, ['title' => $old['title'], 'description' => $old['description'], 'question' => $old['question'], 'results_public' => $old['results_public']]);

        $existing = array_column(Poll::items($poll), null, 'id');
        $errors = [];
        if ($old['title'] === '' || mb_strlen($old['title']) > 190) {
            $errors[] = 'Informe um título de até 190 caracteres.';
        }
        $errors = array_merge($errors, self::itemErrors($type, $old, $existing));

        // Um mesmo item não pode aparecer duas vezes
        $submitted = array_filter(array_column($type === 'catalog' ? $old['games'] : $old['options'], 'id'));
        if (count($submitted) !== count(array_unique($submitted))) {
            $errors[] = 'Há itens repetidos no formulário. Recarregue a página e tente de novo.';
        }

        $saved = [];
        if (!$errors && $type === 'catalog') {
            [$coverErrors, $saved] = self::saveNewCovers($old['games']);
            $errors = array_merge($errors, $coverErrors);
        }
        if ($errors) {
            array_map('delete_cover', $saved);
            http_response_code(422);
            $this->renderEdit($form, $errors, $old);
            return;
        }

        $pdo = Database::pdo();
        $oldCovers = []; // capas que deixam de ser usadas: apagadas só depois do commit
        $pdo->beginTransaction();
        try {
            // Serializa com os votos (que travam a linha em modo compartilhado) e com outras edições
            $pdo->prepare('SELECT id FROM polls WHERE id = ? FOR UPDATE')->execute([$poll['id']]);

            // O slug (URL pública) não muda: links já distribuídos continuam valendo.
            $pdo->prepare('UPDATE polls SET title = ?, description = ?, question = ?, results_public = ? WHERE id = ?')
                ->execute([$old['title'], $old['description'] ?: null, $type === 'options' ? $old['question'] : null, $old['results_public'], $poll['id']]);

            $hasVotes = Poll::voteCounts($poll); // dentro da transação, já com a linha travada
            if ($type === 'catalog') {
                $keep = [];
                $insGame = $pdo->prepare('INSERT INTO games (poll_id, name, description, cover_path, sort_order) VALUES (?, ?, ?, ?, ?)');
                $updGame = $pdo->prepare('UPDATE games SET name = ?, description = ?, sort_order = ? WHERE id = ? AND poll_id = ?');
                $updCover = $pdo->prepare('UPDATE games SET cover_path = ? WHERE id = ? AND poll_id = ?');
                $delMembers = $pdo->prepare('DELETE FROM game_members WHERE game_id = ?');
                $insMember = $pdo->prepare('INSERT INTO game_members (game_id, name) VALUES (?, ?)');
                foreach ($old['games'] as $i => $g) {
                    if ($g['id'] === 0) {
                        $insGame->execute([$poll['id'], $g['name'], $g['description'], $g['cover'], $i]);
                        $gameId = (int) $pdo->lastInsertId();
                    } else {
                        $gameId = $g['id'];
                        $updGame->execute([$g['name'], $g['description'], $i, $gameId, $poll['id']]);
                        if ($g['cover'] !== null) {
                            $updCover->execute([$g['cover'], $gameId, $poll['id']]);
                            $oldCovers[] = $existing[$gameId]['cover_path'] ?? null;
                        }
                        $delMembers->execute([$gameId]);
                    }
                    foreach (self::splitMembers($g['members']) as $m) {
                        $insMember->execute([$gameId, $m]);
                    }
                    $keep[$gameId] = true;
                }
                foreach ($existing as $gid => $row) {
                    if (isset($keep[$gid])) {
                        continue;
                    }
                    if (!empty($hasVotes[$gid])) {
                        $pdo->prepare('UPDATE games SET active = 0 WHERE id = ?')->execute([$gid]); // preserva o histórico
                    } else {
                        $pdo->prepare('DELETE FROM game_members WHERE game_id = ?')->execute([$gid]);
                        $pdo->prepare('DELETE FROM games WHERE id = ?')->execute([$gid]);
                        $oldCovers[] = $row['cover_path'] ?? null;
                    }
                }
            } else {
                $keep = [];
                $insOpt = $pdo->prepare('INSERT INTO poll_options (poll_id, label, sort_order) VALUES (?, ?, ?)');
                $updOpt = $pdo->prepare('UPDATE poll_options SET label = ?, sort_order = ? WHERE id = ? AND poll_id = ?');
                foreach ($old['options'] as $i => $o) {
                    if ($o['id'] === 0) {
                        $insOpt->execute([$poll['id'], $o['label'], $i]);
                        $keep[(int) $pdo->lastInsertId()] = true;
                    } else {
                        $updOpt->execute([$o['label'], $i, $o['id'], $poll['id']]);
                        $keep[$o['id']] = true;
                    }
                }
                foreach ($existing as $oid => $row) {
                    if (isset($keep[$oid])) {
                        continue;
                    }
                    if (!empty($hasVotes[$oid])) {
                        $pdo->prepare('UPDATE poll_options SET active = 0 WHERE id = ?')->execute([$oid]);
                    } else {
                        $pdo->prepare('DELETE FROM poll_options WHERE id = ?')->execute([$oid]);
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            array_map('delete_cover', $saved);
            throw $e;
        }

        array_map('delete_cover', $oldCovers);
        flash('success', ($type === 'catalog' ? 'Catálogo atualizado.' : 'Enquete atualizada.'));
        redirect('/admin/enquetes');
    }

    /** "Dashboard" de uma enquete: ranking e médias por pergunta. */
    public function results(string $id): void
    {
        require_admin();

        $poll = self::pollOr404($id);
        if (!$poll) {
            return;
        }

        $hl = RankingService::highlights($poll);
        $rows = $hl['rows'];


        view('admin/poll_results', [
            'title'         => $poll['title'],
            'poll'          => $poll,
            'rows'          => $rows,
            'hl'            => $hl,
            'participations' => Participation::countForPoll(Database::pdo(), (int) $poll['id']),
            'risky'          => Participation::countRiskForPoll(Database::pdo(), (int) $poll['id']),
            'withCharts'     => true,
        ]);
    }
}
