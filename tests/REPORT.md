# Validation du déploiement — 24 septembre 2026

Environnement : Apache public du sous-dossier Pathfinder, PHP 8.4.24, SQLite puis MySQL après migration. Aucune configuration Apache globale modifiée.

- 37 assertions métier passent sur SQLite en mémoire : création, HP, temporaires, soins, annulation, maîtrises, cumul typé, compétences, conditions, armes/agile, inventaire relationnel, monnaie/annulation/transfert, ressources, sorts/focus, repos, boosts partiels, progression, historique, import/export, isolation utilisateur, concurrence optimiste, suppression et cascades.
- 27 assertions HTTP passent sur Apache : authentification de deux comptes, refus anonyme/CSRF/propriétaire tiers, création, HP, potion, monnaie, conditions, lancement atomique, level-up, téléchargement/import du JSON, conflit de révision, suppression et erreurs 404.
- Sept vérifications de parcours navigateur passent sur Chromium 130 : assistant complet, dégâts, consommable, lancement de sort, progression, largeur mobile 390 px, absence d’erreurs JavaScript. Affichage desktop 1920 × 1080 également capturé et inspecté.
- Cinq vérifications de portraits : faux MIME refusé, extension incorrecte refusée, PNG réencodé en JPEG, lecture anonyme refusée, fichier supprimé avec le personnage.
- `.env`, `.git/config`, `storage/characters.sqlite`, `storage/backups`, `app`, `database` et `config` renvoient HTTP 403.
- Contrôle syntaxique PHP et JavaScript passé ; migrations appliquées ; sauvegarde SQLite par VACUUM INTO.

Comptes, personnages et identifiants de test supprimés après vérification. Captures et outils de navigateur restent uniquement dans storage, non versionné et interdit via HTTP.

Limites de validation : pas de certification exhaustive de toutes les règles/classes/variantes Pathfinder. Les restrictions de contenu et automatismes partiels sont détaillés dans README.md.

Après configuration du mot de passe par le propriétaire : migration SQLite → MySQL vérifiée, 27 tests HTTP et sept parcours navigateur repassés sur MySQL, portraits revérifiés, tables uniformisées en utf8mb4 et sauvegarde SQL créée.

Validation des améliorations d’inventaire et de préparation :

- 45 assertions métier : ajout de cas sur les bonus liés aux objets investis, le rangement, les sacs extradimensionnels pleins/inconnus, la validation des capacités et le refus de lancer un sort non préparé.
- 27 assertions HTTP repassées sur MySQL ; un PDF personnel à la racine renvoie également HTTP 403.
- Parcours Chromium étendu : champs d’inventaire conditionnels, préparation d’une copie de sort et combinaison des filtres de recherche/préparation, en plus des parcours existants.
- Une copie privée d’une fiche volumineuse a été exportée/réimportée : statistiques, attributs, PV, DD et encombrement identiques. Aucun contenu personnel n’est inclus dans les tests versionnés.
