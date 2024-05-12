<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPSEOAI_List_Table extends WP_List_Table {

	/** Class constructor */
	public function __construct() {
		parent::__construct( [
			'singular' => __( 'WPSEO.AI', 'ai-seo-wp' ),
			'plural'   => __( 'WPSEO.AI', 'ai-seo-wp' ),
			'ajax'     => false
		] );
	}

	/**
	 * Retrieve submission audit records, from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return array|object|stdClass[]|null
	 */
	public static function get_submissions(
		int $per_page = 20,
		int $page_number = 1
	) {
		global $wpdb, $wp_version;

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Your user account is not allowed to edit posts.', 'ai-seo-wp' ) );
		}

		if ( ! empty( $_POST[ 's' ] ) ) {
			check_admin_referer( 'wpseoai_dashboard', '_wpnonce_wpseoai' );
		}

		$args = [
			'post_type'      => WPSEOAI::POST_TYPE_RESPONSE,       // Assuming constant holds your CPT
			'posts_per_page' => intval( $per_page ),         // Pagination: number of items per page
			'paged'          => intval( $page_number ),      // Pagination: current page
			'meta_query'     => [                                  // Meta query for 'state'
				[
					'key'     => WPSEOAI::META_KEY_STATE,
					'compare' => 'EXISTS',
				],
			],
		];

		// Add search term condition
		if ( ! empty( $_POST[ 's' ] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_POST[ 's' ] ) );
			$args[ 's' ] = $search_term;
		}

		// Add ordering parameters
		if ( ! empty( $_GET[ 'orderby' ] ) && ! empty( $_GET[ 'order' ] ) ) {
			$args[ 'orderby' ] = sanitize_text_field( $_GET[ 'orderby' ] );
			$args[ 'order' ]   = sanitize_text_field( strtoupper( $_GET[ 'order' ] ) ) === 'ASC' ? 'ASC' : 'DESC';
		}

