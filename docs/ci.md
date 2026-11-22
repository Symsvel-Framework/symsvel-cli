# Intégration continue

Depuis le 22/11/2026, la CI active `.github/workflows/ci.yml` est exécutée sur **GitHub-hosted Ubuntu 24.04**, avec PHP 8.4 installé par `shivammathur/setup-php@v2`, Composer et les tests existants. Les `push` vers `dev` et `main`, ainsi que les PR vers `main`, déclenchent la CI. `ci.yml.stub` reste une archive inactive.

Les runners du mainteneur ne servent plus ces dépôts publics : ils restent réservés à `symsvel-packages` et `symsvel-recipes` (privés). CI et crash-tests restent des validations distinctes.

