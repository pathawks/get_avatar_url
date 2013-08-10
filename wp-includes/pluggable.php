<?php
/**
 * These functions can be replaced via plugins. If plugins do not redefine these
 * functions, then these will be used instead.
 *
 * @package WordPress
 */

if ( !function_exists( 'get_avatar' ) ) :
/**
 * Retrieve the avatar img tag for a user, email address, MD5 hash, comment, or post.
 *
 * @uses apply_filters() 'pre_get_avatar' to bypass
 * @uses apply_filters() 'get_avatar' filters the result
 *
 * @since 2.5
 * @param mixed $id_or_email The Gravatar to retrieve:
 * 	(int)    {user_id}                   : Use the email address of the corresponding user
 * 	(string) "{hash}@md5.gravatar.com"   : Use the hash directly
 * 	(string) "{email}"
 * 	(object) {User row or WP_User object}: Use the user's email
 * 	(object) {Post row or WP_Post object}: Use the post_author's email
 * 	(object) {Comment row}               : Use the comment's user_id or comment_author_email
 * @param array $args
 * 	size   : (int) Size of the avatar image
 * 	default: (string) URL for the default image or a default type:
 * 		404                    : Return a 404 instead of a default image @since 3.6
 * 		retro                  : 8bit
 * 		monsterid              : Monster
 * 		wavatar                : cartoon face
 * 		identicon              : the "quilt"
 * 		mystery, mm, mysteryman: The Oyster Man
 * 		blank                  : A transparent GIF
 * 		gravatar_default       : Gravatar Logo
 * 	force_default: (bool) Always show the default image, never the Gravatar
 * 	rating : display avatars up to the given rating: G < PG < R < X.
 *	scheme : (string) @see set_url_scheme()
 * 	alt    : (string) value for the img element's alt attribute
 * 	class  : (array|string) array or sttring of additional classes to add to the img element
 * 	force_display: (bool) Always show the avatar - ignore the show_avatars option
 *
 * @return bool|string <img> tag for the user's avatar.  False on failure.
 */
// Old Parameters:   $id_or_email, $size = 96, $default = '', $alt = '' )
function get_avatar( $id_or_email, $args = null ) {
	$defaults = array(
		// get_avatar_url() args
		'size'          => 96,
		'default'       => get_option( 'avatar_default', 'mystery' ),
		'force_default' => false,
		'rating'        => get_option( 'avatar_rating' ),
		'scheme'        => null,

		'alt'           => '',
		'class'         => null,
		'force_display' => false,
	);

	if ( is_scalar( $args ) ) {
		$args = array(
			'size' => $args,
		);

		$num_args = func_num_args();
		if ( $num_args > 4 ) {
			$num_args = 4;
		}

		switch ( $num_args ) {
		// no breaks
		case 4 :
			$args['alt'] = func_get_arg( 3 );
		case 3 :
			$args['default'] = func_get_arg( 2 );
		}
	} else {
		$args = (array) $args;
	}

	$original_args = $args;

	$args = wp_parse_args( $args, $defaults );

	$avatar = apply_filters_ref_array( 'pre_get_avatar', array( null, $id_or_email, &$args, $original_args ) );
	if ( !is_null( $avatar ) ) {
		return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args, $original_args );
	}

	if ( !$args['force_display'] && !get_option( 'show_avatars' ) ) {
		return false;
	}

	$processed_args = null;
	$args['processed_args'] =& $processed_args;
	$url = get_avatar_url( $id_or_email, $args );
	if ( !$url || is_wp_error( $url ) ) {
		return false;
	}

	$class = array( 'avatar', 'avatar-' . (int) $processed_args['size'], 'photo' );

	if ( !$processed_args['found_avatar'] || $processed_args['force_default'] ) {
		$class[] = ' avatar-default';
	}

	if ( $args['class'] ) {
		if ( is_array( $args['class'] ) ) {
			$class = array_merge( $class, $args['class'] );
		} else {
			$class[] = $args['class'];
		}
	}

	$avatar = sprintf(
		'<img alt="%s" src="%s" class="%s" height="%d" width="%d" />',
		esc_attr( $processed_args['alt'] ),
		esc_url( $url ),
		esc_attr( join( ' ', $class ) ),
		(int) $processed_args['size'],
		(int) $processed_args['size']
	);

	return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $processed_args, $original_args );
}
endif;
