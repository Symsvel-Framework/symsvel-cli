# SymSvel CLI

Point d'entrée autonome des projets SymSvel (PHP 8.4+, Composer, Git ; Node.js/npm pour les interfaces SvelteKit ; Python 3 pour construire automatiquement la documentation développeur locale avec MkDocs). La CLI orchestre les commandes utilisateur ; **le Tooling conserve les moteurs d'installation, synchronisation et transaction**. Le Setup Wizard local en deux phases est coordonné entre CLI, Tooling, Back et modules natifs. Les crash-tests XT du 11/11/2026 ont validé le parcours d'installation et les migrations natives sous PostgreSQL et MySQL ; les autres parcours de maintenance et les autres SGBD restent à valider séparément.

## Bilan CT XT multi-SGBD du 11 novembre 2026

Les crash-tests d'installation sur le workspace XT vierge ont abouti avec **PostgreSQL, MySQL, SQLite et MariaDB**. Le parcours validé comprend la création/configuration de la base, l'exécution des migrations natives Doctrine Schema (Admins, Roles, ACLs, Users), la création du premier administrateur, les builds Svelte et le routing. Pour MariaDB, le contrôle SQL a confirmé les tables `doctrine_migration_versions`, `symsvel_acl_rules`, `symsvel_admins`, `symsvel_roles` et `symsvel_users`, ainsi qu'un administrateur activé avec le rôle `ROLE_DEV`. Ne pas publier d'identifiants ni de hashes issus des CT.

**Incident de CT distinct du fonctionnement multi-SGBD :** une tentative a été perturbée par un terminal de Setup précédent resté ouvert pendant la recréation du workspace. Les états `running` et `recovering` observés dans le nouveau workspace et le refus du bootstrap ne constituent pas une preuve de défaut Doctrine ou MariaDB. L'interaction exacte entre processus n'a pas été tracée ; la protection contre deux installations concurrentes reste à auditer, sans modification architecturale décidée à ce stade. Pour les prochains CT, arrêter tous les processus de Setup avant de supprimer/recréer XT.

**Périmètre de validation :** ces résultats valident l'installation initiale et les contrôles fonctionnels mentionnés, pas les parcours `require/update/remove`, les rollbacks, le canal stable via Satis, la 2FA ou l'i18n. Avant publication : vérifier les CI sur les commits de release, les versions et dépendances inter-dépôts, les documents et les parcours de maintenance encore requis. Le suivi npm temporaire doit être traité explicitement avant release.

## Installation locale de la CLI

```bash
chmod +x bin/symsvel
ln -s "$(pwd)/bin/symsvel" /usr/local/bin/symsvel
symsvel doctor
```

Le dossier de destination doit exister et être accessible en écriture. L'accès aux dépôts privés SymSvel et la configuration Composer/GitHub sont nécessaires selon le canal.

## Créer un projet

```bash
symsvel doctor
symsvel create-project --components=all
# Derniers commits de développement, pour maintenance et crash-tests :
symsvel create-project --components=all --dev
```

`--components=bo|app|front|all` installe le BO et les interfaces supplémentaires demandées. Symfony est installé dans `backend/` ; `bo/`, `app/` et `front/` sont ses dossiers frères. La création délègue l'installation au Tooling et démarre le Setup Wizard local. Une création partielle n'est pas effacée automatiquement.

Le canal est choisi **à la création** et écrit dans `backend/.symsvel/project.json` : `stable` par défaut, `dev` avec `--dev`. Il est indépendant de `APP_ENV`. Les commandes de maintenance exigent une provenance valide et ne déduisent pas automatiquement un canal pour un ancien projet. Le canal `dev` récupère `dev-dev` pour Back, Core et Tooling lors de la création ; les modules natifs restent sur `dev-main` (branche Git `main`). Pour les packages SymSvel ajoutés sans contrainte explicite, la maintenance conserve provisoirement la résolution `dev-main` ; une contrainte fournie par l'utilisateur est conservée. Voir [le contrat de provenance et le protocole XT](docs/reference/provenance.md).

## Maintenance sélective

```bash
symsvel require symsvel-framework/module-test-back
symsvel update symsvel-framework/module-test-back
symsvel remove symsvel-framework/module-test-back
symsvel update bo
```

Les opérations de packages utilisent le **lanceur transactionnel Composer du Tooling**, sans réimplémentation dans la CLI. Les commandes Composer natives ne sont pas interceptées. `symsvel update bo|app|front` utilise le cycle de vie natif des interfaces : analyse des fichiers gérés, préservation des modifications locales non conflictuelles, refus des collisions. Ce parcours est distinct de `symsvel update <package>` et de la commande Symfony globale `symsvel:update`.

