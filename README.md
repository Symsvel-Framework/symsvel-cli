# SymSvel CLI

CLI autonome pour les projets SymSvel. **Prototype V1 non publié**, PHP >= 8.4 requis.

## Installation locale (Mac / Linux)

Cloner le dépôt puis rendre le lanceur exécutable et le placer dans le PATH :

```bash
chmod +x bin/symsvel
ln -s "$(pwd)/bin/symsvel" /usr/local/bin/symsvel
symsvel doctor
```

Le chemin `/usr/local/bin` doit exister et être accessible en écriture. Aucun changement système automatique n'est effectué par la CLI.

## Commandes

```bash
symsvel create-project --components=all
symsvel create-project --components=all --dev
symsvel require symsvel-framework/module-test-back
symsvel update symsvel-framework/module-test-back
symsvel remove symsvel-framework/module-test-back
```

`create-project` appelle `composer create-project symsvel-framework/back backend`, puis `php bin/console symsvel:install`. La configuration BDD reste assurée par le setup web existant, avant l'accès au BO. Les projets partiellement créés ne sont jamais supprimés automatiquement.

Le mode `--dev` crée le squelette depuis `dev-main`, résout Core et Tooling sur `dev-main` et lance l'installation dans l'environnement Symfony standard (`dev`). Le mode normal conserve les releases stables. Un nouveau répertoire est nécessaire pour chaque essai.

Les opérations de packages délèguent au lanceur transactionnel **déjà fourni par le Tooling** (`backend/scripts/symsvel-composer-transaction.php`). Aucun moteur transactionnel n'est dupliqué.

**Limite actuelle :** Composer exécute les scripts et plugins, dont Flex et la synchronisation des modules. L'orchestration transactionnelle des migrations Doctrine et de la restauration de la base reste à finaliser avant les crash-tests complets.

La CLI Symfony n'est pas nécessaire. Les commandes Composer et Symfony natives restent disponibles pour les crash-tests du framework.

## Créer un module

Le ModuleMaker est implémenté exclusivement dans `symsvel-framework/tooling`.
La CLI autonome lui délègue la génération, sans maintenir de deuxième générateur.

Depuis la racine du framework SymSvel (ou un sous-dossier) :

```bash
symsvel make:module documents --scopes=back,front
```

Depuis la racine d'un projet SymSvel (ou son dossier `backend/`) :

```bash
symsvel make:module catalogue --vendor=acme --scopes=bo,app
```

Le module est créé dans `<workspace>/modules/<nom>`. Le scope `back` est toujours présent ; les profils historiques `bo`, `app`, `front`, `all` restent acceptés. La commande accepte `--workspace=<chemin>` pour choisir explicitement la destination.

La commande projet nécessite le Tooling installé dans le backend. La commande framework utilise le Maker de `framework/tools/src/Module/ModuleMaker.php` ; aucun backend Symfony n'est nécessaire dans ce cas.
