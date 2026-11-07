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
                'setup' => self::setup($args),
                'setup:finalize' => self::finalizeSetup($args),
                'make:module' => self::makeModule($args),
                'update' => self::update($args),
                'require', 'remove' => self::composer($command, $args),
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
  symsvel setup               # Lance le Wizard local sous l'utilisateur CLI
  symsvel setup:finalize
  symsvel create-project --components=all|bo|app|front [--directory=backend] [--dev]
  symsvel make:module <name> [--scopes=back,bo,app,front] [--vendor=symsvel-framework] [--workspace=path]
  symsvel require <package> [constraint] [--bootstrap]
  symsvel update [bo|app|front|package]
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
        $colors = self::supportsAnsiColors();
        foreach ($requirements as $name => $ok) {
            $label = $ok ? '[OK]' : '[KO]';
            if ($colors) {
                $label = ($ok ? "\033[32m" : "\033[31m").$label."\033[0m";
            }
            echo $label.' '.$name.PHP_EOL;
        }
        echo "Symfony CLI : ".(self::available('symfony') ? 'disponible (facultative)' : 'absente (facultative)').PHP_EOL;
        return in_array(false, $requirements, true) ? 1 : 0;
    }

    private static function supportsAnsiColors(): bool
    {
        if (getenv('NO_COLOR') !== false || getenv('TERM') === 'dumb') {
            return false;
        }
        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    private static function createProject(array $args): int
    {
        $components = 'all';
        $directory = 'backend';
        $dev = false;
        foreach ($args as $arg) {
            if ($arg === '--dev') {
                $dev = true;
            } elseif (str_starts_with($arg, '--components=')) {
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
        $create = ['composer', 'create-project'];
        if ($dev) {
            // Resolve the skeleton directly from GitHub, bypassing potentially stale Satis metadata.
            $create[] = '--repository={"type":"vcs","url":"https://github.com/Symsvel-Framework/symsvel-back.git"}';
            $create[] = '--no-cache';
        }
        array_push($create, 'symsvel-framework/back', $directory);
        if ($dev) {
            $create[] = 'dev-main';
        }
        self::run($create, getcwd());
        $backend = realpath($directory);
        if ($backend === false || !is_file($backend.'/bin/console')) {
            throw new \RuntimeException('Projet créé, mais bin/console introuvable. Destination conservée : '.$directory);
        }
        try {
            self::persistProjectChannel($backend, $dev ? 'dev' : 'stable');
            if ($dev) {
                // The initial skeleton has already been created. Further Composer
                // operations must resolve SymSvel from GitHub, not release-only Satis.
                self::removeSatisRepository($backend);
                // The skeleton pins stable versions; explicitly resolve the current
                // development branches before booting Symfony in the dev environment.
                self::run(['composer', 'config', 'repositories.symsvel-tooling-dev', 'vcs', 'https://github.com/Symsvel-Framework/symsvel-tooling.git'], $backend);
                self::run(['composer', 'config', 'repositories.symsvel-core-dev', 'vcs', 'https://github.com/Symsvel-Framework/symsvel-core.git'], $backend);
                self::run(['composer', 'require', '--dev', '--with-all-dependencies', '--no-cache', 'symsvel-framework/tooling:dev-main'], $backend);
                self::run(['composer', 'require', '--with-all-dependencies', '--no-cache', 'symsvel-framework/core:dev-main'], $backend);
                if (!is_file($backend.'/vendor/symsvel-framework/tooling/bin/symsvel-composer-transaction.php')) {
                    throw new \RuntimeException('Tooling development checkout missing Composer transaction launcher.');
                }
            }
            // Defer builds from the first component clone through native module installation.
            $previousDefer = getenv('SYMSVEL_DEFER_BUILDS');
            putenv('SYMSVEL_DEFER_BUILDS=1');
            try {
                self::run([PHP_BINARY, 'bin/console', 'symsvel:install', '--components='.$components], $backend);
                self::installNativeModules($backend, $dev, $components);
            } finally {
                if ($previousDefer === false) {
                    putenv('SYMSVEL_DEFER_BUILDS');
                } else {
                    putenv('SYMSVEL_DEFER_BUILDS='.$previousDefer);
                }
            }
            self::run([PHP_BINARY, 'scripts/symsvel-module-sync.php'], $backend);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Création partielle : '.$e->getMessage().'. Projet conservé dans '.$backend);
        }
        echo "[symsvel] Projet créé : ".$backend.PHP_EOL;
        echo "[symsvel] Démarrage du Setup Wizard...".PHP_EOL;
        $previousDirectory = getcwd();
        if (!chdir($backend)) {
            throw new \RuntimeException("Impossible d’ouvrir le backend pour le Setup Wizard.");
        }
        try {
            return self::setup([]);
        } finally {
            chdir($previousDirectory);
        }
    }

    /**
     * Set once at project creation; never infer the channel from APP_ENV.
     * The Tooling resolver reads the same backend/.symsvel/project.json contract.
     */
    private static function persistProjectChannel(string $backend, string $channel): void
    {
        if (!in_array($channel, ['stable', 'dev'], true)) {
            throw new \InvalidArgumentException('Canal SymSvel inconnu.');
        }
        $directory = $backend.'/.symsvel';
        $path = $directory.'/project.json';
        if (is_link($directory) || (file_exists($directory) && !is_dir($directory)) || is_link($path) || file_exists($path)) {
            throw new \RuntimeException('Configuration de provenance déjà présente ou non fiable.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Création de la configuration SymSvel impossible.');
        }
        $temporary = tempnam($directory, '.project-');
        if ($temporary === false) {
            throw new \RuntimeException('Préparation de la provenance impossible.');
        }
        try {
            $content = json_encode(['schema' => 1, 'channel' => $channel], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
            if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
                throw new \RuntimeException('Écriture de la provenance impossible.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Native modules are declared by the skeleton, never hard-coded in the CLI.
     * Composer/Flex installs each package; migrations wait for the Setup Wizard.
     */
    /** Remove only the SymSvel release repository, preserving third-party repositories. */
    private static function removeSatisRepository(string $backend): void
    {
        $path = $backend.'/composer.json';
        $configuration = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $repositories = $configuration['repositories'] ?? [];
        if (!is_array($repositories)) {
            throw new \RuntimeException('Configuration des dépôts Composer invalide.');
        }
        foreach ($repositories as $key => $repository) {
            if (!is_array($repository) || ($repository['type'] ?? null) !== 'composer') {
                continue;
            }
            $url = rtrim((string) ($repository['url'] ?? ''), '/');
            if ($url === 'https://packages.xavier-tezza.fr') {
                unset($repositories[$key]);
            }
        }
        $configuration['repositories'] = array_is_list($repositories)
            ? $repositories : array_values($repositories);
        $encoded = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $encoded."\n", LOCK_EX) === false) {
            throw new \RuntimeException('Impossible de mettre à jour les dépôts Composer.');
        }
    }

    private static function installNativeModules(string $backend, bool $dev, string $components): void
    {
        $manifest = $backend.'/config/symsvel-native-modules.json';
        if (!is_file($manifest)) {
            return;
        }
        $config = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        if (($config['schema'] ?? null) !== 1) {
            throw new \RuntimeException('Version du manifeste des modules natifs invalide.');
        }
        $modules = $config['modules'] ?? null;
        if (!is_array($modules) || !array_is_list($modules)) {
            throw new \RuntimeException('Configuration des modules natifs invalide.');
        }
        $componentModules = $config['components'] ?? [];
        if (!is_array($componentModules)) {
            throw new \RuntimeException('Configuration des modules de composants invalide.');
        }
        if (in_array($components, ['all', 'app'], true)) {
            foreach ($componentModules['app'] ?? [] as $package) {
                $modules[] = $package;
            }
        }
        $modules = array_values(array_unique($modules));
        if ($dev) {
            // Satis can expose only tagged releases. In development mode, register
            // the module VCS repositories so Composer can resolve dev-main.
            foreach ($modules as $package) {
                if (!is_string($package) || !preg_match('~^symsvel-framework/module-[a-z][a-z0-9-]*$~D', $package)) {
                    throw new \RuntimeException('Module natif invalide dans le manifeste.');
                }
                $moduleName = substr($package, strlen('symsvel-framework/'));
                self::run(['composer', 'config', 'repositories.'.$moduleName.'-dev', 'vcs', 'https://github.com/Symsvel-Framework/symsvel-'.$moduleName.'.git'], $backend);
            }
        }
        foreach ($modules as $package) {
            if (!is_string($package) || !preg_match('~^symsvel-framework/module-[a-z][a-z0-9-]*$~D', $package)) {
                throw new \RuntimeException('Module natif invalide dans le manifeste.');
            }
        }
        if ($modules === []) {
            return;
        }
        $launcher = $backend.'/scripts/symsvel-composer-transaction.php';
            if (!is_file($launcher)) {
                throw new \RuntimeException('Lanceur transactionnel introuvable pour les modules natifs.');
            }
        $constraints = array_map(static fn (string $package): string => $dev ? $package.':dev-main' : $package, $modules);
        self::run(array_merge([PHP_BINARY, $launcher, 'require'], $constraints, ['--bootstrap']), $backend);
    }

    /**
     * Run the browser wizard under the same OS identity as the installer.
     * This avoids requiring Apache, Nginx or PHP-FPM to write project files.
     */
    private static function setup(array $args): int
    {
        if ($args !== []) {
            throw new \RuntimeException('setup ne prend pas d’arguments.');
        }
        $backend = self::findBackend(getcwd());
        if ($backend === null) {
            throw new \RuntimeException('Exécutez setup depuis un projet SymSvel.');
        }
        $workspace = dirname($backend);
        $stateFile = $workspace.'/.symsvel/setup.json';
        if (!is_file($stateFile) || is_link($stateFile)) {
            throw new \RuntimeException('Setup non initialisé. Exécutez create-project auparavant.');
        }
        $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
        if (($state['schema'] ?? null) !== 1 || !in_array($state['status'] ?? null, ['pending', 'running'], true)) {
            throw new \RuntimeException('Setup déjà terminé ou état invalide.');
        }
        $tokenFile = $workspace.'/.symsvel/setup-token';
        if (!is_file($tokenFile) || is_link($tokenFile)) {
            throw new \RuntimeException('Jeton de setup introuvable.');
        }
        $token = trim((string) file_get_contents($tokenFile));
        if ($token === '') {
            throw new \RuntimeException('Jeton de setup vide.');
        }
        if (!is_file($backend.'/public/index.php')) {
            throw new \RuntimeException('Front controller Symfony introuvable.');
        }
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException('Impossible de réserver un port local : '.$error);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address) || !preg_match('/:(\\d+)$/D', $address, $matches)) {
            throw new \RuntimeException('Port local indisponible.');
        }
        $port = (int) $matches[1];
        $url = 'http://127.0.0.1:'.$port.'/setup';
        echo '[symsvel] Wizard local : '.$url.PHP_EOL;
        echo '[symsvel] Jeton à saisir dans le formulaire : '.$token.PHP_EOL;
        echo '[symsvel] Serveur accessible uniquement sur cette machine. Ctrl+C pour arrêter.'.PHP_EOL;
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', 'public', 'public/index.php'],
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $backend,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Démarrage du serveur local impossible.');
        }
        self::openBrowser($url);
        $exitCode = proc_close($process);
        return $exitCode === 0 || $exitCode === 130 ? 0 : 1;
    }

    /** Best-effort browser launch; the URL remains visible for headless environments. */
    private static function openBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $url],
            'Windows' => ['cmd', '/c', 'start', '', $url],
            default => ['xdg-open', $url],
        };
        try {
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                return;
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        } catch (\Throwable) {
            // A missing desktop opener must never prevent manual setup.
        }
    }

    private static function finalizeSetup(array $args): int
    {
        if ($args !== []) {
            throw new \RuntimeException('setup:finalize ne prend pas d’arguments.');
        }
        $backend = self::findBackend(getcwd());
        if ($backend === null) {
            throw new \RuntimeException('Exécutez setup:finalize depuis un projet SymSvel.');
        }
        self::run([PHP_BINARY, 'bin/console', 'symsvel:setup:finalize', '--no-interaction'], $backend);
        return 0;
    }

    private static function makeModule(array $args): int
    {
        $name = null;
        $scopes = 'back';
        $vendor = 'symsvel-framework';
        $workspaceOption = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--scopes=')) {
                $scopes = substr($arg, strlen('--scopes='));
            } elseif (str_starts_with($arg, '--vendor=')) {
                $vendor = substr($arg, strlen('--vendor='));
            } elseif (str_starts_with($arg, '--workspace=')) {
                $workspaceOption = substr($arg, strlen('--workspace='));
            } elseif ($name === null && !str_starts_with($arg, '-')) {
                $name = $arg;
            } else {
                throw new \RuntimeException('Argument inconnu : '.$arg);
            }
        }
        if ($name === null) {
            throw new \RuntimeException('Nom requis : symsvel make:module <name>.');
        }
        if ($workspaceOption !== null && $workspaceOption === '') {
            throw new \RuntimeException('Valeur --workspace vide.');
        }

        $backend = self::findBackend(getcwd());
        if ($workspaceOption !== null) {
            $workspace = realpath($workspaceOption);
            if ($workspace === false || !is_dir($workspace)) {
                throw new \RuntimeException('Workspace introuvable : '.$workspaceOption);
            }
        } elseif ($backend !== null) {
            $workspace = dirname($backend);
        } else {
            // Local framework checkout: CLI invoked from workspace or a nested directory.
            $workspace = self::findFrameworkWorkspace(getcwd());
            if ($workspace === null) {
                throw new \RuntimeException('Contexte SymSvel introuvable. Utilisez --workspace.');
            }
        }

        // Framework workspaces own their generator, even when a backend is present.
        // A backend in the workspace must not redirect module creation to Symfony.
        $makerSource = $workspace.'/framework/tools/src/Module/ModuleMaker.php';
        if (is_file($makerSource)) {
            require_once $makerSource;
            $path = (new \SymSvel\Tooling\Module\ModuleMaker())->create($workspace, $name, $vendor, $scopes);
            echo '[symsvel] Module créé : '.$path.PHP_EOL;
            return 0;
        }

        // An explicit workspace may point to a different project than the current directory.
        if ($workspaceOption !== null) {
            $selectedBackend = self::findBackend($workspace);
            $backend = $selectedBackend !== null && dirname($selectedBackend) === $workspace ? $selectedBackend : null;
        }

        if ($backend !== null) {
            // Always delegate to the Tooling generator installed with the Symfony backend.
            self::run([PHP_BINARY, 'bin/console', 'symsvel:make:module', $name, '--workspace='.$workspace, '--scopes='.$scopes, '--vendor='.$vendor, '--no-interaction'], $backend);
            return 0;
        }

        throw new \RuntimeException('Aucun générateur de module disponible dans ce workspace.');
    }

    private static function findFrameworkWorkspace(string $directory): ?string
    {
        $current = realpath($directory);
        while ($current !== false) {
            if (is_file($current.'/framework/tools/src/Module/ModuleMaker.php')) {
                return $current;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }
        return null;
    }

    private static function update(array $args): int
    {
        // Component names are reserved CLI targets, not Composer packages.
        // Delegate lifecycle orchestration to Tooling, which owns installation,
        // module synchronization, builds and rollback.
        if (count($args) === 1 && in_array($args[0], ['bo', 'app', 'front'], true)) {
            self::assertRequirements(true);
            $backend = self::findBackend(getcwd());
            if ($backend === null) {
                throw new \RuntimeException('Projet SymSvel introuvable (composer.json + bin/console).');
            }
            self::run([PHP_BINARY, 'bin/console', 'symsvel:component:update', $args[0], '--no-interaction'], $backend);
            return 0;
        }

        return self::composer('update', $args);
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
        if (in_array('--bootstrap', $args, true) && $command !== 'require') {
            throw new \RuntimeException('--bootstrap est réservé à symsvel require.');
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
        $process = proc_open(['/bin/sh', '-c', 'command -v '.escapeshellarg($program).' >/dev/null 2>&1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }

    private static function run(array $argv, string $cwd, array $environment = []): void
    {
        echo '[symsvel] '.implode(' ', array_map('escapeshellarg', $argv)).PHP_EOL;
        $env = $environment === [] ? null : array_merge(getenv() ?: [], $_ENV, $environment);
        $process = proc_open($argv, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new \RuntimeException('Impossible de démarrer la commande.');
        }
        $status = proc_close($process);
        if ($status !== 0) {
            throw new \RuntimeException('Commande échouée (code '.$status.').');
        }
    }
}
