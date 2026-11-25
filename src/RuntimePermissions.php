<?php

declare(strict_types=1);

namespace SymSvelCli;

/** Local runtime ACLs only; available before Composer installs Tooling. */
final class RuntimePermissions
{
    /** @return list<int> */
    public static function workerIds(string $processes): array
    {
        $ids = [];
        foreach (preg_split('/\R/', $processes) ?: [] as $line) {
            if (preg_match('~^\s*(\d+)\s+php-fpm(?:[0-9.]+)?: pool\s+\S.*$~D', $line, $match)
                && (int) $match[1] > 0) {
                $ids[] = (int) $match[1];
            }
        }

        return array_values(array_unique($ids));
    }

    public static function prepare(string $backend, ?string $httpUser = null): string
    {
        if (!in_array(PHP_OS_FAMILY, ['Darwin', 'Linux'], true)
            || !function_exists('posix_geteuid') || !function_exists('posix_getpwuid')
            || !function_exists('posix_getpwnam')) {
            throw new \RuntimeException('ACL locales non prises en charge sur ce système (macOS/Linux et extension POSIX requis).');
        }

        $uid = posix_geteuid();
        if ($uid === 0) {
            throw new \RuntimeException('Exécutez la CLI avec votre compte de développement, pas root.');
        }
        $cli = posix_getpwuid($uid);
        if ($cli === false) {
            throw new \RuntimeException('Utilisateur CLI introuvable.');
        }
        if ($httpUser === null) {
            $configured = getenv('SYMSVEL_HTTP_USER');
            if ($configured !== false && $configured !== '') {
                $httpUser = $configured;
            }
        }
        if ($httpUser !== null) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*[$]?$/D', $httpUser)) {
                throw new \RuntimeException('Nom de compte HTTP invalide.');
            }
            $http = posix_getpwnam($httpUser);
            if ($http === false || $http['uid'] <= 0) {
                throw new \RuntimeException('Compte HTTP inexistant ou privilégié : '.$httpUser);
            }
        } else {
            // Darwin comm is a truncated process title, not a reliable executable name.
            // Request only effective UID and full, untruncated arguments on both systems.
            $ids = self::workerIds(self::command(['ps', '-axww', '-o', 'uid=', '-o', 'args=']));
            if ($ids === []) {
                return 'Aucun worker PHP-FPM détecté ; si le serveur HTTP utilise un autre compte, exécutez symsvel permissions:fix --http-user=COMPTE.';
            }
            if (count($ids) !== 1) {
                throw new \RuntimeException('Plusieurs comptes PHP-FPM détectés ; précisez le compte du projet avec --http-user=COMPTE.');
            }
            $http = posix_getpwuid($ids[0]);
            if ($http === false) {
                throw new \RuntimeException('Compte PHP-FPM introuvable.');
            }
        }
        if ($http['uid'] === $uid) {
            return 'CLI et PHP HTTP : même utilisateur, aucune ACL supplémentaire.';
        }

        // Validate the complete scope before changing any ACL. Do not follow
        // symlinks or modify hard-linked files, databases or private metadata.
        if (is_link($backend) || !is_dir($backend)) {
            throw new \RuntimeException('Backend non fiable.');
        }
        $var = $backend.'/var';
        self::ensureDirectory($var);
        $directories = [];
        $files = [];
        foreach (['cache', 'log'] as $name) {
            $root = $var.'/'.$name;
            self::ensureDirectory($root);
            $directories[] = $root;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new \RuntimeException('Lien symbolique refusé : '.$item->getPathname());
                }
                if ($item->isDir()) {
                    $directories[] = $item->getPathname();
                } elseif ($item->isFile() && stat($item->getPathname())['nlink'] === 1) {
                    $files[] = $item->getPathname();
                } else {
                    throw new \RuntimeException('Fichier runtime non fiable : '.$item->getPathname());
                }
            }
        }

        // Each parent retains its inheritance ACL through Symfony cache:clear
        // (which creates a sibling of the environment cache under var/cache).
        foreach ([$cli, $http] as $account) {
            if (PHP_OS_FAMILY === 'Darwin') {
                $principal = 'user:'.$account['name'].' allow read,write,append,delete,writeattr';
                foreach (array_chunk($directories, 64) as $chunk) {
                    self::command(['chmod', '+a', $principal.',file_inherit,directory_inherit', ...$chunk]);
                    // Search and deletion of children are directory rights only.
                    self::command(['chmod', '+a', 'user:'.$account['name'].' allow search,delete_child,directory_inherit', ...$chunk]);
                }
                foreach (array_chunk($files, 64) as $chunk) {
                    self::command(['chmod', '+a', $principal, ...$chunk]);
                }
            } else {
                $acl = 'u:'.$account['uid'].':';
                foreach (array_chunk($directories, 64) as $chunk) {
                    self::command(['setfacl', '-m', $acl.'rwx,d:'.$acl.'rwx', '--', ...$chunk]);
                }
                foreach (array_chunk($files, 64) as $chunk) {
                    self::command(['setfacl', '-m', $acl.'rw-', '--', ...$chunk]);
                }
            }
        }

        return 'ACL runtime CLI/HTTP : '.$cli['name'].' / '.$http['name'].' (var/cache et var/log).';
    }

    private static function ensureDirectory(string $path): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, 0755) && !is_dir($path))) {
            throw new \RuntimeException('Répertoire runtime non fiable : '.$path);
        }
    }

    /** @param list<string> $arguments */
    private static function command(array $arguments): string
    {
        // Capture to a single temporary stream, avoiding pipe deadlocks.
        $output = tmpfile();
        if ($output === false) {
            throw new \RuntimeException('Impossible de préparer la commande ACL.');
        }
        try {
            $process = proc_open($arguments, [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => $output], $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Impossible de démarrer '.$arguments[0].'.');
            }
            $status = proc_close($process);
            rewind($output);
            $message = (string) stream_get_contents($output);
            if ($status !== 0) {
                throw new \RuntimeException($arguments[0].' : '.trim($message).' (code '.$status.'). Vérifiez ACL, propriétaire et outil système (acl/setfacl sous Linux).');
            }

            return $message;
        } finally {
            fclose($output);
        }
    }
}
