# Génération des migrations d'un module (chantier en cours)

## Commande cible

```bash
symsvel module:migrate MonModule
cd modules/mon-module
symsvel module:migrate
```

Cette commande **n'est pas encore disponible** sur la branche : la présente documentation prépare le raccordement à la commande Tooling en cours de développement.

## Contrat validé

- L'argument est facultatif seulement si le répertoire courant se trouve dans un module SymSvel reconnu.
- Un argument explicite désigne le module à traiter, même depuis le dossier d'un autre module.
- La CLI conserve le répertoire courant d'origine lors de la délégation pour permettre la résolution du module.
- Le Tooling reste l'unique responsable de la découverte, de la validation du module, de la sélection des entités Doctrine et de la génération des migrations.
- Les sources sont écrites dans `modules/<slug>/Migration/` ; aucune compilation ni exécution automatique.
- Un module non identifié ou un contexte ambigu entraîne une erreur, sans modification de fichiers.

## Validation attendue

Le raccordement ne doit pas réimplémenter le moteur Doctrine dans la CLI. Tests de parsing et de délégation, CI verte, puis crash-tests depuis un module créé avec `symsvel make:module`. Documentation CLI et Tooling à maintenir ensemble.

Suivi Tooling : [PR draft #69](https://github.com/Symsvel-Framework/symsvel-tooling/pull/69).
