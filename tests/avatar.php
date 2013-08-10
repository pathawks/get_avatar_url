<?php

/**
 * Test get_avatar_url() and get_avatar()
 *
 * @group avatar
 */
class Tests_Avatar extends WP_UnitTestCase {
	const USER_EMAIL         = 'UsEr@exaMple.oRg';
	const DELETED_USER_EMAIL = 'dOdO@ExampLe.oRg';
	const GUEST_EMAIL        = 'GuesT@eXample.oRg';

	const USER_EMAIL_MD5          = '572c3489ea700045927076136a969e27'; // 2 == hexdec( "5" ) % 3
	const DELETED_USER_EMAIL_MD5  = '7736907f09ecae4d1400c18f499a61f8'; // 1 == hexdec( "7" ) % 3
	const GUEST_EMAIL_MD5         = 'cde48bd0bea0bbc6bc6bb02a461d946e'; // 0 == hexdec( "c" ) % 3

	const UNKNOWN_COMMENT_TYPE = 'unknown';
	const ALLOWED_COMMENT_TYPE = 'allowed';

	var $user_id         = 0;
	var $deleted_user_id = 0;

	var $comment_by_user         = 0;
	var $comment_by_deleted_user = 0;
	var $comment_by_guest        = 0;
	var $unknown_comment_type    = 0;
	var $allowed_comment_type    = 0;

	var $post_by_user = 0;

	var $img_structure;

	function setUp() {
		parent::setUp();

		$this->user_id = $this->factory->user->create( array(
			'user_email' => self::USER_EMAIL,
		) );

		$this->deleted_user_id = $this->factory->user->create( array(
			'user_email' => self::DELETED_USER_EMAIL,
		) );

		$this->comment_by_user = $this->factory->comment->create( array(
			'user_id' => $this->user_id,
		) );

		$this->comment_by_deleted_user = $this->factory->comment->create( array(
			'user_id' => $this->deleted_user_id,
			'comment_author_email' => self::DELETED_USER_EMAIL,
		) );

		$this->comment_by_guest = $this->factory->comment->create( array(
			'user_id' => 0,
			'comment_author_email' => self::GUEST_EMAIL,
		) );

		$this->comment_by_noone = $this->factory->comment->create( array(
			'user_id' => 0,
			'comment_author_email' => '',
		) );

		$this->unknown_comment_type = $this->factory->comment->create( array(
			'user_id' => $this->user_id,
			'comment_type' => self::UNKNOWN_COMMENT_TYPE,
		) );

		$this->allowed_comment_type = $this->factory->comment->create( array(
			'user_id' => $this->user_id,
			'comment_type' => self::ALLOWED_COMMENT_TYPE,
		) );

		$this->post_by_user = $this->factory->post->create( array(
			'post_author' => $this->user_id,
		) );

		if ( is_multisite() ) {
			wpmu_delete_user( $this->deleted_user_id );
		} else {
			wp_delete_user( $this->deleted_user_id );
		}

		$dom = new DomDocument;
		$dom->loadHTML( '<img />' );
		$this->img_structure = $dom->documentElement->firstChild->firstChild;
		foreach ( array( 'src', 'height', 'width', 'class', 'alt' ) as $name ) {
			$this->img_structure->setAttribute( $name, 'true' );
		}
	}	

/* Tests */

	/**
	 * Make sure our tests are set up correctly.
	 *
	 * @dataProvider md5s
	 */
	function test_md5( $email, $expected_md5 ) {
		$this->assertEquals( $expected_md5, md5( strtolower( trim( $email ) ) ) );
	}

	function md5s() {
		return array(
			// $email, $expected_md5
			array( self::USER_EMAIL        , self::USER_EMAIL_MD5 ),
			array( self::DELETED_USER_EMAIL, self::DELETED_USER_EMAIL_MD5 ),
			array( self::GUEST_EMAIL       , self::GUEST_EMAIL_MD5 ),
		);
	}

	/* Get Avatar By ... */
	/*	User ID */

