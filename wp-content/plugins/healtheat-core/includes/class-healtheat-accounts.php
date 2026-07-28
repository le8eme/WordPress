<?php
/**
 * Comptes clients : objectifs, marqueurs déclarés et espace personnel.
 *
 * Les marqueurs biologiques sont des données de santé. Ils sont donc
 * facultatifs, soumis à un consentement distinct et horodaté, stockés sous
 * forme de simples repères (« fer bas ») plutôt que de valeurs d'analyse,
 * effaçables en un clic, et exposés aux outils d'export et de suppression
 * de données personnelles de WordPress.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles customer accounts and their nutrition profile.
 */
class Healtheat_Accounts {

	/**
	 * Hooks the account features.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'healtheat_account', array( __CLASS__, 'render' ) );

		add_action( 'admin_post_nopriv_healtheat_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_healtheat_register', array( __CLASS__, 'handle_register' ) );
		add_action( 'admin_post_healtheat_profile', array( __CLASS__, 'handle_profile' ) );
		add_action( 'admin_post_healtheat_forget_markers', array( __CLASS__, 'handle_forget_markers' ) );
		add_action( 'admin_post_healtheat_rebuild_plan', array( __CLASS__, 'handle_rebuild' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'render_admin_panel' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_admin_panel' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Returns the nutrition profile of a customer.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public static function get_profile( $user_id ) {
		$user_id = (int) $user_id;

		return array(
			'goal'        => (string) get_user_meta( $user_id, '_healtheat_goal', true ) ?: 'maintien',
			'activity'    => (string) get_user_meta( $user_id, '_healtheat_activity', true ) ?: 'modere',
			'sex'         => (string) get_user_meta( $user_id, '_healtheat_sex', true ),
			'age'         => (int) get_user_meta( $user_id, '_healtheat_age', true ),
			'height'      => (int) get_user_meta( $user_id, '_healtheat_height', true ),
			'weight'      => (float) get_user_meta( $user_id, '_healtheat_weight', true ),
			'kcal_target' => (int) get_user_meta( $user_id, '_healtheat_kcal_target', true ),
			'diets'       => array_filter( (array) get_user_meta( $user_id, '_healtheat_diets', true ) ),
			'allergens'   => array_filter( (array) get_user_meta( $user_id, '_healtheat_allergens', true ) ),
			'markers'     => array_filter( (array) get_user_meta( $user_id, '_healtheat_markers', true ) ),
			'consent'     => (string) get_user_meta( $user_id, '_healtheat_markers_consent', true ),
		);
	}

	/**
	 * Tells whether the profile has enough data to compose a plan.
	 *
	 * @param array<string,mixed> $profile Profile.
	 * @return bool
	 */
	public static function profile_is_complete( array $profile ) {
		return ! empty( $profile['goal'] );
	}

