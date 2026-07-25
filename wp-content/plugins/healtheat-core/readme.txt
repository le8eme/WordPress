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

== Banque d'images ==

L'écran **Health'eat → Photos** intègre une banque de vraies photos, utilisable sans quitter l'administration :

1. collez une clé d'API Pexels (gratuite, immédiate, sur pexels.com/api) — ou définissez la constante `HEALTHEAT_PEXELS_KEY` dans `wp-config.php` ;
2. tapez une recherche (« bowl quinoa », « salade césar », « jus détox ») ;
3. la grille affiche les photos ; sous chacune, choisissez le plat auquel l'affecter ;
4. « Importer les photos choisies » les télécharge, les recadre aux formats du site et les définit comme image principale.

La licence Pexels autorise l'usage commercial, y compris sur le site d'un restaurant, sans achat ni mention obligatoire ; le nom du photographe est tout de même enregistré. Une case cochée par défaut marque ces photos comme provisoires, pour pouvoir les remplacer d'un bloc après un shooting.

Côté sécurité, seules les adresses servies par `images.pexels.com` sont téléchargées : un formulaire trafiqué ne peut pas faire récupérer une adresse arbitraire par le serveur.

Pour une banque payante (iStock, Getty, Adobe Stock), il n'y a pas d'API d'import possible sans votre abonnement : téléchargez les fichiers achetés, téléversez-les dans la médiathèque, puis affectez-les depuis le tableau plus bas.

== Photos provisoires ==

En attendant un vrai shooting, l'écran **Health'eat → Photos** propose deux façons de remplir la carte, qui ne touchent qu'aux plats sans photo :

* **Importer des photos libres** — interroge l'API de Wikimedia Commons, ne retient que les licences autorisant l'usage commercial (CC0, domaine public, CC BY, CC BY-SA ; les mentions NC, ND, fair use et non-free sont écartées), télécharge la première image exploitable et enregistre l'auteur, la licence et la page source. Ce crédit s'affiche sous la photo sur la fiche du plat, comme ces licences l'exigent ;
* **Générer des visuels de remplacement** — aucune connexion requise : le site fabrique un aplat dégradé par plat, dans les teintes de la palette (vert, cyan, violet), avec une silhouette de bol. Ce n'est pas une photographie et cela ne prétend pas l'être.

Toutes les images posées ainsi sont marquées comme provisoires : elles portent la mention « provisoire » dans le tableau, un rappel s'affiche tant qu'il en reste en ligne, et le bouton **Supprimer les photos provisoires** les retire toutes — y compris des plats — une fois les vraies photos prêtes.

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