Le canal `dev` ne garantit pas à lui seul que GitHub/Satis est accessible : les tests d'intégration réseau réels font partie des crash-tests XT. Les restaurations BDD ne sont garanties que lorsque le checkpoint est effectivement disponible, notamment avec PostgreSQL ou SQLite. Voir [la documentation transactionnelle du Tooling](https://github.com/Symsvel-Framework/symsvel-tooling/blob/main/docs/composer-transactions.md).

## Setup et génération de modules

```bash
symsvel setup
symsvel setup:finalize
symsvel make:module catalogue --vendor=acme --scopes=back,bo,app
```

Le Setup Wizard configure le projet localement après l'installation des composants. Le ModuleMaker est implémenté dans Tooling et partagé avec la CLI. Depuis un workspace SymSvel, les modules sont générés dans `modules/<nom>`, jamais dans `vendor/`. `--workspace=<chemin>` permet de choisir une destination. Le scope `back` est toujours inclus.

## Références et validation

- [Commandes](docs/reference/commandes.md)
- [Fichiers et registres](docs/reference/fichiers.md)
- [Provenance et crash-tests XT](docs/reference/provenance.md)

Avant toute release CLI : tests, CI, documentation et crash-tests XT des parcours concernés, notamment maintenance sélective, retrait et rollbacks. **Une CI verte ne remplace pas les crash-tests XT après merge sur les branches concernées.**


### Setup local en deux phases

`create-project` prépare les sources et modules sans build Svelte. Le Setup local s'effectue ensuite en deux écrans : configuration projet, domaine local et connexion au **SGBD choisi** (détection de version serveur lorsque disponible), création de la base et migrations Composer ; puis création directe du premier administrateur par le module Admins, sans staging de mot de passe. Les builds statiques BO/App/Front interviennent uniquement après les migrations et la vérification de l'administrateur. La finalisation est relançable après un échec. Les CT PostgreSQL et MySQL sont validés au 11/11/2026 pour l'installation et les migrations natives. MariaDB et SQLite ne sont pas encore validés par ces CT. i18n et 2FA restent des chantiers distincts.

### Correctif CT XT : synchronisation et builds

Pendant `create-project`, la synchronisation explicite `scripts/symsvel-module-sync.php` reste sous `SYMSVEL_DEFER_BUILDS=1`, comme les opérations Composer et l'installation des modules natifs. Le hook Composer du backend peut lancer des builds hors de ce contexte ; la CLI ne doit donc jamais restaurer la variable avant la dernière synchronisation initiale. Les builds initiaux sont réservés à `symsvel:setup:complete`. Régression couverte dans `tests/smoke.php` ; parcours initial validé sur XT pour PostgreSQL et MySQL ; les régressions futures nécessitent de nouveaux CT.

### Wizard local sans jeton

`symsvel create-project` ouvre le Setup sur `127.0.0.1` sans jeton ni saisie de secret temporaire. `symsvel setup` reprend également un état `database_ready` après une erreur de finalisation, sans réinstaller ni relancer les migrations. Le formulaire est réservé au loopback ; le contrôle CSRF des actions POST reste assuré côté Back. Le code CLI ne doit ni lire `.symsvel/setup-token` ni afficher de jeton.

Lors du lancement du Wizard, la CLI résout `npm` depuis son `PATH` et transmet `SYMSVEL_SETUP_NPM` ainsi que l'environnement au serveur PHP local. Aucun chemin Node/Homebrew n'est codé en dur.

### Diagnostic temporaire npm du Setup (09/11/2026)

Pendant le crash-test local, le parcours Setup ajoute des traces dans `.symsvel/npm-trace.log` à la racine du workspace. Les étapes CLI, contrôleur et Tooling journalisent l'horodatage, le PID, le chemin npm et le PATH utiles au diagnostic ; Tooling journalise également la commande de build choisie et son code de sortie. Aucun secret ni environnement complet n'est journalisé. Ces traces sont temporaires, réservées au diagnostic local ; leur retrait constitue un nettoyage à vérifier avant release. Ne pas publier ce journal.

### CI GitHub et runner Docker local (10/11/2026)

Deux configurations sont conservées : `.github/workflows/ci.yml.stub` contient la CI GitHub-hosted d'origine (`ubuntu-latest` et `shivammathur/setup-php@v2`), désactivée par son extension `.stub` ; `.github/workflows/ci.yml` est la CI active sur le runner Docker ARM64 de l'organisation (`self-hosted`, `linux`, `ARM64`, `symsvel`). Elle utilise PHP 8.4 préinstallé dans l'image `symsvel-runner:latest` et vérifie explicitement sa version avant les quatre tests PHP inchangés. L'action `setup-php` échouait sur l'image Debian PHP utilisée par le runner local.

Pour revenir à la CI GitHub hébergée, remplacer `ci.yml` par le contenu de `ci.yml.stub` ; ne pas activer les deux configurations simultanément si l'objectif est d'économiser les minutes GitHub. Le runner local persistant est réservé aux dépôts et contributions de confiance. La validation finale requiert une CI verte sur le runner Docker et les crash-tests XT applicables.
