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
	 * @return mixed
	 */
	public static function get_submissions( $per_page = 20, $page_number = 1 ) {

		global $wpdb;

		$sql = "SELECT {$wpdb->posts}.ID,
            {$wpdb->posts}.post_parent as post_parent,
            {$wpdb->posts}.post_title as signature,
            {$wpdb->posts}.post_content as summary,
            {$wpdb->posts}.post_excerpt as credits,
            {$wpdb->posts}.post_date,
            (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key = '" . WPSEOAI::META_KEY_STATE . "') AS state,
            (SELECT pp.post_title FROM {$wpdb->posts} AS pp WHERE pp.ID = {$wpdb->posts}.post_parent) AS title,
            (SELECT pp.post_type FROM {$wpdb->posts} AS pp WHERE pp.ID = {$wpdb->posts}.post_parent) AS post_type
            FROM {$wpdb->posts}
            WHERE {$wpdb->posts}.post_type = '" . WPSEOAI::POST_TYPE_RESPONSE . "'";

		$args = [];

		// Search filter: title or content contains
		if ( ! empty( $_POST['s'] ) ) {
			$s   = esc_sql( sanitize_text_field( wp_unslash( $_POST['s'] ) ) );
			$sql .= " AND ( {$wpdb->posts}.post_title LIKE '%%%s%%' OR {$wpdb->posts}.post_content LIKE '%%%s%%' )";

			// The value is used twice, hence required twice
			$args[] = $s;
			$args[] = $s;
		}

		// Ordering: By column, and direction
		if ( ! empty( $_GET['orderby'] ) ) {
			$orderby = esc_sql( sanitize_text_field( $_GET['orderby'] ) );
			$order   = esc_sql( sanitize_text_field( $_GET['order'] ) );

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

		$args[] = $per_page;
		$args[] = ( $page_number - 1 ) * $per_page;

		// Prepare the query
		$query = call_user_func_array(
			[
				$wpdb,
				'prepare'
			],
			array_merge(
				(array) $sql,
				$args
			)
		);

		// Execute the query
		$result = $wpdb->get_results( $query, 'ARRAY_A' );

		return $result;
	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = '" . WPSEOAI::POST_TYPE_RESPONSE . "'";

		// Prepare the query
		$query = call_user_func_array(
			[
				$wpdb,
				'prepare'
			],
			array_merge(
				(array) $sql
			)
		);

		return $wpdb->get_var( $query );
	}

	/** Text displayed when no response data is available */
	public function no_items() {
		esc_html_e( 'No submissions have been made.', 'ai-seo-wp' );
	}

	/**
	 * Method for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	function column_title( $item ) {

		$retrieve_nonce = wp_create_nonce( 'retrieve' );

		$id = absint( $item['ID'] );

		$audit_url = wp_nonce_url( admin_url( 'admin.php?page=wpseoai_dashboard&action=audit&post_id=' . $id ), 'audit' );

		$title = '<strong><a href="' . esc_attr( sanitize_text_field( $audit_url ) ) . '">' . esc_html( $item['title'] ) . '</a></strong>';

		$actions = [];

		$state               = get_post_meta( $id, WPSEOAI::META_KEY_JSON, true );
		$actions['retrieve'] = sprintf(
			'<a href="?page=wpseoai_dashboard&action=%s&post_id=%d&_wpnonce=%s">%s</a>',
			'retrieve',
			$id,
			$retrieve_nonce,
			__( 'Retrieve', 'ai-seo-wp' )
		);

		if ( is_array( $state ) && array_key_exists( 'received', $state ) ) {
			$actions['revision'] = sprintf(
				'<a href="revision.php?revision=%d">%s</a>',
				absint( $state['received'][0]['post']['revision_id'] ),
				__( 'Revision', 'ai-seo-wp' )
			);
		} else {
			$actions['revision'] = '<span class="disabled">Revision</span>';
		}

		$actions['audit'] = sprintf(
			'<a href="?page=wpseoai_dashboard&action=%s&post_id=%d">%s</a>',
			'audit',
			$id,
			__( 'Audit', 'ai-seo-wp' )
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'post_parent':
				$url = admin_url( sprintf( 'post.php?post=%d&action=%s', $item['post_parent'], 'edit' ) );

				return '<a href="' . esc_attr( sanitize_text_field( $url ) ) . '">' . esc_html( sanitize_text_field( $item['post_parent'] ) ) . '</a>';
			case 'state':
				return $item['state'] === '1' ? 'Complete' : 'Pending';
			case 'credits':
				return ! empty( $item[ $column_name ] ) ? esc_html( sanitize_text_field( $item[ $column_name ] ) ) : '&ndash;';
			case 'signature':
				return esc_html( $item[ $column_name ] );
			case 'post_type':
				$pto = get_post_type_object( $item[ $column_name ] );

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
	function column_cb( $item ) {
		// TODO: TO BE IMPLEMENTED
		return '';
//		return sprintf(
//			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']
//		);
	}

	/**
	 * Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
//			'cb'          => '<input type="checkbox" />',
			'title'       => __( 'Title', 'ai-seo-wp' ),
			'post_type'   => __( 'Type', 'ai-seo-wp' ),
			'state'       => __( 'Status', 'ai-seo-wp' ),
			'post_parent' => __( 'Parent ID', 'ai-seo-wp' ),
			'credits'     => __( 'Credits', 'ai-seo-wp' ),
			'signature'   => __( 'Signature', 'ai-seo-wp' ),
			'post_date'   => __( 'Date', 'ai-seo-wp' )
		];

		return $columns;
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'title'       => array( 'title', 'asc' ),
			'post_parent' => array( 'post_parent', 'desc' ),
			'post_type'   => array( 'post_type', 'asc' ),
			'state'       => array( 'state', 'asc' ),
			'credits'     => array( 'credits', 'desc' ),
			'signature'   => array( 'signature', 'asc' ),
			'post_date'   => array( 'post_date', 'desc' )
		);

		return $sortable_columns;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @return string Name of the default primary column, in this case, an empty string.
	 * @since 4.3.0
	 *
	 */
	protected function get_default_primary_column_name() {
		return 'post_date';
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
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
	public function process_bulk_action() {

		// Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

			if ( ! wp_verify_nonce( $nonce, 'sp_delete_response' ) ) {
				die( '.' );
			} else {
//				self::delete_response( absint( $_GET['response'] ) );

				wp_redirect( esc_url( add_query_arg() ) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
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