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
		global $wpdb;

		if ( !current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html( __( 'Your user account is not allowed to edit posts.', 'ai-seo-wp' ) ) );
		}

		$sql = "SELECT {$wpdb->posts}.ID,
            {$wpdb->posts}.post_parent as post_parent,
            {$wpdb->posts}.post_title as signature,
            {$wpdb->posts}.post_content as summary,
            {$wpdb->posts}.post_excerpt as credits,
            {$wpdb->posts}.post_date,
            (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key = %s) AS state,
            (SELECT pp.post_title FROM {$wpdb->posts} AS pp WHERE pp.ID = {$wpdb->posts}.post_parent) AS title,
            (SELECT pp.post_type FROM {$wpdb->posts} AS pp WHERE pp.ID = {$wpdb->posts}.post_parent) AS post_type
            FROM {$wpdb->posts}
            WHERE {$wpdb->posts}.post_type = %s";

		$args = [
			WPSEOAI::META_KEY_STATE,
			WPSEOAI::POST_TYPE_RESPONSE
		];

		// Search filter: title or content contains
		if ( ! empty( $_POST[ 's' ] ) ) {
			check_admin_referer( 'wpseoai_dashboard', '_wpnonce_wpseoai' );

			$sql .= " AND ( {$wpdb->posts}.post_title LIKE '%%%s%%' OR {$wpdb->posts}.post_content LIKE '%%%s%%' )";

			// The value is used twice, hence required twice
			$s      = esc_sql( sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 's', FILTER_SANITIZE_STRING ) ) ) );
			$args[] = $s;
			$args[] = $s;
		}

		// Ordering: By column, and direction
		if ( ! empty( $_GET[ 'orderby' ] ) ) {
			$orderby = esc_sql( sanitize_text_field( $_GET[ 'orderby' ] ) );
			$order   = esc_sql( sanitize_text_field( $_GET[ 'order' ] ) );

			switch ( $order ) {
				case 'desc' :
					$order = 'DESC';
					break;
				case 'asc' :
				default:
					$order = 'ASC';
					break;
			}

			$sql .= " ORDER BY " . sanitize_sql_orderby( "{$orderby} {$order}" );
		}

		// Result limits for paging
		$sql .= " LIMIT %d OFFSET %d";

		$args[] = intval( $per_page );
		$args[] = intval( ( $page_number - 1 ) * $per_page );

		// Prepare the query
		$query = $wpdb->prepare( $sql, $args );

		// Execute the query
		$result = $wpdb->get_results( $query, 'ARRAY_A' );

		return $result;
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
			esc_html( __( 'Retrieve', 'ai-seo-wp' ) )
		);

		if ( is_array( $state ) && array_key_exists( 'received', $state ) ) {
			$actions[ 'revision' ] = sprintf(
				'<a href="revision.php?revision=%d">%s</a>',
				absint( $state[ 'received' ][ 0 ][ 'post' ][ 'revision_id' ] ),
				esc_html( __( 'Revision', 'ai-seo-wp' ) )
			);
		} else {
			$actions[ 'revision' ] = '<span class="disabled">Revision</span>';
		}

		$actions[ 'audit' ] = sprintf(
			'<a href="?page=wpseoai_dashboard&action=%s&post_id=%d&_wpnonce=%s">%s</a>',
			'audit',
			$id,
			esc_attr( $audit_nonce ),
			esc_html( __( 'Audit', 'ai-seo-wp' ) )
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
					return esc_html( __( 'Unknown', 'ai-seo-wp' ) );
				}

				return esc_html( sanitize_text_field( $pto->labels->singular_name ?? $pto->label ) );
//				echo $pt->labels->name;
			case 'post_date':
				return esc_html( date( 'jS F, h:i:s a', strtotime( esc_attr( sanitize_text_field( $item[ $column_name ] ) ) ) ) );
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
			'title'       => esc_html( __( 'Title', 'ai-seo-wp' ) ),
			'post_type'   => esc_html( __( 'Type', 'ai-seo-wp' ) ),
			'state'       => esc_html( __( 'Status', 'ai-seo-wp' ) ),
			'post_parent' => esc_html( __( 'Parent ID', 'ai-seo-wp' ) ),
			'credits'     => esc_html( __( 'Credits', 'ai-seo-wp' ) ),
			'signature'   => esc_html( __( 'Signature', 'ai-seo-wp' ) ),
			'post_date'   => esc_html( __( 'Date', 'ai-seo-wp' ) )
		];
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
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