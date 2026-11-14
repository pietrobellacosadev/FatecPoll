<?php
// Configuração lida de variáveis de ambiente (definidas no docker-compose.yml / .env).
// Os valores padrão servem para rodar sem Docker, com MariaDB local.
$env = static fn(string $k, string $d = '') => ($v = getenv($k)) !== false && $v !== '' ? $v : $d;

return [
    'app_name' => 'FatecPoll',
    'timezone' => $env('APP_TZ', 'America/Sao_Paulo'),
    'debug'    => filter_var($env('APP_DEBUG', '1'), FILTER_VALIDATE_BOOLEAN),
    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'fatecpoll'),
        'user'    => $env('DB_USER', 'fatecpoll'),
        'pass'    => $env('DB_PASS', 'fatecpoll'),
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'email'    => $env('ADMIN_EMAIL', 'admin@fatecpoll.local'),
        'password' => $env('ADMIN_PASSWORD', 'admin123'),
    ],
    // URL pública do site (usada nos links copiáveis das enquetes). Vazio = detecta pela requisição.
    'app_url' => rtrim($env('APP_URL', ''), '/'),
    // Chave secreta que assina o identificador de dispositivo e gera os hashes antifraude.
    // Vazio = usa/cria storage/app.key. Mantenha a MESMA chave sempre: trocá-la invalida as identidades já emitidas.
    'app_key' => $env('APP_KEY', ''),
    // O que fazer quando um dispositivo "novo" repete o fingerprint + rede de quem já votou:
    // flag  = vota, mas a participação fica marcada como suspeita (padrão: não pune quem usa o mesmo modelo de celular na mesma rede)
    // block = recusa o voto (mais rígido; pode barrar pessoas diferentes com aparelhos idênticos na mesma rede Wi-Fi)
    'duplicate_signal' => $env('DUPLICATE_SIGNAL', 'flag') === 'block' ? 'block' : 'flag',
    // Cadastro de professores: só e-mails deste domínio (exato, sem subdomínios).
    'teacher_domain' => 'cps.sp.gov.br',
    'verify' => [
        'ttl_minutes'    => 10,  // validade do código
        'max_attempts'   => 5,   // tentativas erradas por código
        'resend_seconds' => 60,  // intervalo mínimo entre envios
        'max_per_hour'   => 5,   // envios por hora, por cadastro
    ],
    // MAIL_DRIVER: smtp (envia de verdade) ou log (grava em storage/mail.log, para testes sem servidor de e-mail)
    'mail' => [
        'driver'     => $env('MAIL_DRIVER', 'log'),
        'host'       => $env('SMTP_HOST', ''),
        'port'       => (int) $env('SMTP_PORT', '587'),
        'secure'     => $env('SMTP_SECURE', 'tls'),   // tls (STARTTLS, 587) | ssl (465) | none
        'user'       => $env('SMTP_USER', ''),
        'pass'       => $env('SMTP_PASS', ''),
        'from_email' => $env('MAIL_FROM', 'nao-responder@fatecpoll.local'),
        'from_name'  => $env('MAIL_FROM_NAME', 'FatecPoll'),
    ],
    'seed_demo' => filter_var($env('SEED_DEMO', '1'), FILTER_VALIDATE_BOOLEAN),
];
