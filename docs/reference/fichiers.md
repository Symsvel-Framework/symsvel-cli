# Fichiers et registres

| Chemin | Fonction |
| --- | --- |
| `backend/composer.json` | Packages et dépendances |
| `backend/config/symsvel-native-modules.json` | Modules natifs requis |
| `backend/.env.local` | Configuration locale, notamment `DATABASE_URL` |
| `backend/config/routes/` | Imports de routes Symfony et recettes Flex |
| `backend/migrations/` | Migrations Doctrine compilées |
| `backend/.symsvel/` | Registres et états techniques |
| `.symsvel/setup.json` | État de l'initialisation du workspace |
| `.symsvel/migration-recovery.json` | Journal de récupération des migrations |
| `backend/.symsvel/cleanup-pending.json` | Nettoyage différé des checkpoints |

Ne pas éditer les registres générés sans procédure explicite. Ne jamais versionner secrets, sauvegardes ou checkpoints.
