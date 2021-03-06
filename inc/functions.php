<?php
/**
 * Post Activities functions.
 *
 * @package Activites_de_Publication\inc
 *
 * @since  1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Gets plugin's version.
 *
 * @since  1.0.0
 *
 * @return string the plugin's version.
 */
function post_activities_version() {
	return post_activities()->version;
}

/**
 * Gets the plugin's JS Url.
 *
 * @since  1.0.0
 *
 * @return string the plugin's JS Url.
 */
function post_activities_js_url() {
	return post_activities()->js_url;
}

/**
 * Gets the plugin's BP Templates path.
 *
 * @since  1.0.0
 *
 * @return string the plugin's BP Templates path.
 */
function post_activities_bp_templates_dir() {
	return trailingslashit( post_activities()->tpl_dir) . 'buddypress';
}

/**
 * Gets the plugin's BP Templates url.
 *
 * @since  1.0.0
 *
 * @return string the plugin's BP Templates url.
 */
function post_activities_bp_templates_url() {
	return trailingslashit( post_activities()->tpl_url) . 'buddypress';
}

/**
 * Gets the JS minified suffix.
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

/**
 * Load translations.
 *
 * @since 1.0.0
 */
function post_activities_load_textdomain() {
	load_plugin_textdomain(
		'activites-de-publication',
		false,
		trailingslashit( basename( post_activities()->dir ) ) . 'languages'
	);
}
add_action( 'bp_include', 'post_activities_load_textdomain', 10 );

/**
 * Checks whether a given post type entry supports Activités de publication.
 *
 * @since  1.0.0
 *
 * @param  WP_Post $post The post type entry object.
 * @return boolean       True if the post type entry supports Activités de publication.
 *                       False otherwise.
 */
function post_activities_is_post_type_supported( WP_Post $post ) {
	$type   = get_post_type( $post );
	$retval = post_type_supports( $type, 'activites_de_publication' );

	if ( $retval && 'page' === $type && in_array( $post->ID, bp_core_get_directory_page_ids(), true ) ) {
		$retval = false;
	}

	return $retval;
}

/**
 * Adds Support for Activités de publication to WordPress posts and pages.
 *
 * @since  1.0.0
 */
function post_activities_create_initial_supports() {
	add_post_type_support( 'post', 'activites_de_publication' );
	add_post_type_support( 'page', 'activites_de_publication' );
}
add_action( 'init', 'post_activities_create_initial_supports', 1 );

/**
 * Registers the post meta for the Post types supporting Activités de publication.
 *
 * @since  1.0.0
 */
function post_activities_register_meta() {
	$post_types = get_post_types_by_support( 'activites_de_publication' );

	$common_args = array(
		'type'        => 'boolean',
		'description' => __( 'Activer ou non les activités de publication', 'activites-de-publication' ),
		'single'      => true,
		'show_in_rest'=> true,
	);

	foreach ( $post_types as $post_type ) {
		register_post_meta( $post_type, 'activites_de_publication', $common_args );
	}
}
add_action( 'init', 'post_activities_register_meta', 50 );

/**
 * Registers the included Rest Activity Endpoint if the BP Rest API is not active on the site.
 *
 * @since  1.0.0
 */
function post_activities_rest_init() {
	if ( post_activities()->bp_rest_is_enabled ) {
		return;
	}

	$controller = new BP_REST_Activity_Endpoint();
	$controller->register_routes();
}
add_action( 'bp_rest_api_init', 'post_activities_rest_init' );

/**
 * Formats the Activités de publication activity action string.
 *
 * @since  1.0.0
 *
 * @param  string                      $action   The activity action string.
 * @param  BP_Activity_Activity|object $activity The activity object.
 * @return string                                The activity action string.
 */
function post_activities_format_activity_action( $action, $activity ) {
	$user_link = bp_core_get_userlink( $activity->user_id );

	return sprintf( __( '%s a partagé une activité de publication.', 'activites-de-publication' ), $user_link );
}

/**
 * Registers the Activités de publication activity action.
 *
 * @since  1.0.0
 */
