# Nyx Pathfinder Manager — Codex des héros

Gestionnaire privé de personnages Pathfinder 2e / Remaster, en français, fonctionnant directement sur Apache et PHP. Application indépendante, non affiliée à Paizo.

## Installation actuellement déployée

- Projet : `/home/clients/17f80a4eac00cf168dafe6353807f683/sites/nyx-off.dev/others/pathfinder`
- URL : https://nyx-off.dev/others/pathfinder/
- Dépôt : https://github.com/Nyx-Off/Nyx-PathFinder-Manager
- PHP 8.4 ; MySQL actif sur `k599zx_NyxPathfinderManager`, hôte `k599zx.myd.infomaniak.com`. La base SQLite initiale est conservée comme sauvegarde.
- Compte initial : ouvrir le lien privé conservé dans `storage/setup-link.txt`. Il permet de choisir son adresse et son mot de passe. Après création du premier utilisateur, l’initialisation est fermée. Ne pas publier ce lien.
- Alternative CLI : `php bin/user.php adresse@example.com`, puis saisir le mot de passe sur l’entrée standard. Ne jamais placer de mot de passe dans les arguments de commande. Pour masquer la saisie avec un terminal interactif : `read -rs PASSWORD_INPUT; printf '%s\n' "$PASSWORD_INPUT" | php bin/user.php adresse@example.com; unset PASSWORD_INPUT`.

## Fonctionnalités

- Comptes privés, création guidée en onze étapes, liste, archivage, duplication, suppression confirmée.
- Attributs et boosts partiels, maîtrises, compétences et connaissances personnalisées, perception, sauvegardes, CA, DD de classe et de sorts.
- Décomposition des calculs et cumul centralisé des bonus d’objet, statut, circonstance et non typés.
- PV, temporaires, dégâts, soins, annulation des changements récents compatibles, conditions, ressources et modificateurs temporaires.
- Armes équipées, maîtrise par arme, traits agile/finesse, attaques multiples, dés de dégâts et runes de puissance ; armure, plafond de Dextérité et bouclier avec blocage.
- Inventaire, quantités, consommation, investissement, charges, encombrement, bourse en cuivre entier, conversion, transactions annulables et transferts atomiques entre personnages du même propriétaire.
- Sorts préparés, spontanés, innés, focus, tours de magie, traditions, intensification documentée, emplacements et lancement avec consommation atomique.
- Montée de niveau : PV, boosts, maîtrises sélectionnées, dons, capacités, sorts et emplacements ; historique et export de l’état précédent. XP ou jalons.
- Dons, capacités, actions, langues, sens, résistances/faiblesses/immunités et déplacements secondaires documentables.
- Notes catégorisées, journal daté, portraits privés contrôlés et réencodés, import/export JSON versionné, impression, mode combat, recherche dans les collections.
- Repos avec prévisualisation des récupérations sélectionnées ; modifications enregistrées immédiatement après validation. Les formulaires conservent un brouillon local dans l’onglet du navigateur et préviennent avant un rechargement non enregistré.

## Périmètre des règles

Il s’agit d’un gestionnaire configurable avec moteur de calcul, **pas d’un catalogue exhaustif de tous les livres ni d’un validateur de builds complet**. Le joueur choisit ascendance, héritage, historique et classe, renseigne leurs PV et les maîtrises accordées. Les quotas de compétences, conditions d’accès aux dons, progressions particulières de chaque classe et variantes doivent être vérifiés avec les règles de la table. Le niveau maximum est 20. Les modificateurs d’attributs sont utilisés pour les personnages classiques et Remaster.

Automatismes déterministes : Effrayé/Malade, Maladroit/Affaibli/Drainé/Stupéfié sur les statistiques concernées, Fatigué sur CA/sauvegardes, pris au dépourvu sur CA, certaines conséquences d’Inconscient et encombrement. Les autres conditions sont des marqueurs avec notes : elles ne remplacent pas l’arbitrage des actions, perception des cibles, jets nus, déplacements ou dégâts persistants. Mourant/blessé/condamné se gèrent explicitement ; atteindre 0 PV ne suppose pas automatiquement les circonstances d’une mort ou d’un coup critique.

