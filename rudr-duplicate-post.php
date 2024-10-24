<?php
/**
 * Plugin name: Duplicate Posts and Pages in One Click
 * Version: 1.0
 * Description: This simple plugin allows to duplicate a page or a post in WordPress in one click. It is very simple and you don't need to use some heavy plugins for that.
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * Plugin URI: https://rudrastyh.com/wordpress/duplicate-post.html
 * License: GPLv2
 */

// Add the duplicate link to action list for post_row_actions
// for "post" and custom post types
add_filter( 'post_row_actions', 'rudr_duplicate_post_link', 25, 2 );
// for "page" post type
add_filter( 'page_row_actions', 'rudr_duplicate_post_link', 25, 2 );

function rudr_duplicate_post_link( $actions, $post ) {

	if( ! current_user_can( 'edit_posts' ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'rudr_duplicate_post_as_draft',
				'post' => $post->ID,
			),
			'admin.php'
		),
		basename(__FILE__),
	);

	$actions[ 'duplicate' ] = '<a href="' . esc_url( $url ) . '" title="Duplicate this item">Duplicate</a>';

	return $actions;
}

/*
 * Function creates post duplicate as a draft and redirects then to the edit post screen
 */
// add_action( 'admin_action_{action name}'
add_action( 'admin_action_rudr_duplicate_post_as_draft', 'rudr_duplicate_post_as_draft' );

function rudr_duplicate_post_as_draft(){

	// check if post ID has been provided and action
	if ( empty( $_GET[ 'post' ] ) ) {
		wp_die( 'No post to duplicate has been provided!' );
	}

	// Nonce verification
	check_admin_referer( basename( __FILE__ ) );

	// Get the original post ID
	$post_id = absint( $_GET[ 'post' ] );

	// And all the original post data then
	$post = get_post( $post_id );

	/*
	 * if you don't want current user to be the new post author,
	 * then change next couple of lines to this: $new_post_author = $post->post_author;
	 */
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;

	// if post data exists (I am sure it is, but just in a case), create the post duplicate
	if( $post ) {

		// new post data array
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft', // $post->post_status,
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);

		// insert the post by wp_insert_post() function
		$new_post_id = wp_insert_post( $args );

		/*
		 * get all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies( $post->post_type ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		if( $taxonomies ) {
			foreach( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
			}
		}

		// duplicate all post meta
		$post_meta = get_post_meta( $post_id );
		if( $post_meta ) {

			foreach ( $post_meta as $meta_key => $meta_values ) {
				// we need to exclude some system meta keys
				if( in_array( $meta_key, array( '_edit_lock', '_wp_old_slug' ) ) ) {
					continue;
				}
				// do not forget that each meta key can have multiple values
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_post_id, $meta_key, $meta_value );
				}
			}
		}

		// finally, redirect to the edit post screen for the new draft
		// wp_safe_redirect(
		// 	add_query_arg(
		// 		array(
		// 			'action' => 'edit',
		// 			'post' => $new_post_id
		// 		),
		// 		admin_url( 'post.php' )
		// 	)
		// );
		// exit;
		// or we can redirect to all posts with a message
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => ( 'post' !== $post->post_type ? $post->post_type : false ),
					'saved' => 'post_duplicate_created' // just a custom slug here
				),
				admin_url( 'edit.php' )
			)
		);
		exit;

	} else {
		wp_die( 'We can not duplicate the post because we can not find it.' );
	}

}

/*
 * In case we decided to add admin notices
 */
add_action( 'admin_notices', 'rudr_duplication_admin_notice' );

function rudr_duplication_admin_notice() {

	// Get the current screen
	$screen = get_current_screen();
	if( 'edit' !== $screen->base ) {
		return;
	}

   if( isset( $_GET[ 'saved' ] ) && 'post_duplicate_created' === $_GET[ 'saved' ] ) {

		echo '<div class="notice notice-success is-dismissible"><p>Post copy created.</p></div>';

   }
}
