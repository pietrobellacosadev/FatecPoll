<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Identificação anônima e persistente do dispositivo (sem conta de participante).
 *
 *  - O identificador é gerado SEMPRE pelo servidor (128 bits aleatórios) e assinado com HMAC:
 *    o cliente não consegue inventar nem alterar um identificador (assinatura inválida = ignorado).
 *  - Fica em cookie HttpOnly de longa duração e numa cópia no localStorage (enviada no cabeçalho
 *    X-Device-Token). Apagar só os cookies, ou só o localStorage, não libera um novo voto.
 *  - No banco só entra HMAC(chave, enquete|id): nada que identifique a pessoa e sem ligação entre enquetes.
 *  - IP e fingerprint viram hashes e servem só de sinal antifraude (nunca bloqueiam sozinhos: ver VotingService).
 */
final class Device
{
    public const COOKIE = 'fp_dev';
    private const TTL   = 400 * 86400; // limite máximo de validade de cookies nos navegadores atuais

    // ---------- Chave secreta ----------

    /** APP_KEY (ambiente) ou arquivo storage/app.key, criado na primeira necessidade. Trocar a chave invalida as identidades. */
    public static function key(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $env = (string) config('app_key', '');
        if ($env !== '') {
            return $key = $env;
        }
        $file = BASE_PATH . '/storage/app.key';
        $read = static fn(): string => is_file($file) ? trim((string) @file_get_contents($file)) : '';
        $val = $read();
        if ($val === '') {
            // Criação exclusiva ('x'): se duas requisições chegarem juntas, só uma cria e a outra lê a mesma chave.
            $fh = @fopen($file, 'x');
            if ($fh !== false) {
                fwrite($fh, bin2hex(random_bytes(32)));
                fclose($fh);
                @chmod($file, 0600);
            }
            for ($i = 0; $i < 20 && ($val = $read()) === ''; $i++) {
                usleep(50000); // a outra requisição ainda está gravando
            }
        }
        if ($val === '') {
            // Falha FECHADA: uma chave temporária daria uma identidade diferente a cada requisição e anularia o voto único.
            throw new \RuntimeException('FatecPoll: não foi possível ler/criar storage/app.key. Defina APP_KEY ou libere escrita em storage/.');
        }
        return $key = $val;
    }

    // ---------- Token (id.assinatura) ----------

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function mint(): string
    {
        $id = self::b64(random_bytes(16));
        return $id . '.' . self::sign($id);
    }

    private static function sign(string $id): string
    {
        return substr(self::b64(hash_hmac('sha256', 'device|' . $id, self::key(), true)), 0, 22);
    }

    /** Devolve o id (sem assinatura) se o token for autêntico; null caso contrário. */
    public static function verify(mixed $token): ?string
    {
        if (!is_string($token) || !preg_match('/^([A-Za-z0-9_-]{22})\.([A-Za-z0-9_-]{22})$/', $token, $m)) {
            return null;
        }
        return hash_equals(self::sign($m[1]), $m[2]) ? $m[1] : null;
    }

    /**
     * Identidade da requisição atual.
     * @return array{ids: string[], token: string, fresh: bool}
     *   ids   = todos os ids autênticos apresentados (cookie e/ou cópia do navegador), sem repetição
     *   token = token canônico que vale daqui em diante (o apresentado mais antigo na lista, ou um novo)
     *   fresh = true quando nada autêntico foi apresentado e um id novo foi gerado
     */
    public static function resolve(): array
    {
        $ids = [];
        $tokens = [];
        foreach ([
            $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? null,   // cópia do localStorage (tem prioridade: é a mais antiga)
            $_COOKIE[self::COOKIE] ?? null,
        ] as $t) {
            $id = self::verify($t);
            if ($id !== null && !in_array($id, $ids, true)) {
                $ids[] = $id;
                $tokens[] = (string) $t;
            }
        }
        if ($ids) {
            return ['ids' => $ids, 'token' => $tokens[0], 'fresh' => false];
        }
        $token = self::mint();
        return ['ids' => [(string) self::verify($token)], 'token' => $token, 'fresh' => true];
    }

    /** Grava/renova o cookie persistente (deve ser chamado antes de qualquer saída). */
    public static function remember(string $token): void
    {
        if (headers_sent()) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + self::TTL,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    // ---------- Hashes guardados no banco ----------

    /** Chave de unicidade do dispositivo na enquete (HMAC por enquete: sem ligação entre enquetes). */
    public static function deviceHash(int $pollId, string $id): string
    {
        return hash_hmac('sha256', "dev|$pollId|$id", self::key());
    }

    /** Hash da rede (IP). Em IPv6 usa só o prefixo /64; em IPv4, o endereço inteiro. O IP em si nunca é salvo. */
    public static function ipHash(int $pollId): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (str_contains($ip, ':')) {
            $bin = inet_pton($ip);
            $ip = $bin === false ? $ip : bin2hex(substr($bin, 0, 8));
        }
        return hash_hmac('sha256', "ip|$pollId|$ip", self::key());
    }

    /** Hash do fingerprint enviado pelo navegador (texto curto, validado). Null se ausente ou inválido. */
    public static function fingerprintHash(int $pollId, mixed $raw): ?string
    {
        if (!is_string($raw) || !preg_match('/^[A-Za-z0-9_.:|,;\/ +-]{8,300}$/', $raw)) {
            return null;
        }
        return hash_hmac('sha256', "fp|$pollId|$raw", self::key());
    }
}
