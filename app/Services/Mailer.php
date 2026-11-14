<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Envio de e-mail sem dependências.
 *  - driver "smtp": fala SMTP direto (STARTTLS ou SSL, AUTH LOGIN/PLAIN).
 *  - driver "log":  grava a mensagem em storage/mail.log (desenvolvimento).
 * Lança RuntimeException se não conseguir enviar.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $text, string $html): void
    {
        $c = config('mail');
        $to = self::clean($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário inválido.');
        }
        $from = self::clean((string) $c['from_email']);
        $fromName = self::clean((string) $c['from_name']);
        $message = self::build($from, $fromName, $to, $subject, $text, $html);

        if (($c['driver'] ?? 'log') === 'smtp') {
            self::smtp($c, $from, $to, $message);
            return;
        }
        $dir = BASE_PATH . '/storage';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta storage/.');
        }
        $entry = "==== " . date('c') . " ====\nTo: $to\nSubject: $subject\n\n$text\n\n";
        if (@file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar storage/mail.log.');
        }
    }

    /** Remove quebras de linha (evita injeção de cabeçalhos). */
    private static function clean(string $v): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $v));
    }

    private static function encodeHeader(string $v): string
    {
        return '=?UTF-8?B?' . base64_encode($v) . '?=';
    }

    private static function build(string $from, string $fromName, string $to, string $subject, string $text, string $html): string
    {
        $boundary = 'fp_' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($from, '@') ?: '@fatecpoll.local', 1);
        $h = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . " <$from>",
            "To: <$to>",
            'Subject: ' . self::encodeHeader(self::clean($subject)),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
            'Auto-Submitted: auto-generated',
        ];
        $part = static fn(string $type, string $body): string =>
            "Content-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($body), 76, "\r\n");

        return implode("\r\n", $h) . "\r\n\r\n"
            . "--$boundary\r\n" . $part('text/plain', $text)
            . "--$boundary\r\n" . $part('text/html', $html)
            . "--$boundary--\r\n";
    }

    private static function smtp(array $c, string $from, string $to, string $message): void
    {
        if ($c['host'] === '') {
            throw new RuntimeException('SMTP_HOST não configurado.');
        }
        $secure = strtolower((string) $c['secure']);
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
        $fp = @stream_socket_client($remote, $errno, $errstr, 10);
        if (!$fp) {
            throw new RuntimeException("Falha ao conectar ao servidor de e-mail ({$c['host']}:{$c['port']}): $errstr");
        }
        stream_set_timeout($fp, 15);

        $read = static function () use ($fp): array {
            $code = 0; $text = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $text .= $line;
                $code = (int) substr($line, 0, 3);
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return [$code, $text];
        };
        $cmd = static function (string $line, array $ok) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            [$code, $text] = $read();
            if (!in_array($code, $ok, true)) {
                throw new RuntimeException('Servidor de e-mail recusou o comando: ' . trim($text));
            }
            return $text;
        };

        try {
            [$code, $banner] = $read();
            if ($code !== 220) {
                throw new RuntimeException('Resposta inesperada do servidor de e-mail: ' . trim($banner));
            }
            $helo = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $cmd('EHLO ' . preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $helo), [250]);

            if ($secure === 'tls') {
                $cmd('STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Não foi possível iniciar a conexão segura (STARTTLS).');
                }
                $cmd('EHLO ' . preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $helo), [250]);
            }
            if ($c['user'] !== '') {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($c['user']), [334]);
                $cmd(base64_encode($c['pass']), [235]);
            }
            $cmd("MAIL FROM:<$from>", [250]);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', [354]);
            // dot-stuffing + terminador
            $body = preg_replace('/^\./m', '..', $message);
            fwrite($fp, rtrim($body, "\r\n") . "\r\n.\r\n");
            [$code, $text] = $read();
            if ($code !== 250) {
                throw new RuntimeException('O servidor de e-mail não aceitou a mensagem: ' . trim($text));
            }
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }
}