function post_activities_register_activity_type() {
	bp_activity_set_action(
		buddypress()->activity->id,
		'publication_activity',
		__( 'Nouvelle activité de publication', 'activites-de-publication' ),
		'post_activities_format_activity_action',
		__( 'Activités de publication', 'activites-de-publication' ),
		array( 'activity', 'member' )
	);
}
add_action( 'bp_register_activity_actions', 'post_activities_register_activity_type' );

/**
 * Makes sure it's possible to post an Activité de publication from the BP Rest API.
 *
 * As the BP Rest API uses the `bp_activity_post_update()` function it is not possible to
 * send the extra attributes we need to type an Activité de publication.
 *
 * @todo   Open a ticket about this on https://github.com/buddypress/bp-rest
 *
 * @since  1.0.0
 *
 * @param  array $args The activity arguments to create an entry.
 * @return array       Unchanged or the Activité de publication arguments to create an entry.
 */
function post_activities_new_activity_args( $args = array() ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		if ( ! isset( $_POST['type'] ) || 'publication_activity' !== $_POST['type'] ) {
			return $args;
		}

		$postData = wp_parse_args( $_POST, array(
			'item_id'           => 0,
			'secondary_item_id' => 0,
			'hide_sitewide'     => false,
		) );

		$args = array_merge( $args, $postData, array(
			'primary_link' => get_permalink( (int) $postData['secondary_item_id'] ),
		) );
	}

	return $args;
}
add_filter( 'bp_after_activity_add_parse_args', 'post_activities_new_activity_args', 10, 1 );

/**
 * Fixes 2 BP_REST_Activity_Endpoint issues.
 *
 * 1. The `show_hidden` argument is missing in create_item().
 * 2. It should be possible to request for show_hidden in get_items().
 *
 * @param  array  $args The arguments for bp_activity_get().
 * @return array        The arguments for bp_activity_get().
 */
function post_activities_get_activity_args( $args = array() ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		if ( ! isset( $_REQUEST['type'] ) || 'publication_activity' !== $_REQUEST['type'] ) {
			return $args;
		}

		if ( isset( $_REQUEST['hide_sitewide'] ) && true === (bool) $_REQUEST['hide_sitewide'] ) {
			$args['show_hidden'] = true;
		}
	}

	return $args;
}
add_filter( 'bp_after_activity_get_parse_args', 'post_activities_get_activity_args', 10, 1 );

/**
 * Gets the Activity edit link used into the WordPress Administration.
 *
 * NB: This is used to be consistent with how WordPress let moderators edit the post
 * types comments.
 *
 * @since  1.0.0
 *
 * @param  integer $id The activity id.
 * @return string      The url to edit the activity from the WordPress Administration.
 */
function post_activities_get_activity_edit_link( $id = 0 ) {
	if ( ! $id ) {
		return '';
	}

	return add_query_arg( array(
		'page'   => 'bp-activity',
		'aid'    => $id,
		'action' => 'edit',
	), bp_get_admin_url( 'admin.php' ) );
}

/**
 * The BP Rest Activity Controller only returns raw values, we need to render the content.
 *
 * @todo   Open a ticket about this on https://github.com/buddypress/bp-rest
 *
 * @since  1.0.0
 *
 * @param  WP_REST_Response $response The BP Rest response.
 * @return WP_REST_Response           The "rendered" BP Rest response.
 */
function post_activities_prepare_bp_activity_value( WP_REST_Response $response ) {
	if ( isset( $response->data['content'] ) ) {
		$reset_activities_template = null;

		if ( isset( $GLOBALS['activities_template'] ) ) {
			$reset_activities_template = $GLOBALS['activities_template'];
		}

		// Temporarly overrides the `activities_template` global.
		$GLOBALS['activities_template'] = new stdClass;
		$GLOBALS['activities_template']->activity = (object) array( 'id' => $response->data['id'] );

		// Make sure the embed filters and actions are triggered.
		bp_activity_embed();

		// Do not truncate activities.
		add_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		$response->data['content'] = apply_filters( 'bp_get_activity_content_body', $response->data['content'] );

		// Restore the filter to truncate activities.
		remove_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		// Restore the `activities_template` global.
		$GLOBALS['activities_template'] = $reset_activities_template;

		// Add needed data for the user.
		$response->data['user_name'] = bp_core_get_user_displayname( $response->data['user'] );
		$response->data['user_link'] = apply_filters( 'bp_get_activity_user_link', bp_core_get_user_domain( $response->data['user'] ) );

		// Add needed meta data
		$timestamp = strtotime( $response->data['date'] );
		$response->data['human_date'] = sprintf(
			__( '%1$s à %2$s', 'activites-de-publication' ),
			date_i18n( get_option( 'date_format' ), $timestamp ),
			date_i18n( get_option( 'time_format' ), $timestamp )
		);

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$response->data['edit_link'] = esc_url_raw( post_activities_get_activity_edit_link( $response->data['id'] ) );
		}
	}

	return $response;
}
add_filter( 'rest_prepare_buddypress_activity_value', 'post_activities_prepare_bp_activity_value', 10, 1 );