Les résistances, faiblesses et immunités sont documentées ; les dégâts saisis sont les dégâts finaux à appliquer. Les traits d’armes autres qu’agile/finesse, attaques à distance spéciales, runes de propriété et dégâts additionnels restent décrits et doivent être arbitrés. Le nombre de dés d’une rune de frappe se configure explicitement. L’encombrement vise les personnages Petits/Moyens, sans allègement automatique des contenants ; entrer `0.1` pour un objet léger. Les pénalités de vitesse sont exprimées en pieds.

La marque « utilisé » d’un sort représente une préparation/un usage ; créer plusieurs entrées pour plusieurs préparations d’un même sort. Les sorts innés récurrents se représentent par plusieurs entrées ou une ressource. Le choix d’un emplacement doit correspondre à la source d’incantation : le moteur vérifie son rang et sa disponibilité, pas toutes les restrictions de tradition. Refocaliser rend un point ; le repos propose explicitement une récupération complète selon le temps accordé.

## Architecture

```text
index.php, api.php, portrait.php, setup.php  Entrées HTTP
app/bootstrap.php                          Configuration, sessions, autoload
app/Database/                              Connexion PDO et champs relationnels
app/Rules/                                 Catalogue mécanique et moteur
app/Services/                              Auth, repository, validation, opérations
public/assets/css/                         Design responsive et impression
public/assets/js/                          Modules UI, API, fiche, collections, assistants
database/migrations/                       Migrations versionnées
bin/                                       Migration, compte, sauvegarde
tests/                                     Tests moteur, API HTTP et navigateur
storage/                                   SQLite, uploads, sessions, logs, backups, outils locaux
```

Les personnages, attributs, maîtrises, monnaies et collections ont des tables séparées avec clés étrangères. Les données principales des objets, sorts, dons, conditions, ressources, notes et modificateurs sont dans des colonnes relationnelles (`002_relational_fields`). Le JSON est réservé aux propriétés variables, snapshots et événements. Les données de règles dans `app/Rules` restent indépendantes des données des utilisateurs. Index par propriétaire/personnage ; historique courant limité aux 100 derniers événements.

## Prérequis et déploiement

PHP 8.1+ avec PDO, pdo_sqlite ou pdo_mysql, mbstring, json, fileinfo, GD, openssl, ZipArchive. Aucun serveur Node, Composer ou build d’assets n’est nécessaire en production.

1. Récupérer le dépôt dans un dossier dédié.
2. Copier `.env.example` vers `.env`, puis configurer la base sans versionner ce fichier.
3. Créer `storage/uploads`, `storage/logs`, `storage/backups`, `storage/sessions`, accessibles en écriture au compte PHP ; `.env` en mode 600. Sur cet hébergement, CLI et PHP s’exécutent avec le compte du site.
4. Exécuter `php bin/migrate.php`.
5. Créer le compte initial avec `php bin/user.php` ou configurer `SETUP_TOKEN_HASH` (SHA-256 d’un secret aléatoire de 32 octets) puis ouvrir `setup.php#SECRET`.
6. Vérifier HTTP 403 pour `.env`, `.git/config`, `storage/characters.sqlite`, `database/`, `app/` ; vérifier HTTP 200 sur `index.php`.

L’architecture utilise les entrées à la racine pour le sous-dossier Apache existant. `.htaccess` exige `mod_rewrite` et `AllowOverride` permettant les directives fournies. Il bloque les dossiers internes, les fichiers cachés et l’indexation. Ne pas déplacer le DocumentRoot vers `public` sans adapter les points d’entrée. La configuration générale Apache n’est pas modifiée.

## Base MySQL de production

La configuration réelle est dans `.env`, hors Git. Destination prévue : base `k599zx_NyxPathfinderManager`, hôte `k599zx.myd.infomaniak.com`. Le mot de passe a été renseigné dans `.env` par le propriétaire. La connexion et la migration depuis SQLite ont été validées, ainsi que les parcours HTTP et navigateur sur MySQL.

Renseigner `DB_PASS` uniquement dans `.env`. Pour une migration ou une intervention, créer `storage/maintenance` afin que les requêtes HTTP reçoivent 503, puis supprimer ce fichier à la fin. La migration et les sauvegardes prennent un verrou exclusif sur `storage/operations.lock`. Pour une installation MySQL neuve, définir `DB_DRIVER=mysql`, puis lancer les migrations. Toutes les tables sont en InnoDB, utf8mb4 / utf8mb4_unicode_ci. PDO utilise les préparations natives et les exceptions. Ne pas simplement basculer le driver d’un site contenant des personnages : sauvegarder et migrer ses données au préalable. Le script `bin/migrate-storage.php` copie une base SQLite vers une destination MySQL **vide** en conservant les identifiants et propriétaires ; voir son aide avant usage.

