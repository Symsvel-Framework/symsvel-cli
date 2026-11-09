# SymSvel CLI

Point d'entrée autonome des projets SymSvel (PHP 8.4+, Composer, Git ; Node.js/npm pour les interfaces SvelteKit). La CLI orchestre les commandes utilisateur ; **le Tooling conserve les moteurs d'installation, synchronisation et transaction**. Le Setup PostgreSQL en deux phases est coordonné avec Tooling #63, Back #40 et Admins #4 ; la validation finale nécessite les crash-tests XT après merge.

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

Le canal est choisi **à la création** et écrit dans `backend/.symsvel/project.json` : `stable` par défaut, `dev` avec `--dev`. Il est indépendant de `APP_ENV`. Les commandes de maintenance exigent une provenance valide et ne déduisent pas automatiquement un canal pour un ancien projet. Le canal `dev` sélectionne par défaut `dev-main` pour les packages SymSvel sans contrainte explicite ; une contrainte fournie par l'utilisateur est conservée. Voir [le contrat de provenance et le protocole XT](docs/reference/provenance.md).

## Maintenance sélective

```bash
symsvel require symsvel-framework/module-test-back
symsvel update symsvel-framework/module-test-back
symsvel remove symsvel-framework/module-test-back
symsvel update bo
```

Les opérations de packages utilisent le **lanceur transactionnel Composer du Tooling**, sans réimplémentation dans la CLI. Les commandes Composer natives ne sont pas interceptées. `symsvel update bo|app|front` utilise le cycle de vie natif des interfaces : analyse des fichiers gérés, préservation des modifications locales non conflictuelles, refus des collisions. Ce parcours est distinct de `symsvel update <package>` et de la commande Symfony globale `symsvel:update`.

Le canal `dev` ne garantit pas à lui seul que GitHub/Satis est accessible : les tests d'intégration réseau réels font partie des crash-tests XT. Les restaurations BDD ne sont garanties que lorsque le checkpoint est effectivement disponible, notamment avec PostgreSQL ou SQLite. Voir [la documentation transactionnelle du Tooling](https://github.com/Symsvel-Framework/symsvel-tooling/blob/feat/60-update-init-simplification/docs/composer-transactions.md).

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

Avant de considérer les PR CLI #10 et Tooling #62 terminées : tests, CI, vérification documentaire et crash-tests XT sur Mac, notamment Setup Wizard, modules natifs, synchronisation, migrations, mises à jour, retrait et rollbacks. **Une CI verte ne remplace pas les crash-tests XT après merge sur main/dev-main.**


### Setup PostgreSQL en deux phases

`create-project` prépare les sources et modules sans build Svelte. Le Setup local s'effectue ensuite en deux écrans : configuration projet, domaine local et connexion **PostgreSQL** (détection de version serveur), création de la base et migrations Composer ; puis création directe du premier administrateur par le module Admins, sans staging de mot de passe. Les builds statiques BO/App/Front interviennent uniquement après les migrations et la vérification de l'administrateur. La finalisation est relançable après un échec. MySQL, MariaDB et SQLite seront traités dans un chantier multibase distinct après les CT PostgreSQL. i18n et 2FA sont également différés.
