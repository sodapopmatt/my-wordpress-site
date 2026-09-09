<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NNR_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$labels = array(
			'name'                  => __( 'Events', 'nnr-events' ),
			'singular_name'         => __( 'Event', 'nnr-events' ),
			'add_new'               => __( 'Add New', 'nnr-events' ),
			'add_new_item'          => __( 'Add New Event', 'nnr-events' ),
			'edit_item'             => __( 'Edit Event', 'nnr-events' ),
			'new_item'              => __( 'New Event', 'nnr-events' ),
			'view_item'             => __( 'View Event', 'nnr-events' ),
			'search_items'          => __( 'Search Events', 'nnr-events' ),
			'not_found'             => __( 'No events found', 'nnr-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash', 'nnr-events' ),
			'all_items'             => __( 'All Events', 'nnr-events' ),
			'menu_name'             => __( 'Events', 'nnr-events' ),
		);

		register_post_type(
			'event',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-calendar-alt',
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
			)
		);
	}
}
