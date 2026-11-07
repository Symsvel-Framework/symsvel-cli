# Provenance des projets SymSvel

À la création, la CLI enregistre `backend/.symsvel/project.json` :

```json
{"schema":1,"channel":"stable"}
```

Le canal par défaut est `stable`. L'option `symsvel create-project --dev`, réservée aux mainteneurs et aux projets de crash-test, enregistre `dev`. Cette configuration est indépendante de Symfony `APP_ENV`, et ne doit jamais être écrasée automatiquement. Le résolveur Tooling lit le même contrat.

**État du chantier :** l'écriture initiale est implémentée. Le raccordement de `require`, `update`, de Composer et des installations natives au canal persistant reste à terminer avant merge. Aucun changement de canal ou migration d'anciens projets n'est prévu.