//		var_dump($args);

		// Create the WP_Query object
		$query = new WP_Query( $args );

		$data = [];

		// Loop over the results
		if ( $query->have_posts() ) {
			while( $query->have_posts() ) {
				$query->the_post();

				// Get the required data
				$post_id     = get_the_ID();
				$post_parent = get_post_field( 'post_parent', $post_id );
				$signature   = get_the_title( $post_id );
//				$summary = get_the_content( $post_id );
				$credits   = get_the_excerpt( $post_id );
				$post_date = get_the_date( 'Y-m-d H:i:s', $post_id );
				$state     = get_post_meta( $post_id, WPSEOAI::META_KEY_STATE, true );
				$title     = get_post_field( 'post_title', $post_parent );
				$post_type = get_post_field( 'post_type', $post_parent );

				$data[] = [
					'ID'          => $post_id,
					'title'       => $title,
					'post_type'   => $post_type,
					'state'       => $state,
					'post_parent' => $post_parent,
					'credits'     => $credits,
					'signature'   => $signature,
					'post_date'   => $post_date,
				];
			}
		}

		// Reset postdata
		wp_reset_postdata();

		return $data;
	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count(): string {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s";

		$args = [
			WPSEOAI::POST_TYPE_RESPONSE
		];

		// Prepare the query
		$query = $wpdb->prepare( $sql, $args );

		// Return executed query
		return $wpdb->get_var( $query );
	}

	/** Text displayed when no response data is available */
	public function no_items(): void {
		esc_html_e( 'No submissions have been made.', 'ai-seo-wp' );
	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_title(
		array $item
	): string {

		$retrieve_nonce = wp_create_nonce( 'retrieve' );
		$audit_nonce    = wp_create_nonce( 'audit' );

		$id = absint( $item[ 'ID' ] );

		$audit_url = wp_nonce_url( admin_url( 'admin.php?page=wpseoai_dashboard&action=audit&post_id=' . $id ), 'audit' );

		$title = '<strong><a href="' . esc_attr( sanitize_text_field( $audit_url ) ) . '">' . esc_html( $item[ 'title' ] ) . '</a></strong>';

		$actions = [];

		$state                 = get_post_meta( $id, WPSEOAI::META_KEY_JSON, true );
		$actions[ 'retrieve' ] = sprintf(
			'<a href="?page=wpseoai_dashboard&action=%s&post_id=%d&_wpnonce=%s">%s</a>',
			'retrieve',
			$id,
			esc_attr( $retrieve_nonce ),
			esc_html__( 'Retrieve', 'ai-seo-wp' )
		);

		if ( is_array( $state ) && array_key_exists( 'received', $state ) ) {
			$actions[ 'revision' ] = sprintf(
				'<a href="revision.php?revision=%d">%s</a>',
				absint( $state[ 'received' ][ 0 ][ 'post' ][ 'revision_id' ] ),
				esc_html__( 'Revision', 'ai-seo-wp' )
			);
		} else {
			$actions[ 'revision' ] = '<span class="disabled">Revision</span>';
		}

		$actions[ 'audit' ] = sprintf(
			'<a href="?page=wpseoai_dashboard&action=%s&post_id=%d&_wpnonce=%s">%s</a>',
			'audit',
			$id,
			esc_attr( $audit_nonce ),
			esc_html__( 'Audit', 'ai-seo-wp' )
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return string
	 */
	public function column_default(
		$item,
		$column_name
	): string {
		switch ( $column_name ) {
			case 'post_parent':
				$url = admin_url( sprintf( 'post.php?post=%d&action=%s', $item[ 'post_parent' ], 'edit' ) );

				return '<a href="' . esc_attr( sanitize_text_field( $url ) ) . '">' . esc_html( sanitize_text_field( $item[ 'post_parent' ] ) ) . '</a>';
			case 'state':
				return $item[ 'state' ] === '1' ? 'Complete' : 'Pending';
			case 'credits':
				return ! empty( $item[ $column_name ] ) ? esc_html( sanitize_text_field( $item[ $column_name ] ) ) : '&ndash;';
			case 'signature':
				return esc_html( $item[ $column_name ] );
			case 'post_type':
				$pto = get_post_type_object( $item[ $column_name ] );
				if ( is_null( $pto ) ) {
					return esc_html__( 'Unknown', 'ai-seo-wp' );
				}

				return esc_html( sanitize_text_field( $pto->labels->singular_name ?? $pto->label ) );
//				echo $pt->labels->name;
			case 'post_date':
				return esc_html( gmdate( 'jS F, h:i:s a', strtotime( esc_attr( sanitize_text_field( $item[ $column_name ] ) ) ) ) );
			default:
				return esc_html( sanitize_text_field( serialize( $item ) ) );
//				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb(
		$item
	): string {
		// TODO: TO BE IMPLEMENTED
		return '';
	}

	/**
	 * Associative array of columns
	 *
	 * @return array
	 */
	function get_columns(): array {
		return [
//			'cb'          => '<input type="checkbox" />',
			'title'       => esc_html__( 'Title', 'ai-seo-wp' ),
			'post_type'   => esc_html__( 'Type', 'ai-seo-wp' ),
			'state'       => esc_html__( 'Status', 'ai-seo-wp' ),
			'post_parent' => esc_html__( 'Parent ID', 'ai-seo-wp' ),
			'credits'     => esc_html__( 'Credits', 'ai-seo-wp' ),
			'signature'   => esc_html__( 'Signature', 'ai-seo-wp' ),
			'post_date'   => esc_html__( 'Date', 'ai-seo-wp' )
		];
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		global $wp_version;
		// See: https://core.trac.wordpress.org/changeset/55151
		if ( version_compare( $wp_version, '6.2.0' ) >= 0 ) {
			return [
				'title'       => [ 'title', 'asc' ],
				'post_parent' => [ 'post_parent', 'desc' ],
				'post_type'   => [ 'post_type', 'asc' ],
				'state'       => [ 'state', 'asc' ],
				'credits'     => [ 'credits', 'desc' ],
				'signature'   => [ 'signature', 'asc' ],
				'post_date'   => [ 'post_date', 'desc' ]
			];
		}

		return [];
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @return string Name of the default primary column, in this case, an empty string.
	 * @since 4.3.0
	 *
	 */
	protected function get_default_primary_column_name(): string {
		return 'post_date';
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		// TODO: TO BE IMPLEMENTED
		$actions = [
//			'bulk-delete' => 'Delete'
		];

		return $actions;
	}

	/**
	 * Handles data query and filter, sorting, and pagination.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		// TODO: TO BE IMPLEMENTED
//		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'submissions_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_submissions( $per_page, $current_page );
	}

	/**
	 * @return void
	 */
	public function process_bulk_action(): void {

		// Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) );

			if ( ! wp_verify_nonce( $nonce, 'sp_delete_response' ) ) {
				die( '.' );
			} else {
//				self::delete_response( absint( $_GET['response'] ) );

				wp_redirect( esc_url( add_query_arg() ) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] === 'bulk-delete' )
		     || ( isset( $_POST[ 'action2' ] ) && $_POST[ 'action2' ] === 'bulk-delete' )
		) {

			// TODO: To be implemented
//			$delete_ids = esc_sql( sanitize_text_field( wp_unslash( $_POST['bulk-delete'] ) ) );

			// loop over the array of record IDs and delete them
//			foreach ( $delete_ids as $id ) {
//				self::delete_response( $id );
//			}

			wp_redirect( esc_url( add_query_arg() ) );
			exit;
		}
	}
}