# SymSvel CLI

<!-- symsvel-brand:start -->
<p align="center">
  <a href="https://symsvel.com/"><img src="https://raw.githubusercontent.com/Symsvel-Framework/symsvel-tooling/07264829c404bcaecf1d8ce25c6a71e5beab2ae9/resources/documentation/theme/mark.png" alt="SymSvel Framework" width="48"></a>
  &nbsp;&nbsp;
  <a href="https://doc.symsvel.com/"><img src="https://raw.githubusercontent.com/Symsvel-Framework/symsvel-tooling/07264829c404bcaecf1d8ce25c6a71e5beab2ae9/resources/documentation/theme/zeko.webp" alt="Zeko, le gecko SymSvel" width="88"></a>
</p>
<p align="center"><a href="https://symsvel.com/">Site officiel</a> · <a href="https://doc.symsvel.com/">Documentation développeur</a></p>
<!-- symsvel-brand:end -->

**Votre point d'entrée dans SymSvel Framework.** Créez un projet Symfony avec les interfaces SvelteKit de votre choix, puis développez votre code métier.

> SymSvel est en cours de développement. Utilisation à vos risques et périls.

## 1. Prérequis

PHP 8.4+, Composer 2, Git, Node.js, npm et Python 3 (venv et pip). Préparez PostgreSQL, MySQL, MariaDB ou SQLite, ainsi que le pilote PDO correspondant. Symfony CLI est facultative. Aucun Docker imposé.

### Systèmes pris en charge

macOS et Linux sont les parcours validés. Windows natif est en préparation, sans obligation de WSL2 ni Docker ; le parcours WSL2 n'est pas encore qualifié non plus. Consultez le [guide de préparation Windows](https://github.com/Symsvel-Framework/symsvel-back/blob/main/docs/guides/01-getting-started/01a-windows.md) avant toute tentative d'installation sur Windows.

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

### Windows natif (qualification en cours, branche dev)

Dans PowerShell, après installation des prérequis documentés :

~~~powershell
git clone --branch dev https://github.com/Symsvel-Framework/symsvel-cli.git "$HOME\symsvel-cli"
$env:Path = "$HOME\symsvel-cli\bin;$env:Path"
symsvel.cmd doctor
~~~

Ajoutez le dossier bin au PATH utilisateur pour les prochains terminaux. Utilisez le Composer-Setup officiel (composer.bat à côté de composer.phar), Git, Node/npm et un vrai Python accessible via python.exe. Le lanceur natif `symsvel.cmd` ne nécessite pas de modifier la politique d'exécution PowerShell. Ce parcours permet la qualification initiale du doctor ; la création complète et les mises à jour restent en cours de qualification. Le Setup intégré tourne sous le compte CLI ; aucune ACL NTFS n'est modifiée automatiquement. Un serveur HTTP sous un compte distinct nécessite des ACL ciblées, non encore automatisées.

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

