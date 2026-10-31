<?php

declare(strict_types=1);

namespace SymSvelCli;

final class Cli
{
    public static function main(array $args): int
    {
        $command = array_shift($args) ?? 'help';
        try {
            return match ($command) {
                'help', '--help', '-h' => self::help(),
                '--version', '-V', 'version' => self::version(),
                'doctor' => self::doctor($args),
                'create-project' => self::createProject($args),
                'require', 'update', 'remove' => self::composer($command, $args),
                default => throw new \RuntimeException("Commande inconnue : $command"),
            };
        } catch (\Throwable $e) {
            fwrite(STDERR, "[symsvel] ".$e->getMessage().PHP_EOL);
            return 1;
        }
    }

    private static function help(): int
    {
        echo <<<'HELP'
SymSvel CLI (development)

Usage:
  symsvel doctor
  symsvel create-project --components=all|bo|app|front [--directory=backend]
  symsvel require <package> [constraint]
  symsvel update [package]
  symsvel remove <package>
  symsvel --version

The CLI requires PHP, Composer and Git. create-project requires Node.js and npm
for frontend components. The Symfony CLI is not required.
Run require/update/remove from a SymSvel project or its backend directory.
HELP;
        echo PHP_EOL;
        return 0;
    }

    private static function version(): int
    {
        echo "SymSvel CLI 0.1.0-dev".PHP_EOL;
        return 0;
    }

    private static function doctor(array $args): int
    {
        if ($args !== []) {
            throw new \RuntimeException('doctor ne prend pas d’arguments.');
        }
        $requirements = [
            'PHP >= 8.4' => PHP_VERSION_ID >= 80400,
            'Composer' => self::available('composer'),
            'Git' => self::available('git'),
            'Node.js' => self::available('node'),
            'npm' => self::available('npm'),
        ];
        foreach ($requirements as $name => $ok) {
            echo ($ok ? '[OK] ' : '[KO] ').$name.PHP_EOL;
        }
        echo "Symfony CLI : ".(self::available('symfony') ? 'disponible (facultative)' : 'absente (facultative)').PHP_EOL;
        return in_array(false, $requirements, true) ? 1 : 0;
    }

    private static function createProject(array $args): int
    {
        $components = 'all';
        $directory = 'backend';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--components=')) {
                $components = substr($arg, 13);
            } elseif (str_starts_with($arg, '--directory=')) {
                $directory = substr($arg, 12);
            } else {
                throw new \RuntimeException('Option inconnue : '.$arg);
            }
        }
        if (!in_array($components, ['all', 'bo', 'app', 'front'], true)) {
            throw new \RuntimeException('Composants invalides : '.$components);
        }
        if ($directory === '' || $directory === '.' || str_starts_with($directory, '-') || str_contains($directory, '..')) {
            throw new \RuntimeException('Répertoire de destination invalide.');
        }
        if (file_exists($directory)) {
            throw new \RuntimeException('Destination déjà existante : '.$directory.' (aucune suppression automatique).');
        }
        self::assertRequirements(true);
        self::run(['composer', 'create-project', 'symsvel-framework/back', $directory], getcwd());
        $backend = realpath($directory);
        if ($backend === false || !is_file($backend.'/bin/console')) {
            throw new \RuntimeException('Projet créé, mais bin/console introuvable. Destination conservée : '.$directory);
        }
        try {
            self::run([PHP_BINARY, 'bin/console', 'symsvel:install', '--components='.$components], $backend);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Création partielle : '.$e->getMessage().'. Projet conservé dans '.$backend);
        }
        echo "[symsvel] Projet créé : ".$backend.PHP_EOL;
        return 0;
    }

    private static function composer(string $command, array $args): int
    {
        self::assertRequirements(false);
        $backend = self::findBackend(getcwd());
        if ($backend === null) {
            throw new \RuntimeException('Projet SymSvel introuvable (composer.json + bin/console).');
        }
        $launcher = $backend.'/scripts/symsvel-composer-transaction.php';
        if (!is_file($launcher)) {
            throw new \RuntimeException('Lanceur transactionnel absent : '.$launcher.'. Mettre à jour symsvel-tooling.');
        }
        if ($command !== 'update' && $args === []) {
            throw new \RuntimeException($command.' nécessite un nom de package.');
        }
        self::run(array_merge([PHP_BINARY, $launcher, $command], $args), $backend);
        return 0;
    }

    private static function findBackend(string $directory): ?string
    {
        $current = realpath($directory);
        while ($current !== false) {
            foreach ([$current, $current.'/backend'] as $candidate) {
                if (is_file($candidate.'/composer.json') && is_file($candidate.'/bin/console')) {
                    $json = json_decode((string) file_get_contents($candidate.'/composer.json'), true);
                    $require = array_merge($json['require'] ?? [], $json['require-dev'] ?? []);
                    if (isset($require['symsvel-framework/core']) || isset($require['symsvel-framework/tooling'])) {
                        return $candidate;
                    }
                }
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }
        return null;
    }

    private static function assertRequirements(bool $node): void
    {
        if (PHP_VERSION_ID < 80400) {
            throw new \RuntimeException('PHP >= 8.4 requis.');
        }
        foreach (array_merge(['composer', 'git'], $node ? ['node', 'npm'] : []) as $program) {
            if (!self::available($program)) {
                throw new \RuntimeException('Prérequis manquant : '.$program);
            }
        }
    }

    private static function available(string $program): bool
    {
        $process = proc_open(['command', '-v', $program], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        // "command" is a shell builtin; use a POSIX shell explicitly.
        if (is_resource($process)) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        $process = proc_open(['/bin/sh', '-c', 'command -v '.escapeshellarg($program).' >/dev/null 2>&1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }

    private static function run(array $argv, string $cwd): void
    {
        echo '[symsvel] '.implode(' ', array_map('escapeshellarg', $argv)).PHP_EOL;
        $process = proc_open($argv, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de démarrer la commande.');
        }
        $status = proc_close($process);
        if ($status !== 0) {
            throw new \RuntimeException('Commande échouée (code '.$status.').');
        }
    }
}