/**
 * Adds the number of activities and pages into the corresponding Request headers.
 *
 * @todo   Open a ticket about this on https://github.com/buddypress/bp-rest
 *
 * @since  1.0.0
 *
 * @param  array            $activities The Result of the activity query.
 * @param  WP_REST_Response $response   The BP Rest response.
 * @param  WP_REST_Request  $request    The BP Rest request.
 */
function post_activities_get_bp_activities( $activities = array(), WP_REST_Response $response, WP_REST_Request $request ) {
	if ( ! isset( $activities['activities'] ) || ! isset( $activities['total'] ) ) {
		return;
	}

	$page             = $request->get_param( 'page' );
	$per_page         = $request->get_param( 'per_page' );
	$total_activities = (int) $activities['total'];

	if ( ! $page || ! $per_page ) {
		return;
	}

	$max_pages = ceil( $total_activities / (int) $per_page );

	$response->header( 'X-WP-Total', (int) $total_activities );
	$response->header( 'X-WP-TotalPages', (int) $max_pages );
}
add_action( 'rest_activity_get_items', 'post_activities_get_bp_activities', 10, 3 );

/**
 * Registers needed JavaScript for the front end.
 *
 * @since  1.0.0
 */
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

/**
 * Adds our templates directory to the BuddyPress templates stack.
 *
 * @since  1.0.0
 *
 * @param  array $stack The BuddyPress templates stack.
 * @return array $stack The BuddyPress templates stack.
 */
function post_activities_get_template_stack( $stack = array() ) {
	return array_merge( $stack, array( post_activities_bp_templates_dir() ) );
}

/**
 * Locates template assets for Activités de publication.
 *
 * NB: This is used to ease users CSS overrides. They simply need to add a file named
 * activites-de-publication into a /buddypress/css/ sub-folder of their theme.
 *
 * @since  1.0.0
 *
 * @param  string $asset The asset to locate.
 * @return array         URI and Path of the located asset.
 */
function post_activities_locate_bp_template_asset( $asset = '' ) {
	add_filter( 'bp_get_theme_compat_dir', 'post_activities_bp_templates_dir' );
	add_filter( 'bp_get_theme_compat_url', 'post_activities_bp_templates_url' );

	$located = bp_locate_template_asset( $asset );

	remove_filter( 'bp_get_theme_compat_dir', 'post_activities_bp_templates_dir' );
	remove_filter( 'bp_get_theme_compat_url', 'post_activities_bp_templates_url' );

	return $located;
}

/**
 * Adds needed JavaScript to the loading queue.
 *
 * @since  1.0.0
 */
