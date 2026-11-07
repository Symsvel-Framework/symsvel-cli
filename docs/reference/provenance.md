# Provenance des projets SymSvel

À la création, la CLI enregistre `backend/.symsvel/project.json` :

```json
{"schema":1,"channel":"stable"}
```

Le canal par défaut est `stable`. L'option `symsvel create-project --dev`, réservée aux mainteneurs et aux projets de crash-test, enregistre `dev`. Cette configuration est indépendante de Symfony `APP_ENV`, et ne doit jamais être écrasée automatiquement. Le résolveur Tooling lit le même contrat.

**État du chantier :** l'écriture initiale et la validation stricte de la provenance avant `require`, `update` et `remove` sont implémentées. La résolution Composer des contraintes et dépôts selon ce canal, ainsi que l'unification des parcours, restent à terminer avant merge. Aucun changement de canal ou migration d'anciens projets n'est prévu.

Toute commande de maintenance refuse un canal absent, invalide ou symbolique avant de déléguer au Tooling. Aucun canal par défaut n'est inféré pour un projet déjà créé.

## Installation des packages

`symsvel require <package>` reprend désormais le canal du projet : en `dev`, la CLI transmet la sélection de `dev-main` au lanceur transactionnel Composer existant **uniquement pour les packages `symsvel-framework/*`**. Les dépendances tierces conservent leur résolution Composer habituelle ; en `stable`, elle conserve les contraintes normales de Composer. L'option `--dev` sur les commandes de maintenance est refusée : elle ne sert qu'à `create-project`. Les contraintes explicitement fournies par l'utilisateur restent prioritaires dans le lanceur actuel.

Cette étape ne couvre pas encore l'actualisation des dépôts Composer pour les packages ajoutés après la création, ni la sélection de versions pendant `update`. Ces points restent ouverts dans la PR draft.

## Mise à jour sélective

`symsvel update <package>` délègue la mise à jour au lanceur transactionnel Composer, sans changer les contraintes du `composer.json` ou du `composer.lock` depuis la CLI. En particulier, un package déjà contraint à `dev-main` dans un projet `dev` reste soumis à cette contrainte ; `stable` conserve ses contraintes de release. `symsvel remove` conserve également la cible du package. Des tests smoke couvrent les deux canaux et la préservation des contraintes explicites.

**Limite actuelle :** ces tests vérifient la délégation et les arguments transmis, pas encore la résolution réelle d'un nouveau module depuis son dépôt VCS. Cette dernière reste à valider dans Tooling et lors des crash-tests.
