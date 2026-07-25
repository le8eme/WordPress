=== Health'eat ===
Contributors: healtheat
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thème futuriste du restaurant healthy Health'eat.

== Description ==

Health'eat est un thème classique (PHP) à l'interface sombre néon, animée par des aliments dessinés en SVG :

* page d'accueil scénarisée : héros avec orbite d'ingrédients, aliments flottants en parallaxe, bandeau défilant, bol qui se compose au défilement, plats signature, étapes du click & collect ;
* carte filtrable et fiches plat détaillées, avec compteurs nutritionnels animés ;
* cartes en verre dépoli, halos colorés, grille en perspective et balayage lumineux au survol ;
* navigation mobile accessible, `theme.json` et styles d'éditeur assortis.

Le thème fonctionne seul, mais la carte et la commande nécessitent l'extension **Health'eat Core**.

== Animations ==

Tout ce qui bouge est regroupé dans `assets/css/animations.css` et `assets/js/animations.js` :

* **aliments SVG** — dix illustrations (`inc/food-svg.php`) découpées en parties nommées pour être animées indépendamment ;
* **parallaxe** — les aliments du héros suivent la souris et le défilement (`data-parallax`) ;
* **révélation** — les blocs apparaissent à l'entrée dans le champ de vision (`data-reveal`), via IntersectionObserver ;
* **compteurs** — les chiffres montent de zéro à leur valeur (`data-count`) ;
* **inclinaison** — les cartes suivent légèrement le curseur (`data-tilt`).

Le site reste entièrement lisible sans JavaScript, et **toutes** les animations se coupent si le visiteur a activé « réduire les animations » dans son système (`prefers-reduced-motion`).

== Installation ==

1. Activer l'extension « Health'eat Core » — elle crée les pages *Le concept*, *La carte*, *Commander*, *Nous trouver*, quelques plats de démonstration et les taxonomies.
2. Activer le thème « Health'eat » — il crée le menu principal à partir de ces pages.
3. Personnaliser le héros dans **Apparence → Personnaliser → Page d'accueil Health'eat**. Entourez un mot d'astérisques dans le titre (`Manger *sainement*`) pour l'afficher en dégradé néon.
4. Renseigner horaires, adresse et règles de retrait dans **Health'eat → Réglages**.

== Personnalisation ==

La palette tient dans les variables `--he-*` déclarées sur `:root` en tête de `style.css`. Le bloc `:root:root` juste en dessous rebranche les variables de l'extension (`--healtheat-surface`, `--healtheat-on-accent`, `--healtheat-tag-bg`…) sur cette palette : changer les six couleurs de base suffit à rhabiller le site entier, extension comprise.

== Changelog ==

= 2.0.0 =
* Refonte futuriste : interface sombre néon, aliments SVG animés, parallaxe, révélations au défilement, compteurs et bol qui se compose.
* Extension habillée par variables plutôt que par surcharges ponctuelles.

= 1.0.0 =
* Version initiale.