function post_activities_front_enqueue_scripts() {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_post();

	if ( ! post_activities_is_post_type_supported( $post ) || true !== (bool) get_post_meta( $post->ID, 'activites_de_publication', true ) ) {
		return;
	}

	// Take care of the Post status.
	$post_status     = get_post_status( $post );
	$post_status_obj = get_post_status_object( $post_status );
	$hide_sitewide   = 0;

	if ( true === (bool) $post_status_obj->private ) {
		$hide_sitewide = 1;
	}

	// No need to use conversations in draft mode.
	if ( true === (bool) $post_status_obj->protected && false === (bool) $post_status_obj->publicly_queryable ) {
		return;
	}

	// Temporarly overrides the BuddyPress Template Stack.
	add_filter( 'bp_get_template_stack', 'post_activities_get_template_stack' );

	$min = post_activities_min_suffix();
	$css = post_activities_locate_bp_template_asset( "css/activites-de-publication{$min}.css" );

	if ( isset( $css['uri'] ) ) {
		wp_enqueue_style( 'activites-d-article-front-style', $css['uri'], array(), post_activities_version() );
	}

	wp_enqueue_script( 'activites-d-article-front-script' );
	wp_localize_script( 'activites-d-article-front-script', '_activitesDePublicationSettings', array(
		'versionString'     => 'buddypress/v1',
		'primaryID'         => get_current_blog_id(),
		'secondaryID'       => $post->ID,
		'hideSitewide'      => $hide_sitewide,

		/**
		 * Filter here if you wish to edit the number of activities per page.
		 *
		 * @since  1.0.0
		 *
		 * @param  integer $value 20 (The same as default BuddyPress activity loop).
		 * @param  WP_Post $post  The current post object.
		 */
		'activitiesPerPage' => apply_filters( 'post_activities_per_page', 20, $post ),

		'mustLogIn'         => sprintf(
			/* translators: %s: login URL */
			__( 'Vous devez <a href="%s">être connecté·e</a> pour afficher ou publier des conversations.', 'activites-de-publication' ),
			wp_login_url( apply_filters( 'the_permalink', get_permalink( $post->ID ), $post->ID ) )
		),
		'publishLabel'         => __( 'Publier', 'activites-de-publication' ),
		'textareaPlaceholder'  => __( 'Participez aux conversations !', 'activites-de-publication' ),
		'loadingConversations' => __( 'Merci de patienter pendant le chargement des conversations.', 'activites-de-publication' ),
		'noConversations'      => __( 'Aucune conversation initiée, soyez le premier à en démarrer une !', 'activites-de-publication' ),
		'errors' => array(
			'rest_authorization_required'        => __( 'Désolé, vous n’êtes pas autorisé·e à consulter les activités de cette publication.', 'activites-de-publication' ),
			'rest_user_cannot_create_activity'   => __( 'Désolé, nous ne sommes pas en mesure de créer cette activité de publication.', 'activites-de-publication' ),
			'rest_authorization_required'        => __( 'Désolé, vous n’êtes pas autorisé·e à créer des activités pour cette publication.', 'activites-de-publication' ),
			'rest_create_activity_empty_content' => __( 'Merci d’ajouter du contenu à votre activité de publication à l’aide du champ texte multiligne.', 'activites-de-publication' ),
		),
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
				/* translators: %s = member name - already translated for BuddyPress */
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
				/* translators: %s = member first name - already translated for BuddyPress */
				'whatsnewPlaceholder' => sprintf( __( "What's new, %s?", 'buddypress' ), bp_get_user_firstname( bp_get_loggedin_user_fullname() ) ),
				/* translators: already translated for BuddyPress */
				'whatsnewLabel'       => __( 'Post what\'s new', 'buddypress' ),
				/* translators: already translated for BuddyPress */
				'whatsnewpostinLabel' => __( 'Post in', 'buddypress' ),
				/* translators: already translated for BuddyPress */
				'postUpdateButton'    => __( 'Post Update', 'buddypress' ),
				/* translators: already translated for BuddyPress */
				'cancelButton'        => __( 'Cancel', 'buddypress' ),
			)
		),
	) );

	// 1000 seems a later enough priority.
	add_filter( 'the_content', 'post_activities_js_templates', 1000 );
}
add_action( 'bp_enqueue_scripts', 'post_activities_front_enqueue_scripts', 14 );

/**
 * Catches Templates to inject it at the end of the content.
 *
 * @since  1.0.0
 *
 * @param  string $content The Post type content.
 * @return string          The Post type content.
 */
function post_activities_js_templates( $content = '' ) {
	$path = trailingslashit( buddypress()->theme_compat->packages['nouveau']->__get( 'dir' ) );

	if ( ! function_exists( 'bp_nouveau_activity_hook' ) ) {
		require_once( $path . 'includes/template-tags.php' );
		require_once( $path . 'includes/activity/template-tags.php' );
	}

	ob_start();

	// Load the Post Form template
	require_once( $path . 'buddypress/common/js-templates/activity/form.php' );

	// Load the Entry template
	bp_get_template_part( 'common/js-templates/activity/activites-de-publication' );

	$templates = ob_get_clean();

	// Remove temporary overrides.
	remove_filter( 'the_content', 'post_activities_js_templates', 1000 );
	remove_filter( 'bp_get_template_stack', 'post_activities_get_template_stack' );

	// Append the templates to the Post content.
	return $content . $templates;
}