	/**
	 * Creates the account.
	 *
	 * @return void
	 */
	public static function handle_register() {
		$redirect = self::page_url();

		if ( ! isset( $_POST['healtheat_register_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['healtheat_register_nonce'] ) ), 'healtheat_register' ) ) {
			self::redirect_with( $redirect, 'error', 'expired' );
		}

		// Champ piège : rempli par les robots uniquement.
		if ( '' !== trim( (string) ( $_POST['healtheat_website'] ?? '' ) ) ) {
			self::redirect_with( $redirect, 'error', 'spam' );
		}

		if ( empty( $_POST['healtheat_terms'] ) ) {
			self::redirect_with( $redirect, 'error', 'terms' );
		}

		$email = sanitize_email( wp_unslash( $_POST['healtheat_email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['healtheat_name'] ?? '' ) );
		$pass  = (string) ( $_POST['healtheat_password'] ?? '' );

		if ( ! is_email( $email ) ) {
			self::redirect_with( $redirect, 'error', 'email' );
		}

		if ( email_exists( $email ) ) {
			self::redirect_with( $redirect, 'error', 'exists' );
		}

		if ( strlen( $pass ) < 8 ) {
			self::redirect_with( $redirect, 'error', 'password' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ? $name : $email,
				'first_name'   => $name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			self::redirect_with( $redirect, 'error', 'failed' );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		self::redirect_with( $redirect, 'ok', 'welcome' );
	}

	/**
	 * Saves the nutrition profile.
	 *
	 * @return void
	 */
	public static function handle_profile() {
		$redirect = self::page_url();

		if ( ! is_user_logged_in() ) {
			self::redirect_with( $redirect, 'error', 'login' );
		}

		check_admin_referer( 'healtheat_profile' );

		$user_id = get_current_user_id();
		$goals   = array_keys( Healtheat_Plan::get_goals() );
		$levels  = array_keys( Healtheat_Plan::get_activities() );

		$goal     = sanitize_key( wp_unslash( $_POST['healtheat_goal'] ?? '' ) );
		$activity = sanitize_key( wp_unslash( $_POST['healtheat_activity'] ?? '' ) );

		update_user_meta( $user_id, '_healtheat_goal', in_array( $goal, $goals, true ) ? $goal : 'maintien' );
		update_user_meta( $user_id, '_healtheat_activity', in_array( $activity, $levels, true ) ? $activity : 'modere' );

		$sex = sanitize_key( wp_unslash( $_POST['healtheat_sex'] ?? '' ) );
		update_user_meta( $user_id, '_healtheat_sex', in_array( $sex, array( 'femme', 'homme' ), true ) ? $sex : '' );

		update_user_meta( $user_id, '_healtheat_age', min( 100, absint( $_POST['healtheat_age'] ?? 0 ) ) );
		update_user_meta( $user_id, '_healtheat_height', min( 230, absint( $_POST['healtheat_height'] ?? 0 ) ) );
		update_user_meta( $user_id, '_healtheat_weight', min( 300, (float) ( $_POST['healtheat_weight'] ?? 0 ) ) );
		update_user_meta( $user_id, '_healtheat_kcal_target', min( 6000, absint( $_POST['healtheat_kcal'] ?? 0 ) ) );

		update_user_meta( $user_id, '_healtheat_diets', self::sanitize_terms( $_POST['healtheat_diets'] ?? array(), 'healtheat_diet' ) );
		update_user_meta( $user_id, '_healtheat_allergens', self::sanitize_terms( $_POST['healtheat_allergens'] ?? array(), 'healtheat_allergen' ) );

		// Données de santé : rien n'est enregistré sans consentement explicite.
		$markers = array();

		if ( ! empty( $_POST['healtheat_markers_consent'] ) ) {
			$allowed = array_keys( Healtheat_Plan::get_markers() );

			foreach ( (array) wp_unslash( $_POST['healtheat_markers'] ?? array() ) as $marker ) {
				$marker = sanitize_key( $marker );

				if ( in_array( $marker, $allowed, true ) ) {
					$markers[] = $marker;
				}
			}

			update_user_meta( $user_id, '_healtheat_markers_consent', current_time( 'mysql' ) );
		} else {
			delete_user_meta( $user_id, '_healtheat_markers_consent' );
		}

		update_user_meta( $user_id, '_healtheat_markers', $markers );

		// Le profil a changé : la semaine est recomposée.
		Healtheat_Plan::get_plan( $user_id, true );

		self::redirect_with( $redirect, 'ok', 'saved' );
	}

	/**
	 * Deletes the declared health markers.
	 *
	 * @return void
	 */
	public static function handle_forget_markers() {
		$redirect = self::page_url();

		if ( ! is_user_logged_in() ) {
			self::redirect_with( $redirect, 'error', 'login' );
		}

		check_admin_referer( 'healtheat_forget_markers' );

		$user_id = get_current_user_id();

		delete_user_meta( $user_id, '_healtheat_markers' );
		delete_user_meta( $user_id, '_healtheat_markers_consent' );

		Healtheat_Plan::get_plan( $user_id, true );

		self::redirect_with( $redirect, 'ok', 'markers_gone' );
	}

	/**
	 * Rebuilds the weekly plan.
	 *
	 * @return void
	 */
	public static function handle_rebuild() {
		$redirect = self::page_url();

		if ( ! is_user_logged_in() ) {
			self::redirect_with( $redirect, 'error', 'login' );
		}

		check_admin_referer( 'healtheat_rebuild_plan' );

		Healtheat_Plan::get_plan( get_current_user_id(), true );

		self::redirect_with( $redirect, 'ok', 'rebuilt' );
	}

	/**
	 * Keeps only existing term slugs.
	 *
	 * @param mixed  $values   Submitted values.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	protected static function sanitize_terms( $values, $taxonomy ) {
		$clean = array();

		foreach ( (array) wp_unslash( $values ) as $value ) {
			$slug = sanitize_title( $value );

			if ( $slug && get_term_by( 'slug', $slug, $taxonomy ) ) {
				$clean[] = $slug;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Returns the URL of the account page.
	 *
	 * @return string
	 */
	public static function page_url() {
		$pages = get_option( 'healtheat_pages', array() );

		if ( ! empty( $pages['account'] ) && 'publish' === get_post_status( (int) $pages['account'] ) ) {
			return (string) get_permalink( (int) $pages['account'] );
		}

		return home_url( '/' );
	}

	/**
	 * Redirects back to the account page with a message.
	 *
	 * @param string $url     Target.
	 * @param string $type    ok|error.
	 * @param string $message Message.
	 * @return void
	 */
	protected static function redirect_with( $url, $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'he_notice' => rawurlencode( $code ),
					'he_type'   => 'error' === $type ? 'error' : 'ok',
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Returns the notices, keyed by code.
	 *
	 * Le message ne transite jamais en clair dans l'adresse : un lien
	 * fabriqué ne peut donc pas afficher un texte de notre part.
	 *
	 * @return array<string,string>
	 */
	public static function get_notices() {
		return array(
			'expired'        => __( 'Formulaire expiré, merci de réessayer.', 'healtheat' ),
			'spam'           => __( 'Inscription refusée.', 'healtheat' ),
			'terms'          => __( 'Merci d\'accepter l\'utilisation de vos coordonnées pour le suivi des commandes.', 'healtheat' ),
			'email'          => __( 'Cette adresse e-mail est invalide.', 'healtheat' ),
			'exists'         => __( 'Un compte existe déjà avec cette adresse.', 'healtheat' ),
			'password'       => __( 'Le mot de passe doit faire au moins huit caractères.', 'healtheat' ),
			'failed'         => __( 'La création du compte a échoué, merci de réessayer.', 'healtheat' ),
			'welcome'        => __( 'Bienvenue ! Renseignez votre objectif pour recevoir votre semaine.', 'healtheat' ),
			'login'          => __( 'Connectez-vous d\'abord.', 'healtheat' ),
			'saved'          => __( 'Profil enregistré, votre semaine vient d\'être recomposée.', 'healtheat' ),
			'markers_gone'   => __( 'Vos repères de santé ont été supprimés.', 'healtheat' ),
			'rebuilt'        => __( 'Nouvelle proposition pour la semaine.', 'healtheat' ),
		);
	}

	/**
	 * Renders the account area.
	 *
	 * @return string
	 */
	public static function render() {
		Healtheat_Shortcodes::enqueue();

		ob_start();

		echo '<div class="healtheat-account">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['he_notice'] ) ) {
			$notices = self::get_notices();
			$code    = sanitize_key( wp_unslash( $_GET['he_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( isset( $notices[ $code ] ) ) {
				printf(
					'<p class="healtheat-account__notice is-%1$s">%2$s</p>',
					esc_attr( 'error' === ( $_GET['he_type'] ?? '' ) ? 'error' : 'ok' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					esc_html( $notices[ $code ] )
				);
			}
		}

		if ( is_user_logged_in() ) {
			self::render_dashboard();
		} else {
			self::render_forms();
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Renders the login and registration forms.
	 *
	 * @return void
	 */
	protected static function render_forms() {
		?>
		<div class="healtheat-account__columns">
			<section class="healtheat-account__card">
				<h2><?php esc_html_e( 'Créer mon compte', 'healtheat' ); ?></h2>
				<p class="healtheat-account__lead">
					<?php esc_html_e( 'Indiquez votre objectif, nous composons votre déjeuner pour toute la semaine.', 'healtheat' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="healtheat_register" />
					<?php wp_nonce_field( 'healtheat_register', 'healtheat_register_nonce' ); ?>

					<div class="healtheat-field">
						<label for="he-reg-name"><?php esc_html_e( 'Prénom', 'healtheat' ); ?></label>
						<input type="text" id="he-reg-name" name="healtheat_name" autocomplete="given-name" />
					</div>

					<div class="healtheat-field">
						<label for="he-reg-email"><?php esc_html_e( 'E-mail', 'healtheat' ); ?> *</label>
						<input type="email" id="he-reg-email" name="healtheat_email" autocomplete="email" required />
					</div>

					<div class="healtheat-field">
						<label for="he-reg-pass"><?php esc_html_e( 'Mot de passe', 'healtheat' ); ?> *</label>
						<input type="password" id="he-reg-pass" name="healtheat_password" autocomplete="new-password" minlength="8" required />
					</div>

					<div class="healtheat-field healtheat-field--hp" aria-hidden="true">
						<label for="he-reg-site"><?php esc_html_e( 'Site web', 'healtheat' ); ?></label>
						<input type="text" id="he-reg-site" name="healtheat_website" tabindex="-1" autocomplete="off" />
					</div>

					<p class="healtheat-consent">
						<label>
							<input type="checkbox" name="healtheat_terms" value="1" required />
							<?php esc_html_e( 'J\'accepte que mes coordonnées servent au suivi de mes commandes et à ma proposition de menus.', 'healtheat' ); ?>
						</label>
					</p>

					<button type="submit" class="healtheat-button healtheat-button--primary">
						<?php esc_html_e( 'Créer mon compte', 'healtheat' ); ?>
					</button>
				</form>
			</section>

			<section class="healtheat-account__card">
				<h2><?php esc_html_e( 'J\'ai déjà un compte', 'healtheat' ); ?></h2>
				<?php
				wp_login_form(
					array(
						'redirect'       => self::page_url(),
						'label_username' => __( 'E-mail', 'healtheat' ),
						'label_password' => __( 'Mot de passe', 'healtheat' ),
						'label_log_in'   => __( 'Se connecter', 'healtheat' ),
						'remember'       => true,
					)
				);
				?>
			</section>
		</div>
		<?php
	}

	/**
	 * Renders the customer dashboard.
	 *
	 * @return void
	 */
	protected static function render_dashboard() {
		$user    = wp_get_current_user();
		$profile = self::get_profile( $user->ID );
		$plan    = Healtheat_Plan::get_plan( $user->ID );
		$daily   = Healtheat_Plan::daily_calories( $profile );
		$lunch   = Healtheat_Plan::lunch_target( $profile );
		?>
		<header class="healtheat-account__header">
			<div>
				<h2>
					<?php
					printf(
						/* translators: %s: customer first name. */
						esc_html__( 'Bonjour %s', 'healtheat' ),
						esc_html( $user->first_name ? $user->first_name : $user->display_name )
					);
					?>
				</h2>
				<p class="healtheat-account__lead">
					<?php if ( $lunch ) : ?>
						<?php
						printf(
							/* translators: 1: daily calories, 2: lunch calories. */
							esc_html__( 'Environ %1$s kcal par jour, dont %2$s kcal au déjeuner.', 'healtheat' ),
							esc_html( number_format_i18n( $daily ) ),
							esc_html( number_format_i18n( $lunch ) )
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Complétez votre profil pour ajuster les portions à vos besoins.', 'healtheat' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<a class="healtheat-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<?php esc_html_e( 'Se déconnecter', 'healtheat' ); ?>
			</a>
		</header>

		<?php self::render_plan( $plan ); ?>
		<?php self::render_profile_form( $profile ); ?>
		<?php
	}

	/**
	 * Renders the weekly plan.
	 *
	 * @param array<int,array<string,mixed>> $plan Plan.
	 * @return void
	 */
	protected static function render_plan( $plan ) {
		?>
		<section class="healtheat-account__card healtheat-plan">
			<h3><?php esc_html_e( 'Votre semaine', 'healtheat' ); ?></h3>

			<?php if ( ! $plan ) : ?>
				<p class="healtheat-account__lead">
					<?php esc_html_e( 'Aucun plat ne correspond encore à vos critères. Assouplissez un régime ou revenez quand la carte aura changé.', 'healtheat' ); ?>
				</p>
			<?php else : ?>
				<?php
				$distinct = count( array_unique( wp_list_pluck( wp_list_pluck( $plan, 'dish' ), 'id' ) ) );

				if ( $distinct < count( $plan ) ) :
					?>
					<p class="healtheat-account__hint">
						<?php
						printf(
							/* translators: %d: number of matching dishes. */
							esc_html( _n( 'Seul %d plat correspond à vos critères cette semaine : il revient donc plusieurs fois. Assouplir un régime ou une allergie élargira les propositions.', 'Seuls %d plats correspondent à vos critères cette semaine : certains reviennent donc plusieurs fois. Assouplir un régime ou une allergie élargira les propositions.', $distinct, 'healtheat' ) ),
							(int) $distinct
						);
						?>
					</p>
				<?php endif; ?>

				<ul class="healtheat-plan__list">
					<?php foreach ( $plan as $day ) : ?>
						<?php $dish = $day['dish']; ?>
						<li class="healtheat-plan__day">
							<span class="healtheat-plan__date"><?php echo esc_html( $day['label'] ); ?></span>

							<span class="healtheat-plan__dish">
								<a href="<?php echo esc_url( $dish['permalink'] ?? '' ); ?>"><?php echo esc_html( $dish['name'] ); ?></a>
								<small>
									<?php
									printf(
										/* translators: 1: calories, 2: protein. */
										esc_html__( '%1$s kcal · %2$s g de protéines', 'healtheat' ),
										esc_html( (string) $dish['calories'] ),
										esc_html( (string) $dish['protein'] )
									);
									?>
								</small>
							</span>

							<span class="healtheat-plan__action">
								<span class="healtheat-price"><?php echo esc_html( healtheat_format_price( (int) $dish['price'] ) ); ?></span>
								<button type="button" class="healtheat-button healtheat-add"
									data-dish-id="<?php echo esc_attr( $dish['id'] ); ?>"
									data-dish-name="<?php echo esc_attr( $dish['name'] ); ?>"
									data-dish-price="<?php echo esc_attr( $dish['price'] ); ?>">
									<?php esc_html_e( 'Ajouter', 'healtheat' ); ?>
								</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="healtheat-plan__rebuild">
					<input type="hidden" name="action" value="healtheat_rebuild_plan" />
					<?php wp_nonce_field( 'healtheat_rebuild_plan' ); ?>
					<button type="submit" class="healtheat-link"><?php esc_html_e( 'Proposer une autre semaine', 'healtheat' ); ?></button>
				</form>
			<?php endif; ?>

			<p class="healtheat-account__legal">
				<?php esc_html_e( 'Ces suggestions sont établies à partir de ce que vous déclarez et de la composition de nos plats. Elles ne remplacent pas l\'avis d\'un médecin ou d\'un diététicien.', 'healtheat' ); ?>
			</p>
		</section>
		<?php
	}

	/**
	 * Renders the profile form.
	 *
	 * @param array<string,mixed> $profile Profile.
	 * @return void
	 */
	protected static function render_profile_form( array $profile ) {
		$diets     = get_terms( array( 'taxonomy' => 'healtheat_diet', 'hide_empty' => false ) );
		$allergens = get_terms( array( 'taxonomy' => 'healtheat_allergen', 'hide_empty' => false ) );
		?>
		<section class="healtheat-account__card">
			<h3><?php esc_html_e( 'Mon profil', 'healtheat' ); ?></h3>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="healtheat_profile" />
				<?php wp_nonce_field( 'healtheat_profile' ); ?>

				<div class="healtheat-field">
					<label for="he-goal"><?php esc_html_e( 'Mon objectif', 'healtheat' ); ?></label>
					<select id="he-goal" name="healtheat_goal">
						<?php foreach ( Healtheat_Plan::get_goals() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $profile['goal'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="healtheat-field">
					<label for="he-activity"><?php esc_html_e( 'Mon activité', 'healtheat' ); ?></label>
					<select id="he-activity" name="healtheat_activity">
						<?php foreach ( Healtheat_Plan::get_activities() as $key => $level ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $profile['activity'], $key ); ?>><?php echo esc_html( $level['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="healtheat-account__grid">
					<div class="healtheat-field">
						<label for="he-sex"><?php esc_html_e( 'Sexe', 'healtheat' ); ?></label>
						<select id="he-sex" name="healtheat_sex">
							<option value=""><?php esc_html_e( 'Ne pas préciser', 'healtheat' ); ?></option>
							<option value="femme" <?php selected( $profile['sex'], 'femme' ); ?>><?php esc_html_e( 'Femme', 'healtheat' ); ?></option>
							<option value="homme" <?php selected( $profile['sex'], 'homme' ); ?>><?php esc_html_e( 'Homme', 'healtheat' ); ?></option>
						</select>
					</div>

					<div class="healtheat-field">
						<label for="he-age"><?php esc_html_e( 'Âge', 'healtheat' ); ?></label>
						<input type="number" id="he-age" name="healtheat_age" min="14" max="100" value="<?php echo esc_attr( $profile['age'] ?: '' ); ?>" />
					</div>

					<div class="healtheat-field">
						<label for="he-height"><?php esc_html_e( 'Taille (cm)', 'healtheat' ); ?></label>
						<input type="number" id="he-height" name="healtheat_height" min="120" max="230" value="<?php echo esc_attr( $profile['height'] ?: '' ); ?>" />
					</div>

					<div class="healtheat-field">
						<label for="he-weight"><?php esc_html_e( 'Poids (kg)', 'healtheat' ); ?></label>
						<input type="number" step="0.1" id="he-weight" name="healtheat_weight" min="30" max="300" value="<?php echo esc_attr( $profile['weight'] ?: '' ); ?>" />
					</div>

					<div class="healtheat-field">
						<label for="he-kcal"><?php esc_html_e( 'Objectif kcal / jour', 'healtheat' ); ?></label>
						<input type="number" id="he-kcal" name="healtheat_kcal" min="0" max="6000" step="50" value="<?php echo esc_attr( $profile['kcal_target'] ?: '' ); ?>" />
					</div>
				</div>

				<p class="healtheat-account__hint">
					<?php esc_html_e( 'Taille, poids et âge servent uniquement à estimer vos besoins. Laissez vide si vous préférez : nous utiliserons alors votre objectif kcal, ou des portions moyennes.', 'healtheat' ); ?>
				</p>

				<?php if ( ! is_wp_error( $diets ) && $diets ) : ?>
					<fieldset class="healtheat-choices">
						<legend><?php esc_html_e( 'Mes régimes', 'healtheat' ); ?></legend>
						<?php foreach ( $diets as $diet ) : ?>
							<label>
								<input type="checkbox" name="healtheat_diets[]" value="<?php echo esc_attr( $diet->slug ); ?>" <?php checked( in_array( $diet->slug, (array) $profile['diets'], true ) ); ?> />
								<?php echo esc_html( $diet->name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>

				<?php if ( ! is_wp_error( $allergens ) && $allergens ) : ?>
					<fieldset class="healtheat-choices">
						<legend><?php esc_html_e( 'Mes allergies — ces plats seront écartés', 'healtheat' ); ?></legend>
						<?php foreach ( $allergens as $allergen ) : ?>
							<label>
								<input type="checkbox" name="healtheat_allergens[]" value="<?php echo esc_attr( $allergen->slug ); ?>" <?php checked( in_array( $allergen->slug, (array) $profile['allergens'], true ) ); ?> />
								<?php echo esc_html( $allergen->name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>

				<fieldset class="healtheat-choices healtheat-choices--health">
					<legend><?php esc_html_e( 'Repères de ma dernière prise de sang — facultatif', 'healtheat' ); ?></legend>

					<p class="healtheat-account__hint">
						<?php esc_html_e( 'Cochez uniquement ce que votre médecin vous a signalé. Nous ne demandons ni vos valeurs d\'analyse, ni votre compte rendu : seulement le repère, pour orienter nos suggestions.', 'healtheat' ); ?>
					</p>

					<?php foreach ( Healtheat_Plan::get_markers() as $key => $marker ) : ?>
						<label>
							<input type="checkbox" name="healtheat_markers[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $profile['markers'], true ) ); ?> />
							<?php echo esc_html( $marker['label'] ); ?>
						</label>
					<?php endforeach; ?>

					<p class="healtheat-consent">
						<label>
							<input type="checkbox" name="healtheat_markers_consent" value="1" <?php checked( ! empty( $profile['consent'] ) ); ?> />
							<?php esc_html_e( 'J\'accepte que ces repères de santé soient conservés pour personnaliser mes menus. Sans cette case, rien n\'est enregistré.', 'healtheat' ); ?>
						</label>
					</p>

					<?php if ( ! empty( $profile['consent'] ) ) : ?>
						<p class="healtheat-account__hint">
							<?php
							printf(
								/* translators: %s: consent date. */
								esc_html__( 'Consentement enregistré le %s.', 'healtheat' ),
								esc_html( mysql2date( get_option( 'date_format' ) . ' à ' . get_option( 'time_format' ), $profile['consent'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</fieldset>

				<button type="submit" class="healtheat-button healtheat-button--primary">
					<?php esc_html_e( 'Enregistrer et recomposer ma semaine', 'healtheat' ); ?>
				</button>
			</form>

			<?php if ( ! empty( $profile['markers'] ) || ! empty( $profile['consent'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="healtheat-plan__rebuild">
					<input type="hidden" name="action" value="healtheat_forget_markers" />
					<?php wp_nonce_field( 'healtheat_forget_markers' ); ?>
					<button type="submit" class="healtheat-link"><?php esc_html_e( 'Supprimer mes repères de santé', 'healtheat' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Shows the profile on the WordPress user screen.
	 *
	 * @param WP_User $user User.
	 * @return void
	 */
	public static function render_admin_panel( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$profile = self::get_profile( $user->ID );
		$goals   = Healtheat_Plan::get_goals();
		$markers = Healtheat_Plan::get_markers();
		$labels  = array();

		foreach ( (array) $profile['markers'] as $marker ) {
			if ( isset( $markers[ $marker ] ) ) {
				$labels[] = $markers[ $marker ]['label'];
			}
		}
		?>
		<h2><?php esc_html_e( 'Profil Health\'eat', 'healtheat' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Objectif', 'healtheat' ); ?></th>
				<td><?php echo esc_html( $goals[ $profile['goal'] ] ?? '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Besoin estimé', 'healtheat' ); ?></th>
				<td>
					<?php
					$daily = Healtheat_Plan::daily_calories( $profile );
					echo $daily ? esc_html( $daily . ' kcal / jour' ) : '—';
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Régimes', 'healtheat' ); ?></th>
				<td><?php echo esc_html( implode( ', ', (array) $profile['diets'] ) ?: '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Allergies', 'healtheat' ); ?></th>
				<td><?php echo esc_html( implode( ', ', (array) $profile['allergens'] ) ?: '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Repères de santé', 'healtheat' ); ?></th>
				<td>
					<?php echo esc_html( implode( ', ', $labels ) ?: '—' ); ?>
					<?php if ( $profile['consent'] ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: consent date. */
								esc_html__( 'Consentement du client le %s. Données de santé : à ne pas diffuser hors de la préparation des menus.', 'healtheat' ),
								esc_html( $profile['consent'] )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Registers the personal data exporter.
	 *
	 * @param array<string,mixed> $exporters Exporters.
	 * @return array<string,mixed>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['healtheat'] = array(
			'exporter_friendly_name' => __( 'Profil Health\'eat', 'healtheat' ),
			'callback'               => array( __CLASS__, 'export_data' ),
		);

		return $exporters;
	}

	/**
	 * Exports the nutrition profile.
	 *
	 * @param string $email Email address.
	 * @return array<string,mixed>
	 */
	public static function export_data( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$profile = self::get_profile( $user->ID );
		$markers = Healtheat_Plan::get_markers();
		$labels  = array();

		foreach ( (array) $profile['markers'] as $marker ) {
			$labels[] = $markers[ $marker ]['label'] ?? $marker;
		}

		$items = array(
			array(
				'group_id'    => 'healtheat-profile',
				'group_label' => __( 'Profil Health\'eat', 'healtheat' ),
				'item_id'     => 'healtheat-profile',
				'data'        => array(
					array( 'name' => __( 'Objectif', 'healtheat' ), 'value' => $profile['goal'] ),
					array( 'name' => __( 'Activité', 'healtheat' ), 'value' => $profile['activity'] ),
					array( 'name' => __( 'Régimes', 'healtheat' ), 'value' => implode( ', ', (array) $profile['diets'] ) ),
					array( 'name' => __( 'Allergies', 'healtheat' ), 'value' => implode( ', ', (array) $profile['allergens'] ) ),
					array( 'name' => __( 'Repères de santé', 'healtheat' ), 'value' => implode( ', ', $labels ) ),
					array( 'name' => __( 'Consentement santé', 'healtheat' ), 'value' => $profile['consent'] ),
				),
			),
		);

		return array( 'data' => $items, 'done' => true );
	}

	/**
	 * Registers the personal data eraser.
	 *
	 * @param array<string,mixed> $erasers Erasers.
	 * @return array<string,mixed>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['healtheat'] = array(
			'eraser_friendly_name' => __( 'Profil Health\'eat', 'healtheat' ),
			'callback'             => array( __CLASS__, 'erase_data' ),
		);

		return $erasers;
	}

	/**
	 * Erases the nutrition profile.
	 *
	 * @param string $email Email address.
	 * @return array<string,mixed>
	 */
	public static function erase_data( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		foreach ( array( 'goal', 'activity', 'sex', 'age', 'height', 'weight', 'kcal_target', 'diets', 'allergens', 'markers', 'markers_consent' ) as $key ) {
			delete_user_meta( $user->ID, '_healtheat_' . $key );
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
				$user->ID,
				$wpdb->esc_like( '_healtheat_plan_' ) . '%'
			)
		);

		return array( 'items_removed' => true, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
