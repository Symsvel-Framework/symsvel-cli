# Fichiers et registres

| Chemin | Fonction |
| --- | --- |
| `backend/composer.json` | Packages et dépendances |
| `backend/config/symsvel-native-modules.json` | Modules natifs requis |
| `backend/.env.local` | Configuration locale, notamment `DATABASE_URL` |
| `backend/config/routes/` | Imports de routes Symfony et recettes Flex |
| `backend/migrations/` | Migrations Doctrine compilées (vérifier le chemin effectif dans le projet installé) |
| `backend/.symsvel/project.json` | Canal de provenance `stable` ou `dev` du projet |
| `backend/.symsvel/components.json` | Provenance et empreintes des interfaces natives |
| `backend/.symsvel/` | Registres techniques du backend et checkpoints |
| `.symsvel/setup.json` | État de l'initialisation du workspace, à la racine du projet (hors `backend/`) |
| `.symsvel/migration-recovery.json` | Journal de récupération des migrations, à la racine du workspace |
| `backend/.symsvel/cleanup-pending.json` | Nettoyage différé des checkpoints |

Le Wizard local fonctionne sans jeton de setup ; l'ancien fichier `.symsvel/setup-token` ne fait plus partie du parcours.

Ne pas éditer les registres générés sans procédure explicite. Ne jamais versionner secrets, sauvegardes ou checkpoints.