	function test_by_user_id() {
		$avatar_html = get_avatar( $this->user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	function test_by_user_id_deleted() {
		$avatar_html = get_avatar( $this->deletede_user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( '', $dom_img ); // User ID does not exist, so no email to hash

		$this->assertAvatarHTMLHasClasses( 'avatar-default', $dom_img );
	}

	function test_by_user_id_bad() {
		$avatar_html = get_avatar( 0 );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( '', $dom_img );

		$this->assertAvatarHTMLHasClasses( 'avatar-default', $dom_img );
	}

	function test_by_user_id_negative() {
		$avatar_html = get_avatar( -1 * $this->user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	/* 	Email Address or Hash */

	function emails() {
		return array(
			// $email, $expected_hash
			array( self::USER_EMAIL             , self::USER_EMAIL_MD5 ),
			array( ' ' . self::USER_EMAIL . "\t", self::USER_EMAIL_MD5 ), // whitespace should be stripped before MD5ing
			array(
				sprintf( '%s@md5.gravatar.com', self::USER_EMAIL_MD5 ), // this is how you generate an avatar for a specific MD5 hash
				self::USER_EMAIL_MD5
			),
		);
	}

	/**
	 * @dataProvider emails
	 */
	function test_by_email( $email, $expected_hash ) {
		$avatar_html = get_avatar( $email );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( $expected_hash, $dom_img );
	}

	/* 	User */

	function test_by_user() {
		$user = get_user_by( 'id', $this->user_id );

		$avatar_html = get_avatar( $user );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	/* 	Comment */

	function test_by_comment_by_user() {
		$comment = get_comment( $this->comment_by_user );

		$avatar_html = get_avatar( $comment );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	function test_by_comment_by_deleted_user() {
		$comment = get_comment( $this->comment_by_deleted_user );

		$avatar_html = get_avatar( $comment );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::DELETED_USER_EMAIL_MD5, $dom_img ); // Comment object has comment_author_email, so should fallback to that after user_id not found
	}

	function test_by_comment_by_guest() {
		$comment = get_comment( $this->comment_by_guest );

		$avatar_html = get_avatar( $comment );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::GUEST_EMAIL_MD5, $dom_img );
	}

	function test_by_comment_by_noone() {
		$comment = get_comment( $this->comment_by_noone );

		$avatar_html = get_avatar( $comment );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( '', $dom_img );
	}

	function test_by_unknown_comment_type() {
		$comment = get_comment( $this->unknown_comment_type );

		$this->assertEquals( self::UNKNOWN_COMMENT_TYPE, $comment->comment_type );

		$avatar_html = get_avatar( $comment );

		$this->assertFalse( $avatar_html );
	}

	function test_by_allowed_comment_type() {
		$comment = get_comment( $this->allowed_comment_type );

		$this->assertEquals( self::ALLOWED_COMMENT_TYPE, $comment->comment_type );

		add_filter( 'get_avatar_comment_types', array( $this, '_get_avatar_comment_types' ) );
		$avatar_html = get_avatar( $comment );
		remove_filter( 'get_avatar_comment_types', array( $this, '_get_avatar_comment_types' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	/* 	Post */

	function test_by_post() {
		$post = get_post( $this->post_by_user );

		$avatar_html = get_avatar( $post );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarMatchesHash( self::USER_EMAIL_MD5, $dom_img );
	}

	/* Parameters */
	/* 	Default Values for all parameters */
	function test_default_values() {
		$width = $height = 96; // $size          = 96
		$d     = 'mm';         // $default       = '' -> mystery
		$f     = false;        // $force_default = false
		$alt   = '';           // $alt           = ''
		$r     = 'g';          // $rating        = 'G'

		$avatar_html = get_avatar( $this->user_id );

		// No specifics in input
		$dom_img = $this->get_image_element( $avatar_html );

		// Test output matches expected defaults

		$this->assertAvatarHTMLAttributesMatch( compact( 'width', 'height', 'alt' ), $dom_img );

		$this->assertAvatarURLQueryParametersMatch( compact( 'd', 'f', 'r' ), $dom_img );

		// Test default scheme
		$this->assertStringStartsWith( 'http://', $dom_img->getAttribute( 'src' ) );
	}

	/* 	Size */

	function sizes() {
		return array(
			// $size, $expected_size
			array(   255, 255 ),
			array(  -255, 255 ),
			array(     0,  96 ),
			array( 'bad',  96 ),
		);
	}

	/**
	 * @dataProvider sizes
	 */
	function test_size_parameter( $size, $expected_size ) {
		$width = $height = $expected_size;

		$avatar_html = get_avatar( $this->user_id, $size );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLAttributesMatch( compact( 'width', 'height' ), $dom_img );
	}

	/**
	 * @dataProvider sizes
	 */
	function test_size_arg( $size, $expected_size ) {
		$width = $height = $expected_size;

		$avatar_html = get_avatar( $this->user_id, compact( 'size' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLAttributesMatch( compact( 'width', 'height' ), $dom_img );
	}

	/* 	Default */

	function defaults() {
		return array(
			// $default, $expected_d
			array( 'mm'              , 'mm' ),
			array( 'mystery'         , 'mm' ),
			array( 'mysteryman'      , 'mm' ),
			array( '404'             , '404' ),
			array(                404, '404' ),
			array( 'retro'           , 'retro' ),
			array( 'monsterid'       , 'monsterid' ),
			array( 'wavatar'         , 'wavatar' ),
			array( 'identicon'       , 'identicon' ),
			array( 'blank'           , 'blank' ),
			array( 'gravatar_default', false ), // There should be no d query string parameter
			array(
				'http://example.org/image.jpg?foo=bar%2F&one=two#fragment',
				'http://example.org/image.jpg?foo=bar%2F&one=two#fragment',
			),
		);
	}

	/**
	 * @dataProvider defaults
	 */
	function test_default_parameter( $default, $expected_d ) {
		$d = $expected_d;
		$avatar_html = get_avatar( $this->user_id, 96, $default );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 'd' ), $dom_img );
	}

	/**
	 * @dataProvider defaults
	 */
	function test_default_arg( $default, $expected_d ) {
		$d = $expected_d;
		$avatar_html = get_avatar( $this->user_id, compact( 'default' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 'd' ), $dom_img );
	}

	/* 	Alt */

	function alts() {
		return array(
			// $alt
			array( 'a\'b"c<d>h&i%j\\k' ),
		);
	}

	/**
	 * @dataProvider alts
	 */
	function test_alt_parameter( $alt ) {
		$avatar_html = get_avatar( $this->user_id, 96, '', $alt );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLAttributesMatch( compact( 'alt' ), $dom_img );
	}

	/**
	 * @dataProvider alts
	 */
	function test_alt_arg( $alt ) {
		$avatar_html = get_avatar( $this->user_id, compact( 'alt' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLAttributesMatch( compact( 'alt' ), $dom_img );
	}

	/* 	Extraneous Parameter */

	// The $size, $default, and $alt parameter should still work even if there are extraneous parameters tacked on the end
	function test_extraneous_parameter() {
		$s = 255;
		$d = '404';
		$alt = 'foo';
		$avatar_html = get_avatar( $this->user_id, $s, $d, $alt, 'extraneous' );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 's', 'd' ), $dom_img );

		$this->assertAvatarHTMLAttributesMatch( compact( 'alt' ), $dom_img );
	}

	/* 	Force Default */

	function test_force_default() {
		$force_default = true;
		$f = 'y';
		$avatar_html = get_avatar( $this->user_id, compact( 'force_default' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 'f' ), $dom_img );

		$this->assertAvatarHTMLHasClasses( 'avatar-default', $dom_img );
	}

	/* 	Rating */

	function test_rating() {
		$rating = 'PG';
		$r = 'pg';
		$avatar_html = get_avatar( $this->user_id, compact( 'rating' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 'r' ), $dom_img );
	}

	/* 	Scheme */

	function test_scheme_https() {
		$scheme = 'https';
		$avatar_html = get_avatar( $this->user_id, compact( 'scheme' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertStringStartsWith( 'https://', $dom_img->getAttribute( 'src' ) );
	}

	/**
 	 * @backupGlobals enabled
	 */
	function test_scheme_default_over_ssl() {
		$_SERVER['HTTPS'] = 'ON';

		$avatar_html = get_avatar( $this->user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertStringStartsWith( 'https://', $dom_img->getAttribute( 'src' ) );
	}

	/* 	Class */

	function classes() {
		return array(
			// $class, $expected_classes
			array( 'bar    foo'             , array( 'foo', 'bar' ) ),
			array( array( 'foo  ', '  bar' ), array( 'foo', 'bar' ) ),
		);
	}

	/**
	 * @dataProvider classes
	 */
	function test_class( $class, $expected_classes ) {
		$avatar_html = get_avatar( $this->user_id, compact( 'class' ) );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLHasClasses( $expected_classes, $dom_img );
	}

	/* 	Force Display */

	function test_force_display() {
		update_option( 'show_avatars', false ); // Turn off avatars

		$avatar_html = get_avatar( $this->user_id, array( 'force_display' => true ) );

		$this->get_image_element( $avatar_html );
	}

	/* Options */

	function test_option_show_avatars() {
		update_option( 'show_avatars', false );

		$avatar_html = get_avatar( $this->user_id );

		$this->assertFalse( $avatar_html );
	}

	function test_option_avatar_default() {
		update_option( 'avatar_default', '404' );

		$default = $d = '404';
		$avatar_html = get_avatar( $this->user_id, 96, $default );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( compact( 'd' ), $dom_img );
	}

	function test_option_avatar_rating_false() {
		update_option( 'avatar_rating', false );

		$avatar_html = get_avatar( $this->user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( array( 'r' => false ), $dom_img );
	}

	function test_option_avatar_rating_PG() {
		update_option( 'avatar_rating', 'PG' );

		$avatar_html = get_avatar( $this->user_id );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarURLQueryParametersMatch( array( 'r' => 'pg' ), $dom_img );
	}

	/* Filters */

	/* 	pre_get_avatar_url, get_avatar_url */

	// Used for both get_avatar and get_avatar_url filters
	function get_avatar_filters() {
		return array(
			array( 'pre_get_avatar' ),
			array( 'get_avatar' ),
		);
	}

	/**
	 * @requires function get_avatar_url
	 * @dataProvider get_avatar_filters
	 */
	function test_get_avatar_url_filters( $filter ) {
		add_filter( "{$filter}_url", '__return_zero' ); // pre_get_avatar_url, get_avatar_url

		$avatar_url = get_avatar_url( $this->user_id );

		remove_filter( "{$filter}_url", '__return_zero' );

		$this->assertSame( 0, $avatar_url );
	}

	/**
	 * @dataProvider get_avatar_filters
	 */
	function test_get_avatar_url_filters_and_process_args( $filter ) {
		$width = $height = 255;

		add_filter( "{$filter}_url", array( $this, '_get_avatar_url_and_process_args' ), 10, 3 ); // pre_get_avatar_url, get_avatar_url

		$avatar_html = get_avatar( $this->user_id );

		remove_filter( "{$filter}_url", array( $this, '_get_avatar_url_and_process_args' ), 10, 3 );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertAvatarHTMLAttributesMatch( compact( 'width', 'height' ), $dom_img );

		$this->assertAvatarMatchesHash( 'zzz', $dom_img );

	}

	/* 	pre_get_avatar, get_avatar */

	/**
	 * @dataProvider get_avatar_filters
	 */
	function test_get_avatar_filters( $filter ) {
		add_filter( $filter, '__return_zero' ); // pre_get_avatar, get_avatar

		$avatar_html = get_avatar( $this->user_id );

		remove_filter( $filter, '__return_zero' );

		$this->assertSame( 0, $avatar_html );
	}

	/* Domain Sharding */

	function domain_shards() {
		return array(
			array( self::GUEST_EMAIL       , 0 ),
			array( self::DELETED_USER_EMAIL, 1 ),
			array( self::USER_EMAIL        , 2 ),
		);
	}

	/**
	 * @dataProvider domain_shards
	 */
	function test_domain_sharding( $email, $shard ) {
		$avatar_html = get_avatar( $email );

		$dom_img = $this->get_image_element( $avatar_html );

		$this->assertStringStartsWith( "http://{$shard}.", $dom_img->getAttribute( 'src' ) );
	}

/* Helpers */

	/**
	 * Converts IMG tag HTML to a DOM_Element and asserts that the markup matches the expected structure.
	 *
	 * @param string $html_string IMG tag
	 *
	 * @return DOM_Element IMG element
	 */
	private function get_image_element( $html_string ) {
		$dom = new DOMDocument;
		$success = $dom->loadHTML( $html_string );
		$this->assertTrue( $success, 'Invalid HTML' );

		$this->assertTrue( $dom->documentElement->firstChild->firstChild->hasAttribute( 'src' ) );

		$this->assertEqualXMLStructure( $this->img_structure, $dom->documentElement->firstChild->firstChild, true, 'HTML does not match expected value' );

		return $dom->documentElement->firstChild->firstChild;
	}

	// Filter for ::test_by_allowed_comment_type()
	function _get_avatar_comment_types( $comment_types ) {
		$comment_types[] = self::ALLOWED_COMMENT_TYPE;

		return $comment_types;
	}

	// Filter for ::test_get_avatar_url_filters_and_process_args()
	function _get_avatar_url_and_process_args( $avatar_url, $id_or_email, &$args ) {
		$args['size'] = 255;

		return 'http://0.gravatar.com/avatar/zzz';
	}

/* Assertions */

	/**
	 * @param string $expected_hash
	 * @param DOM_Element $dom_img
	 */
	private function assertAvatarMatchesHash( $expected_hash, $dom_img ) {
		$src = $dom_img->getAttribute( 'src' );

		$this->assertRegExp( sprintf( '#/avatar/%s(/|\?|$)#', preg_quote( $expected_hash, '#' ) ), $src, "Hash mismatch: '$src' !~ '$expected_hash'" );
	}

	/**
	 * @param array $expected_parameters key/value array of expected query string parameters in the IMG's src URL.  To expect a parameter to net be present, pass false as the value.
	 * @param DOM_Element $dom_img
	 */
	private function assertAvatarURLQueryParametersMatch( $expected_parameters, $dom_img ) {
		$src = $dom_img->getAttribute( 'src' );

		$query = parse_url( $src, PHP_URL_QUERY );
		wp_parse_str( $query, $query_args );

		$expected_parameters = wp_parse_args( $expected_parameters );

		foreach ( $expected_parameters as $key => $value ) {
			if ( false === $value ) {
				$this->assertArrayNotHasKey( $key, $query_args );
			} else {
				$this->assertArrayHasKey( $key, $query_args );
				$this->assertEquals( $value, $query_args[$key] );
			}
		}
	}

	/**
	 * @param array $expected_atttributes key/value array of expected HTML attributes on the IMG element.  To expect a attribute to be present but not care about the value, pass null as the value.
	 * @param DOM_Element $dom_img
	 */
	private function assertAvatarHTMLAttributesMatch( $expected_attributes, $dom_img ) {
		foreach ( $expected_attributes as $name => $value ) {
			if ( is_null( $value ) ) {
				$this->assertTrue( $dom_img->hasAttribute( $name ) );
			} else {
				$this->assertEquals( $value, $dom_img->getAttribute( $name ) );
			}
		}
	}
	/**
	 * @param string|array $expected_classes expected classes on the IMG element.
	 * @param DOM_Element $dom_img
	 */
	private function assertAvatarHTMLHasClasses( $expected_classes, $dom_img ) {
		$expected_classes = preg_split( '/\s+/', join( ' ', (array) $expected_classes ), -1, PREG_SPLIT_NO_EMPTY );

		$actual_classes = $dom_img->getAttribute( 'class' );
		$actual_classes = preg_split( '/\s+/', join( ' ', (array) $actual_classes ), -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $expected_classes as $expected_class ) {
			$this->assertContains( $expected_class, $actual_classes, "Missing class $expected_class" );
		}
	}
}
