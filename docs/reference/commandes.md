# Commandes SymSvel CLI

La CLI orchestre les opérations locales ; Composer et Tooling exécutent les opérations techniques.

| Commande | Utilisation |
| --- | --- |
| symsvel doctor | Vérifier les prérequis |
| symsvel --version | Afficher la version |
| symsvel create-project --components=all | Créer le projet |
| symsvel setup | Reprendre le Setup |
| symsvel setup:finalize | Finaliser la configuration locale |
| symsvel make:module NOM --scopes=back,bo | Créer un module métier |
| symsvel module:migrate NOM | Générer les migrations source du module |
| symsvel require PACKAGE [CONTRAINTE] | Installer un package |
| symsvel update [PACKAGE] | Mettre à jour les dépendances Composer |
| symsvel update back | Actualiser le squelette Symfony |
| symsvel update bo / app / front | Actualiser une interface (un argument par commande) |
| symsvel remove PACKAGE | Retirer un module facultatif |
| symsvel documentation:build-dev | Reconstruire le site développeur local |
| symsvel documentation:sync | Mettre à jour la documentation privée |
| symsvel permissions:fix | Réparer les ACL locales cache/log |

Pour toutes les options, utilisez symsvel --help.

## Canaux

Le canal stable est choisi par défaut. L'option --dev lors de la création sélectionne les branches de développement. Ce choix est conservé dans le projet, indépendamment de APP_ENV. N'ajoutez pas --dev à update ou require.

## Permissions locales (CLI 0.3.1+)

Sur macOS/Linux, la CLI prépare les ACL de cache et logs pour le compte PHP-FPM détecté avant l'installation. Lorsque plusieurs comptes sont présents ou si le serveur est arrêté, indiquez celui de votre projet :

~~~bash
symsvel permissions:fix --http-user=COMPTE
~~~

Vous pouvez aussi définir SYMSVEL_HTTP_USER avant create-project. Linux nécessite setfacl (paquet acl), PHP doit disposer de POSIX. Les ACL ne remplacent pas les droits de traversée des répertoires parents. Si des fichiers appartiennent à un autre utilisateur, une intervention ciblée de l'administrateur local peut être nécessaire. N'exécutez pas la CLI en root et n'utilisez pas chmod 777.

## Documentation

Après l'installation, ouvrez dev-doc/site/index.html. Pour la reconstruire, lancez symsvel documentation:build-dev.

Pour les procédures internes et les historiques, consultez [les notes de maintenance](../maintainer/).
