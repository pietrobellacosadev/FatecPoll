<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/** Cadastro de professores: valida o e-mail institucional, envia e confere o código de verificação. */
final class TeacherRegistration
{
    /** Só aceita nome@<domínio> exato (sem subdomínios, sem texto extra). */
    public static function isInstitutionalEmail(string $email): bool
    {
        $email = self::normalizeEmail($email);
        $domain = preg_quote((string) config('teacher_domain', 'cps.sp.gov.br'), '/');
        return strlen($email) <= 190
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[a-z0-9][a-z0-9._%+\-]*@' . $domain . '$/', $email) === 1;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /** Código digitado pelo usuário → só dígitos (aceita espaços e hífen). */
    public static function normalizeCode(string $code): string
    {
        return preg_replace('/\D+/', '', $code) ?? '';
    }

    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($user, 0, 2) . str_repeat('•', max(2, mb_strlen($user) - 2)) . '@' . $domain;
    }

    /**
     * Cria (ou atualiza, se ainda não confirmado) o cadastro e envia o código.
     * @return array{errors: string[], admin_id: ?int, email: string, mail_failed?: bool}
     */
    public static function register(string $name, string $email, string $password, string $confirm): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $email = self::normalizeEmail($email);
        $errors = [];

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors[] = 'Informe seu nome completo.';
        }
        if (!self::isInstitutionalEmail($email)) {
            $errors[] = 'Use seu e-mail institucional (@' . config('teacher_domain') . '). Outros domínios não são aceitos.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'A senha deve ter no máximo 72 caracteres.';
        } elseif ($password !== $confirm) {
            $errors[] = 'A confirmação da senha não confere.';
        }
        if ($errors) {
            return ['errors' => $errors, 'admin_id' => null, 'email' => $email];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, role, active, email_verified_at FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($existing) {
            $pending = $existing['role'] === 'teacher' && $existing['email_verified_at'] === null;
            if (!$pending) {
                return ['errors' => ['Este e-mail já está cadastrado. Entre com sua senha.'], 'admin_id' => null, 'email' => $email];
            }
            // Cadastro não confirmado: permite refazer (novo nome/senha) e pedir outro código.
            $pdo->prepare('UPDATE admins SET name = ?, password_hash = ? WHERE id = ?')->execute([$name, $hash, $existing['id']]);
            $adminId = (int) $existing['id'];
        } else {
            $pdo->prepare("INSERT INTO admins (name, email, role, password_hash, active) VALUES (?, ?, 'teacher', ?, 0)")
                ->execute([$name, $email, $hash]);
            $adminId = (int) $pdo->lastInsertId();
        }

        $sent = self::sendCode($adminId, $email, $name);
        $out = ['errors' => [], 'admin_id' => $adminId, 'email' => $email];
        if ($sent !== null) {
            $out['mail_failed'] = true;
            $out['notice'] = $sent;
        }
        return $out;
    }

    /** Há cadastro de professor aguardando confirmação para este e-mail? Retorna o id do admin. */
    public static function pendingId(string $email): ?int
    {
        $stmt = Database::pdo()->prepare("SELECT id FROM admins WHERE email = ? AND role = 'teacher' AND email_verified_at IS NULL LIMIT 1");
        $stmt->execute([self::normalizeEmail($email)]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Gera e envia um novo código. Retorna null se enviou, ou a mensagem de erro (limite/falha de envio). */
    public static function sendCode(int $adminId, string $email, ?string $name = null): ?string
    {
        $pdo = Database::pdo();
        $v = config('verify');

        $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM email_verifications WHERE admin_id = ?');
        $stmt->execute([$adminId]);
        $since = $stmt->fetchColumn();
        if ($since !== null && $since !== false && (int) $since < $v['resend_seconds']) {
            return 'Aguarde ' . ($v['resend_seconds'] - (int) $since) . ' segundos para pedir outro código.';
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM email_verifications WHERE admin_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
        $stmt->execute([$adminId]);
        if ((int) $stmt->fetchColumn() >= $v['max_per_hour']) {
            return 'Muitos envios em pouco tempo. Tente novamente em cerca de uma hora.';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE email_verifications SET consumed_at = NOW() WHERE admin_id = ? AND consumed_at IS NULL')->execute([$adminId]);
        $pdo->prepare('INSERT INTO email_verifications (admin_id, code_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)')
            ->execute([$adminId, self::hash($adminId, $code), (int) $v['ttl_minutes']]);
        $rowId = (int) $pdo->lastInsertId();

        try {
            self::mail($email, $name, $code, (int) $v['ttl_minutes']);
        } catch (Throwable $e) {
            // Não conta como envio (para o usuário poder tentar de novo sem esperar).
            $pdo->prepare('DELETE FROM email_verifications WHERE id = ?')->execute([$rowId]);
            error_log('[FatecPoll] falha ao enviar e-mail: ' . $e->getMessage());
            return config('debug')
                ? 'Não foi possível enviar o e-mail: ' . $e->getMessage()
                : 'Não foi possível enviar o e-mail agora. Tente novamente em instantes.';
        }
        return null;
    }

    /** @return string[] erros (vazio = confirmado com sucesso) */
    public static function verify(string $email, string $rawCode): array
    {
        $code = self::normalizeCode($rawCode);
        if (strlen($code) !== 6) {
            return ['Digite o código de 6 dígitos.'];
        }
        $adminId = self::pendingId($email);
        if ($adminId === null) {
            return ['Cadastro não encontrado ou já confirmado. Tente entrar com seu e-mail e senha.'];
        }

        $pdo = Database::pdo();
        $max = (int) config('verify.max_attempts');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, code_hash, attempts, (expires_at < NOW()) AS expired
                                     FROM email_verifications
                                    WHERE admin_id = ? AND consumed_at IS NULL
                                    ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $stmt->execute([$adminId]);
            $row = $stmt->fetch();

            if (!$row || (int) $row['expired'] === 1) {
                $pdo->commit();
                return ['Código expirado. Peça um novo código.'];
            }
            if ((int) $row['attempts'] >= $max) {
                $pdo->prepare('UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
                $pdo->commit();
                return ['Muitas tentativas incorretas. Peça um novo código.'];
            }
            if (!hash_equals($row['code_hash'], self::hash($adminId, $code))) {
                $attempts = (int) $row['attempts'] + 1;
                $pdo->prepare('UPDATE email_verifications SET attempts = ? WHERE id = ?')->execute([$attempts, $row['id']]);
                $pdo->commit();
                $left = $max - $attempts;
                return [$left > 0
                    ? 'Código incorreto. ' . plural($left, 'tentativa restante', 'tentativas restantes') . '.'
                    : 'Código incorreto. Peça um novo código.'];
            }

            $pdo->prepare('UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('UPDATE admins SET active = 1, email_verified_at = NOW() WHERE id = ?')->execute([$adminId]);
            $pdo->commit();
            return [];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function hash(int $adminId, string $code): string
    {
        return hash('sha256', $adminId . ':' . $code);
    }

    private static function mail(string $email, ?string $name, string $code, int $minutes): void
    {
        $app = (string) config('app_name', 'FatecPoll');
        $hello = $name ? 'Olá, ' . $name . '!' : 'Olá!';
        $text = "$hello\n\nSeu código de verificação do $app é: $code\n\n"
              . "Ele vale por $minutes minutos. Se você não pediu este cadastro, ignore este e-mail.\n";
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:auto;padding:24px;color:#0b1220">'
              . '<h2 style="margin:0 0 12px">' . $h($app) . '</h2>'
              . '<p>' . $h($hello) . '</p>'
              . '<p>Use este código para confirmar seu e-mail:</p>'
              . '<p style="font-size:34px;font-weight:700;letter-spacing:8px;background:#eef3fb;padding:16px;text-align:center;border-radius:12px">' . $h($code) . '</p>'
              . '<p style="color:#5b6b85;font-size:14px">O código vale por ' . $minutes . ' minutos. Se você não pediu este cadastro, ignore este e-mail.</p></div>';
        Mailer::send($email, "Seu código de verificação $app: $code", $text, $html);
    }
}
