# Intégration continue

Depuis le 22/11/2026, la CI active `.github/workflows/ci.yml` est exécutée sur **GitHub-hosted Ubuntu 24.04**, avec PHP 8.4 installé par `shivammathur/setup-php@v2`, Composer et les tests existants. Les `push` vers `dev` et `main`, ainsi que les PR vers `main`, déclenchent la CI. `ci.yml.stub` reste une archive inactive.

Les runners du mainteneur ne servent plus ces dépôts publics : ils restent réservés à `symsvel-packages` et `symsvel-recipes` (privés). CI et crash-tests restent des validations distinctes.


Le job `windows-bootstrap` sur Windows natif teste le lanceur CMD, doctor, la résolution Composer et les primitives de bootstrap. Il ne qualifie pas encore le parcours complet, les transactions ou les ACL NTFS multi-comptes. Les jobs Unix existants restent inchangés.

La détection Windows accepte les scripts CMD/BAT présents sur le PATH sans exiger leur bit exécutable POSIX. Composer PHAR est lancé directement par PHP lorsque disponible (Composer-Setup et setup-php), sans interpoler les arguments dans une commande shell.

La suppression du Git upstream sous Windows traite les objets en lecture seule dans ce seul checkout neuf. Le test Windows crée explicitement un objet en lecture seule. Aucun élargissement des ACL NTFS du projet.

Le job Windows crée un compte HTTP local jetable pour vérifier les ACL NTFS ciblées, leur héritage sur un nouveau fichier, le refus des hardlinks avant toute mutation, la préservation de la base SQLite et la répétabilité. L'exécution CLI normale ne crée aucun compte et ne demande pas de privilèges administrateur ; le compte développeur doit pouvoir modifier les DACL ciblées. Les comptes de service/groupes ne sont pas implicitement sélectionnés.
