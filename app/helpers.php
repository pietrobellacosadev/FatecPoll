<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escapa HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho base quando o app roda em subpasta (ex.: /fatecpoll). */
function url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $base . '/' . ltrim($path, '/');
}

/** URL completa (com http(s)://domínio), para links que serão copiados e compartilhados. Defina APP_URL em produção. */
function absolute_url(string $path): string
{
    $base = (string) config('app_url', '');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', $host);
    }
    return $base . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent  = $_POST['_csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    // Token vazio nunca é válido (hash_equals('', '') seria verdadeiro numa sessão que ainda não gerou token)
    return is_string($sent) && is_string($known) && $known !== '' && hash_equals($known, $sent);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        view('public/419', ['title' => 'Sessão expirada']);
        exit;
    }
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Renderiza uma view dentro do layout. */
function view(string $template, array $data = [], string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    $content = ob_get_clean();
    require BASE_PATH . '/views/' . $layout . '.php';
}

/** Conta do painel logada, lida do banco uma vez por requisição (null = sem login, conta removida ou inativada). */
function admin_account(): ?array
{
    static $cache = [];
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $st = \App\Core\Database::pdo()->prepare('SELECT id, name, role, active FROM admins WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $cache[$id] = ($row && (int) $row['active'] === 1) ? $row : null;
    }
    return $cache[$id];
}

