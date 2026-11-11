# Provenance des projets SymSvel

À la création, la CLI enregistre `backend/.symsvel/project.json` :

```json
{"schema":1,"channel":"stable"}
```

Le canal par défaut est `stable`. L'option `symsvel create-project --dev`, réservée aux mainteneurs et aux projets de crash-test, enregistre `dev`. Cette configuration est indépendante de Symfony `APP_ENV`, et ne doit jamais être écrasée automatiquement. Le résolveur Tooling lit le même contrat.

**État vérifié au 11/11/2026 :** l'écriture et la validation stricte du canal sont implémentées. La CLI applique `dev-main` par défaut aux packages SymSvel sans contrainte explicite ; Tooling prépare les dépôts VCS des modules en canal `dev` après checkpoint. Les CT XT ont validé l'installation et les migrations natives PostgreSQL/MySQL. La maintenance sélective réelle, les rollbacks et le canal `stable` après publication doivent être validés séparément. Aucun changement de canal ou migration automatique d'anciens projets n'est prévu.

Toute commande de maintenance refuse un canal absent, invalide ou symbolique avant de déléguer au Tooling. Aucun canal par défaut n'est inféré pour un projet déjà créé.

## Installation des packages

`symsvel require <package>` reprend désormais le canal du projet : en `dev`, la CLI transmet la sélection de `dev-main` au lanceur transactionnel Composer existant **uniquement pour les packages `symsvel-framework/*`**. Les dépendances tierces conservent leur résolution Composer habituelle ; en `stable`, elle conserve les contraintes normales de Composer. L'option `--dev` sur les commandes de maintenance est refusée : elle ne sert qu'à `create-project`. Les contraintes explicitement fournies par l'utilisateur restent prioritaires dans le lanceur actuel.

Pour les nouveaux modules `symsvel-framework/module-*` en canal `dev`, Tooling déclare le dépôt VCS officiel dans la transaction. `update` respecte les contraintes existantes, sans réécriture automatique de versions. La résolution GitHub/Satis réelle reste à valider sur XT.

## Mise à jour sélective

`symsvel update <package>` délègue la mise à jour au lanceur transactionnel Composer, sans changer les contraintes du `composer.json` ou du `composer.lock` depuis la CLI. En particulier, un package déjà contraint à `dev-main` dans un projet `dev` reste soumis à cette contrainte ; `stable` conserve ses contraintes de release. `symsvel remove` conserve également la cible du package. Des tests smoke couvrent les deux canaux et la préservation des contraintes explicites.

**Limite actuelle :** les smoke tests CLI vérifient la délégation et les arguments. Tooling teste en complément une résolution Composer réelle sur un dépôt Git local substitué, mais pas encore le dépôt GitHub officiel ni Satis. Ces parcours restent à valider lors des crash-tests XT.

Les contraintes explicites, qu'elles soient intégrées au nom (`vendor/package:^1.0`) ou fournies en second argument (`vendor/package ^1.0`), ne sont pas remplacées par `dev-main`. Le canal `dev` fournit la valeur par défaut uniquement lorsque la commande `require` ne précise aucune contrainte.

Une option Composer telle que `--no-interaction` après le nom du package ne constitue pas une contrainte de version : le canal `dev` reste appliqué si aucune version n'a été précisée.

## Bootstrap des modules natifs

La CLI ne déclare plus elle-même les dépôts VCS des modules pendant le bootstrap initial. Elle valide le manifeste et délègue le `require` des modules au lanceur transactionnel de Tooling, qui prépare les sources `dev` après son checkpoint Composer. Ainsi, un échec de résolution peut restaurer `composer.json` via le rollback existant. Le test smoke vérifie l'absence de mutation des repositories par la CLI. Le parcours de maintenance réel XT reste à valider.

## Protocole de crash-test XT (à exécuter après CI verte)

**Précondition :** utiliser un répertoire XT neuf, jamais un projet contenant des données à préserver. La création démarre le Setup Wizard interactif. Aucun contournement de XT ni modification manuelle du canal.

1. Dans le répertoire parent de XT, exécuter `symsvel doctor`, puis `symsvel create-project --components=all --dev` depuis un répertoire de travail neuf. Vérifier que `backend/.symsvel/project.json` contient `schema: 1` et `channel: dev` ; vérifier l'installation de BO, App, Front et des modules natifs attendus.
2. Finaliser le Setup Wizard avec une base de test dédiée, puis vérifier routes, migrations, accès BO/App, synchronisation des modules et builds.
3. Depuis la racine du projet créé, lancer `symsvel require symsvel-framework/module-test-back` sans contrainte explicite : en canal `dev`, la résolution attendue est `dev-main` via le dépôt VCS officiel. Vérifier Composer, routes et interfaces du module, ainsi que les migrations.
4. Lancer `symsvel update symsvel-framework/module-test-back` : mise à jour sélective, sans changement de canal. Vérifier que les modifications locales des composants ne sont pas écrasées.
5. Tester `symsvel remove symsvel-framework/module-test-back` et vérifier le nettoyage des migrations et des interfaces. Utiliser une base jetable et conserver un état de référence pour les comparaisons.
6. Tester séparément le canal `stable` dans un **autre projet neuf**, sans `--dev`. Ce scénario dépend de la publication effective des versions dans Satis : ne pas interpréter une release absente comme une réussite du résolveur.
7. Pour le rollback, injecter un échec **uniquement sur une copie jetable** du projet XT et comparer `composer.json`, `composer.lock`, `vendor`, les fichiers de composants et la base avant/après. Ne jamais provoquer cet échec sur Choralys ou un projet réel.

**Critères de clôture avant release :** CI verte, installation et maintenance réelles en `dev` et `stable` (si releases disponibles), migrations, rollback, builds, tests de routes et interfaces, documentation vérifiée. Le CT d'installation PostgreSQL/MySQL du 11/11/2026 ne valide pas à lui seul les opérations de maintenance et rollback. Toute anomalie doit être résolue avant publication.
