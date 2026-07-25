=== Health'eat Core ===
Contributors: healtheat
Tags: restaurant, menu, nutrition, click and collect, healthy
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Le moteur du concept Health'eat : carte détaillée (calories, macros, allergènes, régimes) et commande à emporter en click & collect.

== Description ==

Health'eat Core apporte à WordPress tout ce dont un restaurant healthy a besoin :

* un type de contenu **Plats** avec prix, calories, protéines / glucides / lipides / fibres, origine du produit et disponibilité ;
* trois taxonomies : **catégories** (bowls, salades, jus…), **régimes** (vegan, sans gluten, riche en protéines…) et **allergènes** ;
* une carte filtrable côté visiteur, sans rechargement de page ;
* un **click & collect** complet : panier, créneaux de retrait calculés depuis les horaires du restaurant, capacité par créneau, e-mails de confirmation ;
* un back-office de suivi des commandes (à confirmer → confirmée → prête → retirée), avec notification automatique du client à chaque étape.

Les prix sont stockés en centimes et **recalculés côté serveur** à chaque commande : le navigateur n'envoie que des identifiants de plats et des quantités.

== Photos ==

Les plats s'affichent avec de vraies photos dès qu'une image mise en avant leur est associée :

* **quatre formats** recadrés automatiquement — carte (900×675), fiche plat (1400 px de large), vignette ronde (400×400) et bandeau (2000×1100) ;
* **balises responsives** — chaque photo sort avec son `srcset`, ses attributs `sizes`, `loading` et `decoding` ;
* **aperçu flouté** — une miniature de 24 px est encodée en base64 à l'import et peinte derrière la photo : elle s'affiche instantanément, la vraie image la recouvre une fois décodée. Aucun saut de mise en page, aucun script ;
* **photos supplémentaires** — un encart « Photos supplémentaires » sur la fiche du plat permet d'ajouter des vues secondaires, affichées en vignettes cliquables ;
* **affectation en masse** — l'écran **Health'eat → Photos** liste tous les plats, signale ceux sans photo et permet, pour chacun, soit de choisir une image de la médiathèque, soit de coller l'adresse publique d'une photo à télécharger (vérifiée sur son type MIME réel, pas seulement son extension).

Photos achetées sur une banque payante (iStock, Getty, Adobe Stock…) : téléversez les fichiers dans la médiathèque, puis affectez-les depuis cet écran. Les liens de téléchargement de ces banques sont liés à votre session et ne peuvent pas servir à l'import par adresse ; les aperçus filigranés des pages de recherche ne sont pas utilisables.

Quand un plat n'a pas encore de photo, le thème affiche une illustration de secours via le filtre `healtheat_dish_placeholder`.

== Réglages ==

Menu **Health'eat → Réglages** :

* identité du restaurant (nom, e-mail de réception des commandes, téléphone, adresse, devise) ;
* activation de la commande en ligne, délai de préparation, intervalle et capacité des créneaux, réservation à l'avance, montant minimum ;
* horaires de retrait jour par jour ;
* page contenant le tunnel de commande.

== Shortcodes ==

* `[healtheat_menu]` — la carte complète avec ses filtres.
  Attributs : `limit`, `category`, `diet`, `filters="no"`.
* `[healtheat_featured limit="3"]` — les plats mis en avant.
* `[healtheat_order]` — le tunnel de commande click & collect.
* `[healtheat_hours]` — les horaires de retrait.

== Endpoints REST ==

* `GET /wp-json/healtheat/v1/slots` — créneaux de retrait disponibles.
* `POST /wp-json/healtheat/v1/orders` — enregistrement d'une commande.

Les envois sont limités à 5 commandes par heure et par adresse IP, avec champ piège anti-robot.

== Changelog ==

= 1.0.0 =
* Version initiale : plats, taxonomies, carte filtrable, click & collect, suivi des commandes et e-mails.