## Sauvegardes et restauration

`php bin/backup.php` écrit un dossier horodaté dans `storage/backups` : copie SQLite cohérente avec `VACUUM INTO` ou dump MySQL sous snapshot transactionnel, plus archive des portraits. Les sauvegardes peuvent contenir des données privées ; elles ne sont ni servies sur HTTP ni versionnées. Sauvegarder séparément `.env` et conserver une copie hors du serveur par votre mécanisme habituel.

Restaurer SQLite en maintenance : conserver une copie de la base actuelle, remplacer `storage/characters.sqlite` par la copie de sauvegarde, remettre les portraits dans `storage/uploads`, vérifier les permissions puis les migrations. Restaurer MySQL dans une base vide depuis le dump ; ne pas injecter un dump par-dessus les données courantes. Les fichiers de session ne font pas partie des sauvegardes.

## API et sécurité

Routes compatibles avec un sous-dossier sans règle de réécriture d’API :

- `GET api.php` : liste du propriétaire connecté.
- `GET api.php?id=ID` : fiche calculée.
- `POST api.php?action=create|import|duplicate` : création/import/duplication.
- `PATCH api.php?id=ID&action=hp|skill|entry|consume|cast|currency|transfer|level_up|rest|...`.
- `DELETE api.php?id=ID&action=delete`.
- `GET api.php?id=ID&action=export` : document JSON téléchargeable brut.

Les mutations exigent `X-CSRF-Token`. Les opérations sur une fiche exigent sa `revision` ; une version périmée reçoit HTTP 409 sans écrasement. Réponses `{ok:true,data:...}` ou `{ok:false,error:...}` ; exception documentée pour l’export brut. Mutations atomiques avec rollback. Le montant maximal d’une requête JSON est 3 Mo. Les identifiants d’éléments sont toujours contrôlés dans le périmètre du personnage.

Sessions HttpOnly/Secure sous HTTPS/SameSite Strict, régénération à la connexion, stockage privé local, limitation persistante des échecs de connexion, CSP sans scripts inline, échappement HTML. Uploads JPEG/PNG/WebP, 3 Mo / 16 mégapixels maximum, contrôle MIME/extension, réencodage JPEG de 800 px, nom aléatoire, lecture authentifiée. Aucun upload SVG exécutable. Les erreurs HTTP n’exposent pas les exceptions PDO. Inscriptions désactivées par défaut ; `REGISTRATION=true` est une décision d’administration explicite.

## Tests

```sh
php tests/run.php
php tests/fixture.php
python3 tests/api_http.py
php tests/fixture.php clean
```

`run.php` utilise une base SQLite en mémoire. Les tests HTTP ciblent l’URL déployée ou `TEST_BASE_URL` ; ils utilisent deux comptes temporaires dont les secrets aléatoires sont dans `storage/test-credentials.json`, et suppriment leurs personnages. Toujours exécuter le nettoyage après les tests, même en cas d’échec.

`tests/browser.cjs` utilise Playwright installé uniquement dans `storage/browser-tools` et des bibliothèques locales dans `storage/browser-libs`, sans modification système. Il vérifie le parcours de création, les PV, les conditions, les consommables, la magie, la progression, les onglets et le format mobile. Les captures de test restent dans `storage`.

## Mise à jour

Sauvegarder, vérifier `git status`, récupérer la branche main sans écraser de modifications locales, exécuter `php bin/migrate.php`, lancer les tests et vérifier les protections HTTP. Les migrations appliquées sont enregistrées dans `schema_migrations`. Ne pas modifier une migration déjà déployée pour changer le schéma : ajouter un nouveau fichier numéroté.

## Références et contenus

Aucun texte de livre ni catalogue tiers complet n’est reproduit. Les descriptions sont rédigées par les utilisateurs ; les icônes d’actions sont des formes génériques. Références de vérification : [aperçu officiel Remaster](https://downloads.paizo.com/RemasterCorePreview.pdf), [errata officiels](https://paizo.com/pathfinder/faq), [FAQ Remaster](https://paizo.com/pathfinder/remaster/faq). Pathfinder est une marque de Paizo Inc. Toute future intégration de contenus de règles devra être accompagnée d’un examen de la licence propre à la source.
