# Référence des commandes SymSvel CLI

La CLI est le point d'entrée pour créer et maintenir un projet. Cette référence doit évoluer dans le même chantier que toute commande ajoutée, modifiée ou supprimée.

| Commande | Rôle |
| --- | --- |
| `symsvel doctor` | Contrôler les prérequis locaux |
| `symsvel --help` | Afficher l'aide |
| `symsvel --version` | Afficher la version |
| `symsvel create-project --components=all|bo|app|front` | Créer un projet et lancer le Setup |
| `symsvel setup` | Reprendre le Setup Wizard local |
| `symsvel setup:finalize` | Finaliser l'initialisation |
| `symsvel make:module <name>` | Générer un module normalisé |
| `symsvel module:migrate [MonModule]` | Déléguer au Tooling la génération d'une migration pour un module (générateur Tooling en cours) |
| `symsvel require <package> [constraint]` | Installer un package |
| `symsvel update [bo|app|front|package]` | Mettre à jour sélectivement |
| `symsvel remove <package>` | Désinstaller un package |

## Exemples

```bash
symsvel doctor
symsvel create-project --components=all
symsvel create-project --components=app --directory=backend --dev
symsvel make:module catalogue --scopes=back,bo,app --vendor=symsvel-framework
# Après intégration du générateur Tooling :
symsvel module:migrate Catalogue
symsvel require symsvel-framework/module-exemple dev-main
symsvel update symsvel-framework/module-exemple
```

La CLI requiert PHP 8.4+, Composer et Git. Node.js et npm sont nécessaires aux composants Svelte. Symfony CLI est facultative.

Les commandes avancées de synchronisation, migration et récupération appartiennent à la console Symfony du Tooling. Depuis un backend installé, utiliser `php bin/console list symsvel` et `--help` pour obtenir leurs signatures exactes.

### Génération des migrations de module (PR draft)

`symsvel module:migrate [MonModule] [--workspace=chemin]` délègue à la commande Tooling `symsvel:module:migrate`. La CLI transmet le répertoire courant initial via `--cwd`, indispensable à la détection du module depuis un sous-dossier. Elle n'embarque aucune logique Doctrine. La génération actuelle ne couvre que les créations simples de tables ; les changements de tables existantes et les clés étrangères restent volontairement refusés. Le parcours complet doit être crash-testé avec un EntityManager réel avant de considérer cette fonctionnalité comme terminée.
