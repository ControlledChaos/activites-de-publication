<?php
/**
 * Post Activities functions.
 *
 * @package Activites_d_article\inc
 *
 * @since  1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get plugin's version.
 *
 * @since  1.0.0
 *
 * @return string the plugin's version.
 */
function post_activities_version() {
	return post_activities()->version;
}

/**
 * Get the plugin's JS Url.
 *
 * @since  1.0.0
 *
 * @return string the plugin's JS Url.
 */
function post_activities_js_url() {
	return post_activities()->js_url;
}

/**
 * Get the plugin's Assets Url.
 *
 * @since  1.0.0
 *
 * @return string the plugin's Assets Url.
 */
function post_activities_assets_url() {
	return post_activities()->assets_url;
}

/**
 * Get the JS minified suffix.
 *
 * @since  1.0.0
 *
 * @return string the JS minified suffix.
 */
function post_activities_min_suffix() {
	$min = '.min';

	if ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG )  {
		$min = '';
	}

	/**
	 * Filter here to edit the minified suffix.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $min The minified suffix.
	 */
	return apply_filters( 'post_activities_min_suffix', $min );
}

function post_activities_supported_post_types() {
	return apply_filters( 'post_activities_supported_post_types', array(
		'post',
		'page',
	) );
}

function post_activities_is_post_type_supported( WP_Post $post ) {
	$retval = false;
	$type   = get_post_type( $post );

	if ( in_array( $type, post_activities_supported_post_types(), true ) ) {
		$retval = true;
	}

	if ( 'page' === $type && in_array( $post->ID, bp_core_get_directory_page_ids(), true ) ) {
		$retval = false;
	}

	return $retval;
}

function post_activities_init() {
	$supported_post_types = post_activities_supported_post_types();

	$common_args = array(
		'type'        => 'boolean',
		'description' => __( 'Activer ou non les activités d\'articles', 'activite-d-articles' ),
		'single'      => true,
		'show_in_rest'=> true,
	);

	foreach ( $supported_post_types as $post_type ) {
		register_post_meta( $post_type, 'activite_d_articles', $common_args );
	}
}
add_action( 'bp_init', 'post_activities_init' );

function post_activities_rest_init() {
	if ( post_activities()->bp_rest_is_enabled ) {
		return;
	}

	$controller = new BP_REST_Activity_Endpoint();
	$controller->register_routes();
}
add_action( 'bp_rest_api_init', 'post_activities_rest_init' );

function post_activities_format_activity_action( $action, $activity ) {
	$user_link = bp_core_get_userlink( $activity->user_id );

	return sprintf( __( '%s a partagé une activité de publication.', 'activite-d-articles' ), $user_link );
}

/**
 * Register activity action.
 *
 * @since 1.0.0
 */
function post_activities_register_activity_type() {
	bp_activity_set_action(
		buddypress()->activity->id,
		'publication_activity',
		__( 'Nouvelle activité d\'article', 'activite-d-articles' ),
		'post_activities_format_activity_action',
		__( 'Activités d\'article', 'activite-d-articles' ),
		array( 'activity', 'member' )
	);
}
add_action( 'bp_register_activity_actions', 'post_activities_register_activity_type' );

function post_activities_new_activity_args( $args = array() ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		if ( ! isset( $_POST['type'] ) || 'publication_activity' !== $_POST['type'] ) {
			return $args;
		}

		$postData = wp_parse_args( $_POST, array(
			'item_id'           => 0,
			'secondary_item_id' => 0,
		) );

		$args = array_merge( $args, $postData, array(
			'primary_link' => get_permalink( (int) $postData['item_id'] ),
		) );
	}

	return $args;
}
add_filter( 'bp_after_activity_add_parse_args', 'post_activities_new_activity_args', 10, 1 );

function post_activities_front_register_scripts() {
	if ( ! isset( wp_scripts()->registered['bp-nouveau-activity-post-form'] ) ) {
		$js_base_url = trailingslashit( buddypress()->theme_compat->packages['nouveau']->__get( 'url' ) );

		foreach ( array(
			'bp-nouveau'                    => bp_core_get_js_dependencies(),
			'bp-nouveau-activity'           => array( 'bp-nouveau' ),
			'bp-nouveau-activity-post-form' => array( 'bp-nouveau', 'bp-nouveau-activity', 'json2', 'wp-backbone' ) ) as $handle => $deps ) {
			$filename = 'buddypress-nouveau';

			if ( 'bp-nouveau' !== $handle ) {
				$filename = str_replace( 'bp-nouveau', 'buddypress', $handle );
			}

			wp_register_script(
				$handle,
				sprintf( '%1$sjs/%2$s%3$s.js', $js_base_url, $filename, post_activities_min_suffix() ),
				$deps,
				post_activities_version(),
				true
			);
		}
	}

	wp_register_script(
		'activites-d-article-front-script',
		sprintf( '%1$sfront%2$s.js', post_activities_js_url(), post_activities_min_suffix() ),
		array( 'bp-nouveau-activity-post-form', 'wp-api-request' ),
		post_activities_version(),
		true
	);
}
add_action( 'bp_enqueue_scripts', 'post_activities_front_register_scripts', 4 );

