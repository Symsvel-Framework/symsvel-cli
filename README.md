# SymSvel CLI

**Votre point d'entrée dans SymSvel Framework.** Créez un projet Symfony avec les interfaces SvelteKit de votre choix, puis développez votre code métier.

> SymSvel est en cours de développement. Utilisation à vos risques et périls.

## 1. Prérequis

PHP 8.4+, Composer 2, Git, Node.js, npm et Python 3 (venv et pip). Préparez PostgreSQL, MySQL, MariaDB ou SQLite, ainsi que le pilote PDO correspondant. Symfony CLI est facultative. Aucun Docker imposé.

## 2. Installer la CLI (macOS / Linux)

~~~bash
mkdir -p "$HOME/.local/bin"
git clone --branch main https://github.com/Symsvel-Framework/symsvel-cli.git "$HOME/.local/symsvel-cli"
chmod +x "$HOME/.local/symsvel-cli/bin/symsvel"
ln -s "$HOME/.local/symsvel-cli/bin/symsvel" "$HOME/.local/bin/symsvel"
export PATH="$HOME/.local/bin:$PATH"
symsvel doctor
~~~

Ajoutez la ligne export à votre configuration de shell. Corrigez les prérequis signalés avant de continuer.

## 3. Créer un projet

~~~bash
mkdir catalogue
cd catalogue
symsvel create-project --components=all
~~~

| Option | Composants |
| --- | --- |
| bo | Backend + BO |
| app | Backend + BO + App |
| front | Backend + BO + Front |
| all | Backend + BO + App + Front |

Le Setup local s'ouvre dans le navigateur. Il configure les origines HTTP, la base et le premier administrateur, puis construit les interfaces. Préparez vos hôtes virtuels locaux ; Back doit pointer vers backend/public et les interfaces statiques vers leur build.

Pour reprendre une installation interrompue : symsvel setup.

Le canal stable est recommandé. Ajoutez --dev seulement pour expérimenter les branches de développement.

## 4. Développer et maintenir

Vous pouvez écrire du code Symfony et SvelteKit dans votre projet ou créer un module métier.

~~~bash
symsvel make:module catalogue --scopes=back,bo
symsvel update
~~~

La [référence des commandes](docs/reference/commandes.md) précise les variantes. Après l'installation, ouvrez dev-doc/site/index.html pour parcourir les guides du framework. Les guides Prise en main sont aussi disponibles dans le [dépôt Back](https://github.com/Symsvel-Framework/symsvel-back/tree/main/docs/guides).

## Mainteneurs

Les notes de conception, historiques de CT et procédures internes sont conservés dans [docs/maintainer](docs/maintainer/). Ils ne font pas partie du guide d'utilisation.

## Licence MIT et contributions

Ce dépôt est distribué sous [licence MIT](LICENSE). Le code peut être utilisé, modifié et redistribué, y compris dans des projets commerciaux ou propriétaires, dans les conditions de cette licence (notamment la conservation de la notice de copyright et de licence).

**Contribuer :** créer un fork, puis proposer une pull request vers `dev` lorsque cette branche existe, sinon vers la branche par défaut. Expliquer la modification et fournir les tests et la documentation concernés. Les mainteneurs conservent la maîtrise des revues, des validations (CI et crash-tests), de l'intégration et des releases officielles. Une PR ne garantit pas son acceptation.

**Versions officielles :** seules les versions diffusées par l'organisation [Symsvel-Framework](https://github.com/Symsvel-Framework) sont officielles. Les forks sont libres sous MIT, mais ne doivent pas être présentés comme officiellement approuvés par les mainteneurs. Cette distinction n'ajoute aucune restriction aux droits accordés par la licence MIT sur le code.

## Participer et soutenir le projet

SymSvel est un projet en cours de développement. Pour contribuer, proposer une amélioration ou échanger avec le mainteneur : **[xavier@xavier-tezza.fr](mailto:xavier@xavier-tezza.fr)**.

SymSvel vous est utile et vous souhaitez me remercier ? **Offrez-moi une bière ! 🍺** (lien PayPal à compléter avant l'ouverture publique).

