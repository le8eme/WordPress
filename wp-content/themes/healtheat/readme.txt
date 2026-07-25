=== Health'eat ===
Contributors: healtheat
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thème sur-mesure du restaurant healthy Health'eat.

== Description ==

Health'eat est un thème classique (PHP) pensé pour un restaurant healthy en click & collect :

* une page d'accueil éditoriale : héros, promesses, plats signature, les trois étapes du click & collect, le concept et le journal ;
* une carte filtrable et des fiches plat détaillées (photo, nutrition, allergènes, origine, ajout au panier) ;
* un pied de page qui reprend les horaires de retrait et les coordonnées saisies dans l'extension ;
* une navigation mobile accessible et un `theme.json` pour l'éditeur de blocs.

Le thème fonctionne seul, mais la carte et la commande nécessitent l'extension **Health'eat Core**.

== Installation ==

1. Activer l'extension « Health'eat Core » — elle crée les pages *Le concept*, *La carte*, *Commander*, *Nous trouver*, quelques plats de démonstration et les taxonomies.
2. Activer le thème « Health'eat » — il crée le menu principal à partir de ces pages.
3. Personnaliser le héros dans **Apparence → Personnaliser → Page d'accueil Health'eat**.
4. Renseigner horaires, adresse et règles de retrait dans **Health'eat → Réglages**.

== Personnalisation ==

Toutes les couleurs sont des variables CSS (`--healtheat-green`, `--healtheat-cream`…) déclarées sur `:root` dans `style.css` et reprises par l'extension : les redéfinir suffit pour rhabiller l'ensemble du site.

== Changelog ==

= 1.0.0 =
* Version initiale.