/** Há alguém logado no painel E a conta continua ativa. Revoga a sessão se foi inativada ou removida. */
function is_admin(): bool
{
    $acc = admin_account();
    if ($acc) {
        $_SESSION['admin_name'] = $acc['name'];
        $_SESSION['admin_role'] = $acc['role'] === 'teacher' ? 'teacher' : 'admin'; // rebaixar o papel vale na hora
        return true;
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
    return false;
}

/** Papel de quem está logado no painel: 'admin' (vê tudo) ou 'teacher' (só as próprias enquetes). */
function admin_role(): string
{
    return ($_SESSION['admin_role'] ?? 'teacher') === 'admin' ? 'admin' : 'teacher';
}

/** Administrador global. Lê só a sessão (sem consultar o banco): use depois de require_admin()/is_admin(), que já validam a conta. */
function is_super_admin(): bool
{
    return !empty($_SESSION['admin_id']) && admin_role() === 'admin';
}

function admin_id(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function require_admin(): void
{
    $had = !empty($_SESSION['admin_id']);
    if (!is_admin()) {
        if ($had) {
            flash('error', 'Seu acesso ao painel foi encerrado. Entre novamente ou fale com a coordenação.');
        }
        redirect('/admin/login');
    }
}

/** Só o administrador global; professor logado recebe 403. */
function require_super_admin(): void
{
    require_admin();
    if (!is_super_admin()) {
        http_response_code(403);
        view('public/403', ['title' => 'Acesso restrito']);
        exit;
    }
}

/** Área do painel/cadastro: carrega os scripts de confirmação, trava de duplo clique e menus. */
function in_admin_area(): bool
{
    $p = current_path();
    return str_starts_with($p, '/admin') || str_starts_with($p, '/cadastro');
}

/** Caminho atual da requisição, sem a subpasta (para marcar o item ativo do menu). */
function current_path(): string
{
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    return '/' . trim($uri, '/');
}

function nav_active(string $path, bool $exact = false): string
{
    $cur = current_path();
    $on = $exact ? $cur === $path : ($cur === $path || str_starts_with($cur, rtrim($path, '/') . '/'));
    return $on ? ' aria-current="page"' : '';
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function slugify(string $text): string
{
    $text = strtr(mb_strtolower($text, 'UTF-8'), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
    $text = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return $text !== '' ? $text : 'enquete';
}

/** Rótulos curtos das 3 perguntas padrão (documentação, seção de avaliação de jogos). */
function rating_labels(): array
{
    return ['q_time' => 'Tempo de jogo', 'q_fun' => 'Diversão', 'q_replay' => 'Jogaria de novo'];
}

/** Ícones de traço (24x24), herdam a cor do texto. */
function icon(string $name, int $size = 20): string
{
    static $paths = [
        'thumbs-up' => '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
        'pointer'   => '<path d="M22 14a8 8 0 0 1-8 8"/><path d="M18 11v-1a2 2 0 0 0-2-2a2 2 0 0 0-2 2"/><path d="M14 10V9a2 2 0 0 0-2-2a2 2 0 0 0-2 2v1"/><path d="M10 9.5V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v10"/><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>',
        'layers'    => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'trophy'    => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'bars'      => '<path d="M5 21v-6"/><path d="M12 21V9"/><path d="M19 21V4"/>',
        'plus'      => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'arrow'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'x'         => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'list'      => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'gamepad'   => '<path d="M6 12h4"/><path d="M8 10v4"/><path d="M15 13h.01"/><path d="M18 11h.01"/><rect width="20" height="12" x="2" y="6" rx="2"/>',
        'image'     => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Capa gerada (gradiente + traço em SVG), estável por nome: o mesmo jogo tem sempre a mesma capa.
 * Troque por imagem enviada (cover_path) quando o upload for implementado.
 */
function cover(string $name, string $extra = '', ?string $path = null): string
{
    if ($path) {
        return '<span class="cover cover--img' . ($extra ? ' ' . e($extra) : '') . '">'
             . '<img src="' . e(url($path)) . '" alt="' . e($name) . '" loading="lazy"></span>';
    }
    static $shapes = [
        '<path d="M8 56C30 22 46 22 62 44S96 66 112 28"/>',
        '<path d="M8 62 36 38l20 14 30-32 26 24"/>',
        '<path d="M10 62h24V42h18v14h18V30h18v18h22"/>',
        '<path d="M6 64h40L56 18l10 46h50"/>',
        '<path d="M6 58q12-32 24 0t24 0 24 0 24 0"/>',
    ];
    $h = crc32($name);
    $tone = $h % 6;
    $shape = $shapes[intdiv($h, 6) % count($shapes)];
    return '<span class="cover cover--c' . $tone . ($extra ? ' ' . e($extra) : '') . '" aria-hidden="true">'
         . '<svg viewBox="0 0 120 80" preserveAspectRatio="xMidYMid slice"><circle cx="102" cy="18" r="14" class="cover__dot"/>'
         . '<circle cx="14" cy="72" r="24" class="cover__shade"/><g class="cover__line">' . $shape . '</g></svg>'
         . '<span class="cover__name">' . e($name) . '</span></span>';
}

/** Mosaico de até 4 capas (miniatura da enquete). Aceita nomes (string) ou ['name' => ..., 'cover' => caminho|null]. */
function collage(array $names): string
{
    $names = array_slice($names, 0, 4);
    if (!$names) {
        return '<span class="collage collage--empty" aria-hidden="true"></span>';
    }
    $html = '';
    foreach ($names as $n) {
        $html .= is_array($n) ? cover($n['name'], '', $n['cover'] ?? null) : cover($n);
    }
    return '<span class="collage collage--' . count($names) . '" aria-hidden="true">' . $html . '</span>';
}


/** Textos completos das 3 perguntas padrão do catálogo de jogos. */
function rating_questions(): array
{
    return ['q_time' => 'O tempo de jogo foi adequado?', 'q_fun' => 'Foi divertido?', 'q_replay' => 'Jogaria novamente?'];
}

/** Nota com duas casas e vírgula (4,32); null vira traço. */
function fmt_score(?float $n): string
{
    return $n === null ? '—' : number_format($n, 2, ',', '');
}

// ---------- Imagens dos jogos ----------

const COVER_MAX_BYTES = 2 * 1024 * 1024; // 2 MB: respeita o upload_max_filesize padrão do PHP

function covers_dir(): string
{
    return BASE_PATH . '/public/uploads/covers';
}

/** Valida o conteúdo como imagem raster (jpg/png/webp/gif) e grava com nome aleatório. Retorna o caminho relativo a public/. */
function save_cover_bytes(string $bytes): string
{
    if ($bytes === '' || strlen($bytes) > COVER_MAX_BYTES) {
        throw new RuntimeException('a imagem deve ter até 2 MB.');
    }
    $info = @getimagesizefromstring($bytes);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2] ?? 0] ?? null;
    if (!$info || !$ext) {
        throw new RuntimeException('o arquivo não é uma imagem válida (use JPG, PNG, WEBP ou GIF).');
    }
    $dir = covers_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('não foi possível criar a pasta de imagens no servidor.');
    }
    $file = bin2hex(random_bytes(12)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $file, $bytes) === false) {
        throw new RuntimeException('não foi possível gravar a imagem no servidor (permissão da pasta public/uploads).');
    }
    return 'uploads/covers/' . $file;
}

/** Imagem enviada pelo formulário ($file = entrada normalizada de $_FILES). */
function save_cover_upload(array $file): string
{
    switch ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: throw new RuntimeException('a imagem deve ter até 2 MB.');
        default: throw new RuntimeException('falha no envio da imagem. Tente novamente.');
    }
    return save_cover_bytes((string) file_get_contents($file['tmp_name']));
}

/** Baixa a imagem de um link http(s) e a guarda no servidor. Bloqueia endereços internos (SSRF). */
function save_cover_from_url(string $url): string
{
    $p = parse_url($url);
    $scheme = strtolower($p['scheme'] ?? '');
    if (!$p || !in_array($scheme, ['http', 'https'], true) || empty($p['host'])) {
        throw new RuntimeException('o link da imagem é inválido (use http:// ou https://).');
    }
    $host = $p['host'];
    $ips = gethostbynamel($host) ?: [];
    if (!$ips) {
        throw new RuntimeException('não foi possível encontrar o endereço do link da imagem.');
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('o endereço do link da imagem não é permitido.');
        }
    }
    $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));

    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RESOLVE        => ["$host:$port:{$ips[0]}"], // fixa o IP validado (evita DNS rebinding)
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'FatecPoll/1.0',
        CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$body): int {
            $body .= $chunk;
            return strlen($body) > COVER_MAX_BYTES ? 0 : strlen($chunk); // aborta se passar do limite
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (strlen($body) > COVER_MAX_BYTES) {
        throw new RuntimeException('a imagem do link passa de 2 MB.');
    }
    if ($ok === false || $code !== 200) {
        throw new RuntimeException('não foi possível baixar a imagem do link informado.');
    }
    return save_cover_bytes($body);
}

function delete_cover(?string $path): void
{
    if ($path && str_starts_with($path, 'uploads/covers/') && basename($path) === substr($path, 15)) {
        @unlink(BASE_PATH . '/public/' . $path);
    }
}

/**
 * Grade em linhas cheias: o máximo possível por fileira (até 4 no desktop).
 * 3 → 3 | 4 → 4 | 5 → 3+2 | 6 → 3+3 | 7 → 4+3 | 8 → 4+4 | 9 → 3+3+3 ...
 * Retorna a classe do card $i (0-based) entre $n: s3 (4 por linha), s4 (3), s6 (2) ou s12 (1).
 * A mesma regra existe em public/js/app.js (que também adapta ao tamanho da tela e ao filtro).
 */
function even_grid_class(int $i, int $n, int $max = 4): string
{
    $rows = (int) ceil($n / $max);
    $base = intdiv($n, $rows);
    $extra = $n % $rows;
    $offset = 0;
    for ($r = 0; $r < $rows; $r++) {
        $size = $r < $extra ? $base + 1 : $base;
        if ($i < $offset + $size) {
            return 's' . intdiv(12, $size);
        }
        $offset += $size;
    }
    return 's12';
}
