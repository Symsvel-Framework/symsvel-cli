<?php

declare(strict_types=1);

namespace SymSvelCli;

final class DatabaseSetup
{
    public static function run(string $backend): int
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new \RuntimeException('Extension PHP pdo_pgsql requise pour PostgreSQL.');
        }
        if (!defined('STDIN') || !stream_isatty(STDIN)) {
            throw new \RuntimeException('Configuration interactive requise (terminal).');
        }

        echo "[symsvel] Configuration PostgreSQL locale\n";
        $host = self::ask('Hôte', '127.0.0.1');
        $port = self::ask('Port', '5432');
        $database = self::ask('Base de données', 'app');
        $user = self::ask('Utilisateur', 'app');
        $password = self::ask('Mot de passe', '', true);

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new \RuntimeException('Port PostgreSQL invalide.');
        }
        if ($database === '' || $user === '' || $host === '') {
            throw new \RuntimeException('Hôte, base et utilisateur obligatoires.');
        }

        try {
            $pdo = new \PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) $port, $database),
                $user,
                $password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5],
            );
            $pdo->query('SELECT 1');
        } catch (\PDOException $error) {
            throw new \RuntimeException('Connexion PostgreSQL impossible. Vérifier le serveur, la base et les identifiants.', 0, $error);
        }

        $url = sprintf(
            'postgresql://%s:%s@%s:%d/%s?charset=utf8',
            rawurlencode($user),
            rawurlencode($password),
            str_contains($host, ':') && !str_starts_with($host, '[') ? '['.$host.']' : $host,
            (int) $port,
            rawurlencode($database),
        );
        $target = rtrim($backend, '/').'/.env.local';
        if (is_link($target)) {
            throw new \RuntimeException('.env.local symbolique interdit.');
        }
        $previous = is_file($target) ? file_get_contents($target) : '';
        if ($previous === false) {
            throw new \RuntimeException('Lecture .env.local impossible.');
        }
        $lines = preg_split('/\r?\n/', $previous);
        $lines = array_values(array_filter($lines, static fn (string $line): bool => !preg_match('/^\s*DATABASE_URL\s*=/', $line)));
        $body = rtrim(implode("\n", $lines));
        $body = ($body === '' ? '' : $body."\n").'DATABASE_URL='.json_encode($url, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $temporary = tempnam($backend, '.env-local-');
        if ($temporary === false) {
            throw new \RuntimeException('Création temporaire .env.local impossible.');
        }
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $body) === false || !rename($temporary, $target)) {
                throw new \RuntimeException('Écriture .env.local impossible.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        echo "[symsvel] Connexion PostgreSQL validée ; .env.local configuré.\n";
        return 0;
    }

    private static function ask(string $label, string $default = '', bool $secret = false): string
    {
        echo $label.($default !== '' ? ' ['.$default.']' : '').' : ';
        $hidden = $secret && trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';
        if ($hidden) {
            shell_exec('stty -echo');
        }
        try {
            $value = fgets(STDIN);
        } finally {
            if ($hidden) {
                shell_exec('stty echo');
                echo "\n";
            }
        }
        if ($value === false) {
            throw new \RuntimeException('Saisie interrompue.');
        }
        $value = trim($value);
        return $value === '' && !$secret ? $default : $value;
    }
}