/**
 * Use a specific activity/entry template for the Activités de Publication activities.
 *
 * NB: This is used to make sure there are no other button actions than the edit one.
 *
 * @since  1.0.0
 *
 * @param  array  $templates The list of possible templates for the slug.
 * @param  string $slug      The slug of the requested template.
 * @return array             The list of possible templates for the slug.
 */
function post_activities_get_activity_entry_template_part( $templates = array(), $slug = '' ) {
	if ( 'activity/entry' !== $slug || ! isset( $GLOBALS['activities_template']->activity->type ) || 'publication_activity' !== $GLOBALS['activities_template']->activity->type ) {
		return $templates;
	}

	// Temporarly overrides the BuddyPress Template Stack.
	add_filter( 'bp_get_template_stack', 'post_activities_get_template_stack' );

	// Use a specific template for the active template pack
	$theme_compat_id = bp_get_theme_compat_id();
	if ( 'nouveau' !== $theme_compat_id && 'legacy' !== $theme_compat_id ) {
		$theme_compat_id = 'legacy';
	}

	return array_merge( array( "activity/entry-{$theme_compat_id}.php" ), $templates );
}
add_filter( 'bp_get_template_part', 'post_activities_get_activity_entry_template_part', 10, 2 );

/**
 * Removes the temporary filter used to override the activity/entry template.
 *
 * @since  1.0.0
 *
 * @param  string $located The located template.
 */
function post_activities_located_entry_template_part( $located = '' ) {
	if ( false !== strpos( $located, 'activity/entry' ) ) {
		remove_filter( 'bp_get_template_stack', 'post_activities_get_template_stack' );
	}
}
add_action( 'bp_locate_template', 'post_activities_located_entry_template_part', 10, 1 );

/**
 * Gets the activity id.
 *
 * @since  1.0.0
 *
 * @param  BP_Activity_Activity|object $activity The activity object.
 * @return integer                               The activity id.
 */
function post_activities_get_activity_id( $activity = null ) {
	$id = '';

	if ( empty( $activity->id ) ) {
		global $activities_template;

		if ( isset( $activities_template->activity->id ) ) {
			$id = $activities_template->activity->id;
		}
	} else {
		$id = $activity->id;
	}

	return (int) $id;
}

/**
 * Gets the activity type.
 *
 * @since  1.0.0
 *
 * @param  BP_Activity_Activity|object $activity The activity object.
 * @return string                                The activity type.
 */
function post_activities_get_activity_type( $activity = null ) {
	$type = '';

	if ( empty( $activity->type ) ) {
		global $activities_template;

		if ( isset( $activities_template->activity->type ) ) {
			$type = $activities_template->activity->type;
		}
	} else {
		$type = $activity->type;
	}

	return $type;
}

/**
 * Overrides the BuddyPress check for the delete cap.
 *
 * @since  1.0.0
 *
 * @param  boolean                     $can_delete Wether the user can delete the activity or not.
 * @param  BP_Activity_Activity|object $activity   The activity object.
 * @return boolean                                 True if the user can delete the activity.
 *                                                 False otherwise.
 */
function post_activities_can_delete( $can_delete = false, $activity = null ) {
	$type = post_activities_get_activity_type( $activity );

	if ( 'publication_activity' === $type ) {
		$can_delete = bp_current_user_can( 'bp_moderate' );
	}

	return $can_delete;
}
add_filter( 'bp_activity_user_can_delete', 'post_activities_can_delete', 20, 2 );

/**
 * Overrides the BuddyPress check for the comment cap.
 *
 * @since  1.0.0
 *
 * @param  boolean $can_comment Wether the user can comment the activity or not.
 * @param  string  $type        The activity type.
 * @return boolean              True if the user can comment the activity.
 *                              False otherwise.
 */
function post_activities_can_comment( $can_comment = false, $type = '' ) {
	if ( 'publication_activity' === $type ) {
		$can_comment = false;
	}

	return $can_comment;
}
add_filter( 'bp_activity_can_comment', 'post_activities_can_comment', 10, 2 );

/**
 * Gets the URL of the BuddyPress delete link.
 *
 * @since  1.0.0
 *
 * @return string The URL of the BuddyPress delete link.
 */
