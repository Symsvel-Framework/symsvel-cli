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

`create-project` appelle `composer create-project symsvel-framework/back backend`, puis `php bin/console symsvel:install`. Les projets partiellement créés ne sont jamais supprimés automatiquement.

Le mode `--dev` crée le squelette depuis `dev-main`, résout Core et Tooling sur `dev-main` et lance l'installation avec `APP_ENV=core`. Le mode normal conserve les releases stables. Un nouveau répertoire est nécessaire pour chaque essai.

Les opérations de packages délèguent au lanceur transactionnel **déjà fourni par le Tooling** (`backend/scripts/symsvel-composer-transaction.php`). Aucun moteur transactionnel n'est dupliqué.

**Limite actuelle :** ce lanceur exécute Composer avec `--no-scripts --no-plugins`. Les recettes Flex et la synchronisation métier ne sont donc pas encore orchestrées par ce parcours. Ne pas considérer l'installation de modules comme fonctionnellement complète avant les crash-tests et l'adaptation du Tooling.

La CLI Symfony n'est pas nécessaire. Les commandes Composer et Symfony natives restent disponibles pour les crash-tests du framework.
