<?php
/**
 * WordPress Link Template Functions
 *
 * @package WordPress
 * @subpackage Template
 */

/**
 * Retrieve the avatar URL for a user, email address, MD5 hash, comment, or post.
 *
 * @uses apply_filters() 'pre_get_avatar_url' to bypass
 * @uses apply_filters() 'get_avatar_url' filters the result
 * @uses apply_filters() 'get_avatar_comment_types' filters the comment types for which we can calculate an avatar
 *
 * @since 3.6
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
 * 		404                    : Return a 404 instead of a default image
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
 * 	&processed_args : (array) Pass as reference.  When the function returns, the value will be the processed/sanitized $args plus a "found_avatar" guess.
 *
 * @return bool|string URL false on failure
 */
function get_avatar_url( $id_or_email, $args = null ) {
	$original_args = $args;

	$args = wp_parse_args( $args, array(
		'size'           => 96,
		'default'        => get_option( 'avatar_default', 'mystery' ),
		'force_default'  => false,
		'rating'         => get_option( 'avatar_rating' ),
		'scheme'         => null,
		'processed_args' => null, // if used, should be a reference
	) );

	if ( is_numeric( $args['size'] ) ) {
		$args['size'] = absint( $args['size'] );
		if ( !$args['size'] ) {
			$args['size'] = 96;
		}
	} else {
		$args['size'] = 96;
	}

	if ( empty( $args['default'] ) ) {
		$args['default'] = 'mystery';
	}

	switch ( $args['default'] ) {
	case 'mm' :
	case 'mystery' :
	case 'mysteryman' :
		$args['default'] = 'mm';
		break;
	case 'gravatar_default' :
		$args['default'] = false;
		break;
	}

	$args['force_default'] = (bool) $args['force_default'];

	$args['rating'] = strtolower( $args['rating'] );

	$args['found_avatar'] = false;

	$url = apply_filters_ref_array( 'pre_get_avatar_url', array( null, $id_or_email, &$args, $original_args ) );
	if ( !is_null( $url ) ) {
		$return = apply_filters_ref_array( 'get_avatar_url', array( $url, $id_or_email, &$args, $original_args ) );
		$args['processed_args'] = $args;
		unset( $args['processed_args']['processed_args'] );
		return $return;
	}

	$email_hash = '';
	$user = $email = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		if ( strpos( $id_or_email, '@md5.gravatar.com' ) ) {
			// md5 hash
		        list( $email_hash ) = explode( '@', $id_or_email );
		} else {
			// email address
			$email = $id_or_email;
		}
	} elseif ( is_object( $id_or_email ) ) {
		if ( isset( $id_or_email->comment_ID ) ) {
			// Comment Object

			// No avatar for pingbacks or trackbacks
			$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
			if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) ) {
				$args['processed_args'] = $args;
				unset( $args['processed_args']['processed_args'] );
				return false;
			}

			if ( ! empty( $id_or_email->user_id ) ) {
				$user = get_user_by( 'id', (int) $id_or_email->user_id );
			}
			if ( ( !$user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
				$email = $id_or_email->comment_author_email;
			}
		} elseif ( ! empty( $id_or_email->user_login ) ) {
			// User Object
			$user = $id_or_email;
		} elseif ( ! empty( $id_or_email->post_author ) ) {
			// Post Object
			$user = get_user_by( 'id', (int) $id_or_email->post_author );
		}
	}
	
	if ( !$email_hash ) {
		if ( $user ) {
			$email = $user->user_email;
		}

		if ( $email ) {
			$email_hash = md5( strtolower( trim( $email ) ) );
		}
	}

	if ( $email_hash ) {
		$args['found_avatar'] = true;
	}

	$url_args = array(
		's' => $args['size'],
		'd' => $args['default'],
		'f' => $args['force_default'] ? 'y' : false,
		'r' => $args['rating'],
	);

	$url = sprintf( 'http://%d.gravatar.com/avatar/%s', hexdec( $email_hash[0] ) % 3, $email_hash );

	$url = add_query_arg(
		rawurlencode_deep( array_filter( $url_args ) ),
		set_url_scheme( $url, $args['scheme'] )
	);

	$return = apply_filters_ref_array( 'get_avatar_url', array( $url, $id_or_email, &$args, $original_args ) );
	$args['processed_args'] = $args;
	unset( $args['processed_args']['processed_args'] );
	return $return;
}