function post_activities_get_delete_activity_url() {
	global $activities_template;

	if ( ! isset( $activities_template->activity ) ) {
		return '';
	}

	$delete_url = bp_get_activity_delete_url();

	if ( bp_is_activity_component() && is_numeric( bp_current_action() ) ) {
		$delete_url = str_replace( '&amp;', '&#038;', $delete_url );
	}

	return $delete_url;
}

/**
 * Overrides the link to delete the activity.
 *
 * @since  1.0.0
 *
 * @param  string                      $delete_link The activity delete link.
 * @param  BP_Activity_Activity|object $activity    The activity object.
 * @return string                                   The URL to delete the activity
 *                                                  from the WordPress Administration.
 */
function post_activities_moderate_link( $delete_link = '', $activity = null ) {
	if ( 'nouveau' === bp_get_theme_compat_id() ) {
		return $delete_link;
	}

	$id   = post_activities_get_activity_id( $activity );
	$type = post_activities_get_activity_type( $activity );

	if ( 'publication_activity' !== $type || ! $id ) {
		return $delete_link;
	}

	return str_replace( array(
		post_activities_get_delete_activity_url(),
		/* translators: already translated for BuddyPress */
		__( 'Delete', 'buddypress' ),
		' confirm',
		'delete-activity'
	), array(
		esc_url( post_activities_get_activity_edit_link( $id ) ),
		__( 'Modifier', 'activites-de-publication' ),
		'',
		'edit-activity',
	), $delete_link );
}
add_filter( 'bp_get_activity_delete_link', 'post_activities_moderate_link', 10, 1 );

/**
 * Overrides BP Nouveau action buttons for the Activités de publication.
 *
 * @since  1.0.0
 *
 * @param  array   $buttons The list of buttons passed by reference.
 * @param  integer $id      The activity id these buttons relate to.
 * @return array            The list of buttons.
 */
function post_activities_get_nouveau_activity_entry_buttons( &$buttons = array(), $id = 0 ) {
	if ( 'publication_activity' !== bp_get_activity_type() ) {
		return $buttons;
	}

	unset( $buttons['activity_favorite'] );

	if ( ! empty( $buttons['activity_delete'] ) && $id ) {
		$buttons['activity_delete'] = str_replace( array(
			post_activities_get_delete_activity_url(),
			' confirm',
			'delete-activity',
			'<span class="bp-screen-reader-text"></span>'
		), array(
			esc_url( post_activities_get_activity_edit_link( $id ) ),
			'',
			'edit-activity',
			__( 'Modifier', 'activites-de-publication' )
		), $buttons['activity_delete'] );
	}

	return $buttons;
}
add_filter( 'bp_nouveau_return_activity_entry_buttons', 'post_activities_get_nouveau_activity_entry_buttons', 10, 2 );

/**
 * Overrides the BuddyPress check for the favorite cap.
 *
 * @since  1.0.0
 *
 * @param  boolean                     $can_favorite Wether the user can favorite the activity or not.
 * @param  BP_Activity_Activity|object $activity     The activity object.
 * @return boolean                                   True if the user can favorite the activity.
 *                                                   False otherwise.
 */
function post_activities_can_favorite( $can_favorite = true, $activity = null ) {
	if ( 'nouveau' === bp_get_theme_compat_id() ) {
		return $can_favorite;
	}

	$type = post_activities_get_activity_type( $activity );

	if ( 'publication_activity' === $type ) {
		$can_favorite = false;
	}

	return $can_favorite;
}
add_filter( 'bp_activity_can_favorite', 'post_activities_can_favorite', 20, 1 );

/**
 * Overrides the activity permalink for Activités de publication.
 *
 * @since  1.0.0
 *
 * @param  string                      $link     The activity permalink.
 * @param  BP_Activity_Activity|object $activity The activity object passed by reference.
 * @return string                                The activity permalink.
 */
function post_activities_get_activity_permalink( $link = '', &$activity = null ) {
	if ( isset( $activity->type ) && 'publication_activity' === $activity->type && is_buddypress() ) {
		$link = $activity->primary_link;
	}

	return $link;
}
add_filter( 'bp_activity_get_permalink', 'post_activities_get_activity_permalink', 10, 2 );
