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
| `symsvel documentation:sync` | Assemblage documentaire privé manuel (Tooling), hors Setup et sans MkDocs |
| `symsvel documentation:build-dev` | Reconstruire localement le site développeur MkDocs depuis les docs des composants et modules installés |
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

La CLI requiert PHP 8.4+, Composer et Git. Node.js/npm sont nécessaires aux composants Svelte, et Python 3 est requis lors de `create-project` pour construire `dev-doc/site/` via un venv MkDocs local. Symfony CLI reste facultative. `dev-doc` est exclu du déploiement de production.

Les commandes avancées de synchronisation, migration et récupération appartiennent à la console Symfony du Tooling. Depuis un backend installé, utiliser `php bin/console list symsvel` et `--help` pour obtenir leurs signatures exactes.

### Génération des migrations de module (PR draft)

`symsvel module:migrate [MonModule] [--workspace=chemin]` délègue à la commande Tooling `symsvel:module:migrate`. La CLI transmet le répertoire courant initial via `--cwd`, indispensable à la détection du module depuis un sous-dossier. Elle n'embarque aucune logique Doctrine. La génération actuelle ne couvre que les créations simples de tables ; les changements de tables existantes et les clés étrangères restent volontairement refusés. Le parcours complet doit être crash-testé avec un EntityManager réel avant de considérer cette fonctionnalité comme terminée.

### Génération de module dans le workspace Framework

À la racine du checkout contenant `symsvel-cli/` et `symsvel-tooling/`, `symsvel make:module mail --scopes=back,bo` détecte automatiquement le Maker de `symsvel-tooling/src/Module/ModuleMaker.php`. Le Maker de Tooling crée le dépôt frère `symsvel-module-mail/` à la racine du workspace Framework. `--workspace=/chemin/absolu/vers/SymSvel` reste disponible depuis un autre répertoire. La génération depuis un projet client continue de déléguer au Tooling installé dans son backend et conserve son organisation propre.

### Confidentialité du Setup (19/11/2026)

Le lancement du serveur local relaie au processus PHP uniquement la
configuration d'environnement nécessaire et `SYMSVEL_SETUP_NPM` lorsque
l'exécutable npm a été trouvé. Le diagnostic temporaire qui écrivait le
`PATH` local dans `.symsvel/npm-trace.log` a été retiré : la CLI ne doit
pas conserver les chemins ni variables d'environnement du mainteneur
lors d'une installation normale. La reprise du Wizard et l'exécution
de npm ne changent pas. Test smoke et CI obligatoires, puis CT XT.

Le test smoke interdit la persistance de la trace temporaire et du
`PATH` sérialisé. La consultation en mémoire de `PATH` pour localiser
`npm` reste nécessaire au fonctionnement du Wizard et n'est pas
considérée comme une fuite tant qu'aucun journal n'est créé.

### Documentation native accessible au BO (19/11/2026)

La CLI expose `symsvel documentation:sync` et délègue uniquement à la
commande interne `symsvel:documentation:sync` de Tooling. L'opération
doit être lancée volontairement après l'installation ou la modification
des modules : elle rassemble leurs documents déclarés dans
`backend/var/symsvel/documentation.json`, hors espace public.
Le Setup, l'installation initiale, le build Svelte et `symsvel update`
n'invoquent jamais implicitement ce traitement. Ni Python, ni pip,
ni MkDocs ne sont installés. Le BO consomme le corpus uniquement par
un endpoint authentifié du module propriétaire. Le test smoke vérifie le routage CLI sans publier l'index privé pendant l'installation.

Le **site MkDocs développeur** suit un cycle distinct : `create-project`
le construit dès l'installation des composants, puis le reconstruit après
l'installation et la synchronisation des modules natifs, avant le Wizard.
Tooling le régénère également **automatiquement après une opération
`symsvel require`, `symsvel update` ou `symsvel remove` réussie**, ainsi
qu'après une mise à jour globale ; en cas de rollback, aucune publication
documentaire n'a lieu. La commande `symsvel documentation:build-dev` permet
de le régénérer manuellement après une édition directe de documents.
Seules les pages de scope
`dev` sont copiées dans `dev-doc/docs/symsvel/`, jamais les guides
`admin` ou `user`. Le site statique demeure strictement local.

### Préparation release CLI `v0.2.0` (20/11/2026)

Le tag `v0.2.0` a été posé le 20/11/2026. Les correctifs du build MkDocs et de son rafraîchissement sont publiés sous `v0.2.1` ; la CLI doit donc annoncer `SymSvel CLI 0.2.1` via `symsvel --version` et le smoke test contrôle cette valeur. La branche de développement conserve son suffixe `-dev`. Le smoke test vérifie la sortie exacte. La publication du tag et de la GitHub Release reste distincte du commit et doit être vérifiée avant le CT utilisateur sans `--dev`.