function post_activities_front_enqueue_scripts() {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_post();

	if ( ! post_activities_is_post_type_supported( $post ) || true !== (bool) get_post_meta( $post->ID, 'activite_d_articles', true ) ) {
		return;
	}

	wp_enqueue_script( 'activites-d-article-front-script' );
	wp_localize_script( 'activites-d-article-front-script', '_activitesDePublicationSettings', array(
		'versionString'     => 'buddypress/v1',
		'primaryID'         => get_current_blog_id(),
		'secondaryID'       => $post->ID,
		// Use the comment_form() fields to be as close to the theme output as possible.
		'commentFormFields' => apply_filters( 'comment_form_defaults', array(
			'must_log_in' => '<p class="must-log-in">' . sprintf(
			/* translators: %s: login URL */
			__( 'Vous devez <a href="%s">être connecté·e</a> pour afficher ou publier des activités.', 'activite-d-articles' ),
			wp_login_url( apply_filters( 'the_permalink', get_permalink( $post->ID ), $post->ID ) )
		) . '</p>',
		) ),
	) );

	$activity_params = array(
		'user_id'     => bp_loggedin_user_id(),
		'object'      => 'user',
		'backcompat'  => false,
		'post_nonce'  => wp_create_nonce( 'post_update', '_wpnonce_post_update' ),
	);

	if ( is_user_logged_in() && buddypress()->avatar->show_avatars ) {
		$width  = bp_core_avatar_thumb_width();
		$height = bp_core_avatar_thumb_height();
		$activity_params = array_merge( $activity_params, array(
			'avatar_url'    => bp_get_loggedin_user_avatar( array(
				'width'  => $width,
				'height' => $height,
				'html'   => false,
			) ),
			'avatar_width'  => $width,
			'avatar_height' => $height,
			'user_domain'   => bp_loggedin_user_domain(),
			'avatar_alt'    => sprintf(
				/* translators: %s = member name */
				__( 'Profile photo of %s', 'buddypress' ),
				bp_get_loggedin_user_fullname()
			),
		) );
	}

	wp_localize_script( 'bp-nouveau', 'BP_Nouveau', array(
		'objects' => array( 'activity' ),
		'nonces'  => array( 'activity' => wp_create_nonce( 'bp_nouveau_activity' ) ),
		'activity' => array(
			'params' => $activity_params,
			'strings' => array(
				'whatsnewPlaceholder' => sprintf( __( "What's new, %s?", 'buddypress' ), bp_get_user_firstname( bp_get_loggedin_user_fullname() ) ),
				'whatsnewLabel'       => __( 'Post what\'s new', 'buddypress' ),
				'whatsnewpostinLabel' => __( 'Post in', 'buddypress' ),
				'postUpdateButton'    => __( 'Post Update', 'buddypress' ),
				'cancelButton'        => __( 'Cancel', 'buddypress' ),
			)
		),
	) );

	add_filter( 'the_content', 'post_activities_js_templates' );
}
add_action( 'bp_enqueue_scripts', 'post_activities_front_enqueue_scripts', 14 );

function post_activities_js_templates( $content = '' ) {
	$path = trailingslashit( buddypress()->theme_compat->packages['nouveau']->__get( 'dir' ) );

	if ( ! function_exists( 'bp_nouveau_activity_hook' ) ) {
		require_once( $path . 'includes/template-tags.php' );
		require_once( $path . 'includes/activity/template-tags.php' );
	}

	ob_start();
	require_once( $path . 'buddypress/common/js-templates/activity/form.php' );
	?>
	<script type="text/html" id="tmpl-activites-de-publication">
		<p>{{{data.content}}}</p>
	</script>
	<?php
	$templates = ob_get_clean();

	remove_filter( 'the_content', 'post_activities_js_templates' );
	return $content . $templates;
}
