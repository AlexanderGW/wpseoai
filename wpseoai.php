<?php

/*
 * WPSEO.AI
 *
 * @package           WPSEOAI
 * @author            WPSEO.AI Ltd
 *
 * @wordpress-plugin
 * Plugin Name:       WPSEO.AI
 * Plugin URI:        https://wpseo.ai/
 * Description:       Pay-as-you-go artificial intelligence (AI); Search engine optimisations (SEO), proofreading, content translation, auditing, and more in development. Our service is currently in beta.
 * Version:           0.0.1
 * Author:            WPSEO.AI Ltd
 * Text Domain:       wpseoai
 * Requires at least: 5.2
 * Requires PHP:      7.1
 * Domain Path:       /language
 * License:           MIT
 */

/**
 * NOTICE
 * ---------------------------------------
 * If you're having problems with this plugin, after configuring your Subscription ID,
 * and Secret, please contact us, using one of the forms on our website. https://wpseo.ai/subscription-status.html
 *
 * Alternatively, please check our FAQ at: https://wpseo.ai/faq.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require `WPSEOAI_List_Table` class, for displaying submissions on the WPSEO.AI dashboard
if ( ! class_exists( 'WPSEOAI_List_Table' ) ) {
	require_once( __DIR__ . '/includes/class-wpseoai-wp-list-table.php' );
}

if ( ! class_exists( 'WPSEOAI' ) ) {

	/**
	 * The `WPSEOAI` class, for plugin functionality and integration
	 */
	class WPSEOAI {

		/**
		 * Constants for identifiers used within the WordPress ecosystem
		 */
		public const POST_TYPE_RESPONSE = 'wpseoai_response';

		public const META_KEY_SUMMARY = '_wpseoai_summary';
		public const META_KEY_SIGNATURE = '_wpseoai_signature';
		public const META_KEY_STATE = '_wpseoai_state';
		public const META_KEY_JSON = '_wpseoai_json';

		/**
		 * Regular expression patterns, within the WPSEO.AI ecosystem
		 */
		public const PATTERN_SIGNATURE_ID = '/^[a-z0-9]{64}$/';

		public const PATTERN_SUBSCRIPTION_ID = '/^[A-Z0-9]{20}$/';

		public const PATTERN_SECRET = '/^[a-z0-9]{32}$/';

		/**
		 * Holds instance of `WPSEOAI` class, fetched with `self::get_instance()`
		 *
		 * @var self
		 */
		private static $instance;

		/**
		 * Holds instance of `WPSEOAI_List_Table` class
		 *
		 * @var WPSEOAI_List_Table
		 */
		public $responses_obj;

		/**
		 * Prevent cloning
		 *
		 * @return void
		 */
		private function __clone() {
			// Empty
		}

		/**
		 * Establish all actions and filters for the plugin
		 */
		private function __construct() {
			// Setup WPSEO.AI post type
			add_action( 'init', [ $this, 'register_custom_post_type' ] );

			// Setup REST API endpoints
			add_action( 'rest_api_init', [ $this, 'register_ingest_endpoint' ] );

			add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_head', [ $this, 'theme_admin_css' ] );

			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

			add_action( 'plugins_loaded', function () {
				WPSEOAI::get_instance();
			} );

			/**
			 * Filters
			 */
			add_filter( 'set-screen-option', [
				__CLASS__,
				'set_screen'
			], 10, 3 );

			add_filter( 'post_row_actions', [
				__CLASS__,
				'post_row_actions'
			], 0, 2 );

			add_filter( 'page_row_actions', [
				__CLASS__,
				'page_row_actions'
			], 0, 2 );
		}

		/**
		 * Singleton instance
		 *
		 * @return WPSEOAI
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * @param $status
		 * @param $option
		 * @param $value
		 *
		 * @return mixed
		 */
		public static function set_screen( $status, $option, $value ) {
			return $value;
		}

		/**
		 * post_row_actions filter
		 *
		 * @param array $actions
		 * @param object $post
		 *
		 * @return array
		 */
		public static function post_row_actions(
			$actions,
			$post
		) {
			if ( current_user_can( 'edit_posts' ) ) {
				$actions = array_merge( $actions, array(
					'wpseoai_optimize_post' => sprintf( '<a href="%s">' . esc_html( __( 'Finesse', 'wpseoai' ) ) . '</a>', wp_nonce_url( sprintf( 'admin.php?page=wpseoai_dashboard&action=optimize&post_id=%d', $post->ID ), 'optimize' ) )
				) );
			}

			return $actions;
		}

		/**
		 * page_row_actions filter
		 *
		 * @param array $actions
		 * @param object $post
		 *
		 * @return array
		 */
		public static function page_row_actions(
			$actions,
			$post
		) {
			if ( current_user_can( 'edit_pages' ) ) {
				$actions = array_merge( $actions, array(
					'wpseoai_optimize_post' => sprintf( '<a href="%s">' . esc_html( __( 'Finesse', 'wpseoai' ) ) . '</a>', wp_nonce_url( sprintf( 'admin.php?page=wpseoai_dashboard&action=optimize&post_id=%d', $post->ID ), 'optimize' ) )
				) );
			}

			return $actions;
		}

		/**
		 * @return void
		 */
		public function enqueue_block_editor_assets() {
			wp_enqueue_script(
				'wpseoai-gutenberg',
				plugins_url( 'dist/wpseoai_gutenberg.js', __FILE__ ),
				array( 'wp-plugins', 'wp-edit-post' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'dist/wpseoai_gutenberg.js' ),
				true
			);
		}

		/**
		 * @param $name
		 * @param $data
		 *
		 * @return bool|null
		 */
		private static function log(
			$name,
			$data
		) {
			if ( get_option( 'wpseoai_log', get_option( 'wpseoai_debug', 'false' ) ) === 'false' ) {
				return null;
			}
			$log_directory = getcwd() . '/log/' . date( 'Y-m-d' );
			if ( ! is_dir( $log_directory ) && ! mkdir( $log_directory ) ) {
				return false;
			}
			$timestamp = time();
			$data      = json_encode( $data, JSON_PRETTY_PRINT );
			if ( ! file_put_contents(
                    "{$log_directory}/wpseoai-{$name}.json",
                    "{$timestamp}: {$data}\n",
                    FILE_APPEND
            ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Create custom post for responses, for auditing purposes
		 *
		 * @return void
		 */
		public function register_custom_post_type() {
			register_post_type( self::POST_TYPE_RESPONSE, [
				'label'               => esc_html( __( 'WPSEO.AI', 'wpseoai' ) ),
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_nav_menus'   => false,
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-share-alt',
			] );
		}

		/**
		 * @return void
		 */
		public function register_ingest_endpoint() {
			register_rest_route( 'wpseoai/v1', '/ingest', [
				'methods'             => 'POST',
				'callback'            => [ $this, 'ingest_endpoint_callback' ],
				'permission_callback' => [ $this, 'ingest_endpoint_permission_callback' ],
			] );

			register_rest_route( 'wpseoai/v1', '/optimize', [
				'methods'             => 'GET',
				'callback'            => [ $this, 'optimize_endpoint_callback' ],
				'permission_callback' => [ $this, 'optimize_endpoint_permission_callback' ],
			] );

			register_rest_route( 'wpseoai/v1', '/retrieve', [
				'methods'             => 'GET',
				'callback'            => [ $this, 'retrieve_endpoint_callback' ],
				'permission_callback' => [ $this, 'retrieve_endpoint_permission_callback' ],
			] );

			register_rest_route( 'wpseoai/v1', '/context', [
				'methods'             => 'GET',
				'callback'            => [ $this, 'context_endpoint_callback' ],
				'permission_callback' => [ $this, 'context_endpoint_permission_callback' ],
			] );

			register_rest_route( 'wpseoai/v1', '/audit', [
				'methods'             => 'GET',
				'callback'            => [ $this, 'audit_endpoint_callback' ],
				'permission_callback' => [ $this, 'audit_endpoint_permission_callback' ],
			] );
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return WP_REST_Response
		 */
		public function ingest_endpoint_callback(
			WP_REST_Request $request
		): WP_REST_Response {
			$subscription_id = self::_get_subscription_id();
			$secret          = self::_get_subscription_secret();

			// Check we are configured with a subscription
			if ( empty( $subscription_id ) || empty( $secret ) ) {
				return new WP_REST_Response( [
					'message' => 'Missing configuration',
					'code'    => 1
				], 401 );
			}

			$request_subscription_id = $request->get_header( 'x_subscription_id' );
			$request_signature       = $request->get_header( 'x_signature' );
			$request_timestamp       = $request->get_header( 'x_timestamp' );

			// Check that we have required headers
			if ( ! $request_subscription_id || ! $request_signature || ! $request_timestamp ) {
				return new WP_REST_Response( [
					'message' => 'Missing authentication headers',
					'code'    => 2
				], 401 );
			}

			// Check the provided subscription ID is matched
			if ( $request_subscription_id !== $subscription_id ) {
				return new WP_REST_Response( [
					'message' => 'Invalid subscription ID',
					'code'    => 3
				], 401 );
			}

			// Create HMAC, and verify signature
			$method   = $request->get_method();
			$endpoint = '/wp-json';
			$endpoint .= $request->get_route();
			$payload  = $request->get_body();
			$content  = $method . ' ' . $endpoint . $payload . $request_timestamp;
			$hmac     = hash_hmac( "sha256", $content, $secret );
			if ( $hmac !== $request_signature ) {
				return new WP_REST_Response( [
					'message' => 'Invalid signature',
					'code'    => 4
				], 401 );
			}

			// Check the received data structure
			$json = $request->get_json_params();
			if ( ! $json || ! array_key_exists( 'data', $json ) ) {
				return new WP_REST_Response( [
					'message' => 'No data provided',
					'code'    => 5
				], 400 );
			}

			// Check payload version
			if ( ! array_key_exists( 'version', $json ) || $json['version'] !== '1' ) {
				return new WP_REST_Response( [
					'message' => 'Invalid payload version',
					'code'    => 6
				], 400 );
			}

			// Decode the received Base64 data
			$data_string = base64_decode( $json['data'] );
			if ( ! $data_string ) {
				return new WP_REST_Response( [
					'message' => 'Invalid data provided',
					'code'    => 7
				], 400 );
			}

			// Parse the data string
			$data = json_decode( $data_string, true );
			if ( ! $data ) {
				return new WP_REST_Response( [
					'message' => 'Invalid data provided',
					'code'    => 8
				], 400 );
			}

			self::log( "ingest-{$request_signature}", $data );

			[ $post_id, $revision_id ] = self::_save_response( $data );
			if ( ! $post_id ) {
				return new WP_REST_Response( [
					'message' => 'Failed to store response data',
					'code'    => 9
				], 400 );
			}

			// Add post ID for audit record
			$data['post']['ID']          = $post_id;
			$data['post']['revision_id'] = $revision_id;

			// TODO: Handling of failed audit record creation
			$audit_post_id = self::_save_audit( $data );
			if ( ! $audit_post_id ) {
				self::log( "audit-${$post_id}", $audit_post_id );
			}

			return new WP_REST_Response( [
				'code'    => 0,
				'auditId' => $audit_post_id,
				'postId'  => $post_id
			], 200 );
		}

		/**
		 * See `WPSEOAI::ingest_endpoint_callback()`
		 *
		 * @param WP_REST_Request $request
		 *
		 * @return bool
		 */
		public function ingest_endpoint_permission_callback(
			WP_REST_Request $request
		): bool {
			return true;
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return WP_REST_Response
		 */
		public function retrieve_endpoint_callback(
			WP_REST_Request $request
		): WP_REST_Response {
			try {
				$params = $request->get_query_params();

				if ( ! array_key_exists( 'post', $params ) ) {
					throw new \Exception( 'Invalid payload', 1 );
				}

				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'Your user account is not allowed', 2 );
				}

				$post_id = intval( $params['post'] );

				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					throw new \Exception( 'Invalid post', 3 );
				}

				$signature = get_post_meta( $post_id, self::META_KEY_SIGNATURE, true );
				if ( empty( $signature ) ) {
					throw new \Exception( 'Invalid post type', 4 );
				}

				$result = self::retrieve( $signature );

				if ( ! is_array( $result ) ) {
					throw new \Exception( 'WPSEO.AI retrieval request failed to response, please try later.', 5 );
				}

				return new WP_REST_Response( [
					'message' => $result['code'] === 204 ? 'Content not yet processed, please try later' : 'Success',
					'code'    => $result['code'],
					'auditId' => $result['audit_post_id'] // TODO: undefined?
				], 200 );
			} catch ( \Exception $e ) {
				return new WP_REST_Response( [
					'message' => $e->getMessage(),
					'code'    => $e->getCode()
				], 400 );
			}
		}


		/**
		 * @param WP_REST_Request $request
		 *
		 * @return bool
		 */
		public function retrieve_endpoint_permission_callback(
			WP_REST_Request $request
		): bool {
			return true;
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return WP_REST_Response
		 */
		public function optimize_endpoint_callback(
			WP_REST_Request $request
		): WP_REST_Response {
			try {
				$params = $request->get_query_params();

				if ( ! array_key_exists( 'post', $params ) ) {
					throw new \Exception( 'Invalid payload', 1 );
				}

				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'Your user account is not allowed', 2 );
				}

				$post_id = intval( $params['post'] );

				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					throw new \Exception( 'Invalid post', 3 );
				}

				// WPML: Establish locale target
				$target_locale = filter_input( INPUT_GET, 'locale', FILTER_SANITIZE_STRING );
				if ( $target_locale && function_exists( 'wpml_get_setting' ) ) {
					global $sitepress;
					$active_languages = $sitepress->get_active_languages();
					if ( ! array_key_exists( $target_locale, $active_languages ) ) {
						throw new \Exception( 'Invalid locale', 4 );
					}
				}

				$result = self::submit_post( $post_id, $target_locale );

				if ( ! is_array( $result ) ) {
					throw new \Exception( 'Invalid submission response, please try later.', 5 );
				}

				if ( $result['code'] <> 200 ) {
					throw new \Exception( $result['message'], $result['code'] );
				}

				return new WP_REST_Response( [
					'message' => 'Success',
					'code'    => $result['code'],
					'auditId' => $result['audit_post_id']
				], 200 );
			} catch ( \Exception $e ) {
				return new WP_REST_Response( [
					'message' => $e->getMessage(),
					'code'    => $e->getCode()
				], 400 );
			}
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return bool
		 */
		public function optimize_endpoint_permission_callback(
			WP_REST_Request $request
		): bool {
			return true;
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return WP_REST_Response
		 */
		public function context_endpoint_callback(
			WP_REST_Request $request
		): WP_REST_Response {
			try {
				$params = $request->get_query_params();

				if ( ! array_key_exists( 'post', $params ) ) {
					throw new \Exception( 'Invalid payload', 1 );
				}

				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'Your user account is not allowed', 2 );
				}

				$post_id = absint( $params['post'] );

				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					throw new \Exception( 'Invalid post', 3 );
				}

				// Get submission summary
				$summary = get_post_meta( $post_id, self::META_KEY_SUMMARY, true );

				// WPML: Establish locales
				if ( function_exists( 'wpml_get_setting' ) ) {
					global $sitepress;
					$language_details = $sitepress->get_element_language_details(
						$post_id,
						"post_{$post->post_type}"
					);
					$default_locale   = $language_details->language_code;
					$locales          = $sitepress->get_active_languages();
				} else {
					$default_locale = get_locale();
					$locales        = null;
				}

				return new WP_REST_Response( [
					'defaultLocale' => $default_locale,
					'locales'       => $locales,
					'summary'       => $summary
				], 200 );
			} catch ( \Exception $e ) {
				return new WP_REST_Response( [
					'message' => $e->getMessage(),
					'code'    => $e->getCode()
				], 400 );
			}
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return bool
		 */
		public function context_endpoint_permission_callback(
			WP_REST_Request $request
		): bool {
			return true;
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return WP_REST_Response
		 */
		public function audit_endpoint_callback(
			WP_REST_Request $request
		): WP_REST_Response {
			try {
				$params = $request->get_query_params();

				if ( ! array_key_exists( 'post', $params ) ) {
					throw new \Exception( 'Invalid payload', 1 );
				}

				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new \Exception( 'Your user account is not allowed', 2 );
				}

				$post_id = absint( $params['post'] );

				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					throw new \Exception( 'Invalid post', 3 );
				}

				$state = get_post_meta( $post_id, self::META_KEY_STATE, true );

				return new WP_REST_Response( [
					'state' => $state
				], 200 );
			} catch ( \Exception $e ) {
				return new WP_REST_Response( [
					'message' => $e->getMessage(),
					'code'    => $e->getCode()
				], 400 );
			}
		}

		/**
		 * @param WP_REST_Request $request
		 *
		 * @return bool
		 */
		public function audit_endpoint_permission_callback(
			WP_REST_Request $request
		): bool {
			return true;
		}

		/**
		 * @return string|void
		 */
		public function theme_admin_css() {
			// TODO: Cleanup logic
			if (
				( array_key_exists( 'page', $_GET ) && $_GET['page'] !== 'wpseoai_dashboard' )
				|| ! array_key_exists( 'action', $_GET )
				|| (
					$_GET['action'] !== 'audit'
					&& $_GET['action'] !== 'optimize'
					&& $_GET['action'] !== 'retrieve'
					&& $_GET['action'] !== 'edit'
				)
			) {
				return '';
			}

			$url_css = esc_attr( plugins_url( 'wpseoai.css', 'wpseoai/dist/wpseoai.css' ) );
			echo '<link rel="stylesheet" href="' . $url_css . '"></script>';

			$url_js = esc_attr( plugins_url( 'main.js', 'wpseoai/dist/main.js' ) );
			echo '<script type="text/javascript" src="' . $url_js . '" defer></script>';
		}

		/**
		 * Screen options
		 *
		 * @return void
		 */
		public function screen_option() {

			// TODO: Can we refactor this?
			if ( ! isset( $_GET['orderby'] ) ) {
				$_GET['orderby'] = 'post_date';
			}
			if ( ! isset( $_GET['order'] ) ) {
				$_GET['order'] = 'DESC';
			}

			$option = 'per_page';
			$args   = [
				'label'   => esc_html( __( 'Number of items per page:' ) ),
				'default' => 20,
				'option'  => 'submissions_per_page'
			];

			add_screen_option( $option, $args );

			$this->responses_obj = new WPSEOAI_List_Table();
		}

		/**
		 * @return void
		 */
		public function add_settings_page() {
			add_menu_page(
				'WPSEO.AI Responses',
				'WPSEO.AI',
				'manage_options',
				'wpseoai_dashboard',
				[ $this, 'manage_responses_callback' ],
				'dashicons-share-alt',
				null
			);

			$hook = add_submenu_page(
				'wpseoai_dashboard',
				esc_html( __( 'WPSEO.AI', 'wpseoai' ) ),
				esc_html( __( 'Dashboard', 'wpseoai' ) ),
				'manage_options',
				'wpseoai_dashboard',
				[ $this, 'manage_responses_callback' ]
			);

			add_action( "load-$hook", [ $this, 'screen_option' ] );

			add_submenu_page(
				'wpseoai_dashboard',
				esc_html( __( 'WPSEO.AI', 'wpseoai' ) ),
				esc_html( __( 'Settings', 'wpseoai' ) ),
				'manage_options',
				'wpseoai_settings',
				[ $this, 'settings_page' ]
			);
		}

		/**
		 * @param $array
		 *
		 * @return string
		 */
		private static function _generate_html_array_key_value( $array ) {
			$html = '';

			foreach ( $array as $key => $value ) {
				$html .= "<hr />";
				$html .= "<h4>" . esc_html( $key ) . "</h4>";
				$html .= '<p>';
				if ( is_bool( $value ) ) {
					$html .= esc_html( $value ? 'TRUE' : 'FALSE' );
				} elseif ( is_array( $value ) ) {
					foreach ( $value as $i => $field ) {
						foreach ( $field as $j => $k ) {
							$html .= "<hr />";
							$html .= "<h4>[" . esc_html( $i ) . "]: " . esc_html( $j ) . "</h4>";
							$html .= '<p>';
							if ( is_string( $k ) || is_int( $value ) ) {
								$html .= esc_html( $k );
							} elseif ( is_bool( $k ) ) {
								$html .= esc_html( $k ? 'TRUE' : 'FALSE' );
							}
							$html .= '</p>';
						}
					}
				} else {
					$html .= esc_html( $value );
				}
				$html .= '</p>';
			}

//			foreach ( $array as $key => $value ) : ?>
            <!--				<hr />-->
            <!--                <h4>--><?php //esc_attr_e( $key ) ?><!--</h4>-->
            <!--                <p>-->
            <!--                --><?php //if ( is_bool( $value ) ) :
//                    echo esc_html( $value ? 'TRUE' : 'FALSE' );
//                elseif ( is_array( $value ) ) :
//	                foreach ( $value as $i => $field ) :
//		                foreach ( $field as $j => $k ): ?>
            <!--                            <hr />-->
            <!--                            <h4>--><?php //esc_attr_e( "[{$i}]: {$j}" ) ?><!--</h4>-->
            <!--                            <p>-->
            <!--                                --><?php //if ( is_string( $k ) || is_int( $value ) ) :
//				                    echo esc_html( $k );
//                                elseif ( is_bool( $k ) ) :
//	                                echo esc_html( $k ? 'TRUE' : 'FALSE' );
//                                endif; ?>
            <!--                            </p>-->
            <!--		                --><?php //endforeach;
//	                endforeach;
//                else :
//	                $html .= esc_html( $value );
//                endif; ?>
            <!--                </p>-->
            <!--			--><?php //endforeach;

			return $html;
		}

		/**
		 * @return void
		 */
		public function manage_responses_callback() {
			if ( array_key_exists( 'action', $_GET ) ) {
				if ( ! array_key_exists( 'post_id', $_GET ) ) {
					return;
				}

				$post_id  = absint( $_GET['post_id'] );
				$_wpnonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
				$action   = sanitize_text_field( wp_unslash( $_GET['action'] ) );

				switch ( $action ) {

					/**
					 * Action an optimization request to WPSEO.AI
					 */
					case 'optimize':
					{
						if ( ! isset( $_wpnonce ) || ! wp_verify_nonce( $_wpnonce, 'optimize' ) ) {
							return;
						}

						$post = get_post( $post_id );
						if ( ! $post instanceof WP_Post ) {
							return;
						}

						$nonce = wp_create_nonce( 'wp_rest' );

						// WPML: Establish target locale
						if ( function_exists( 'wpml_get_setting' ) ) {
							global $sitepress;
							$active_languages = $sitepress->get_active_languages();
							$locale           = filter_input( INPUT_GET, 'locale', FILTER_SANITIZE_STRING );
							if ( ! $locale ) {
								$language_details = $sitepress->get_element_language_details(
									$post_id,
									"post_{$post->post_type}"
								);

								$locale = $language_details->language_code;
							}

							if ( ! array_key_exists( $locale, $active_languages ) ) {
								return;
							}
						} else {
							$locale = get_locale();
						}

						?>
                        <div class="wrap" id="wpseoai-request" data-type="optimize"
                             data-locale="<?php echo esc_attr( $locale ) ?>"
                             data-post="<?php echo esc_attr( $post_id ) ?>"
                             data-nonce="<?php echo esc_attr( $nonce ) ?>">
                            <h2>Finesse content</h2>
                        </div>
						<?php
						break;
					}

					/**
					 * Retrieve an optimisation request, previously sent to WPSEO.AI
					 */
					case 'retrieve':
					{
						if ( ! isset( $_wpnonce ) || ! wp_verify_nonce( $_wpnonce, 'retrieve' ) ) {
							return;
						}

						// TODO: Escape?
						$nonce = wp_create_nonce( 'wp_rest' );

						?>
                        <div class="wrap" id="wpseoai-request" data-type="retrieve"
                             data-post="<?php echo esc_attr( $post_id ) ?>"
                             data-nonce="<?php echo esc_attr( $nonce ) ?>">
                            <h2>WPSEO.AI Retrieval</h2>
                        </div>
						<?php
						break;
					}

					/**
					 * View current audit state of an optimisation request, previously sent to WPSEO.AI
					 */
					case 'audit':
					{
						$post = get_post( $post_id );
						if ( ! $post ) {
							wp_die( esc_html( __( 'Post not found.', 'wpseoai' ) ) );
						}

						$date_before = get_the_date( 'jS F, h:i:s a', $post );

						$state = get_post_meta( $post_id, self::META_KEY_JSON, true );

						// Exit if we have invalid state information
						if (
							! is_array( $state )
							|| ! array_key_exists( 'sent', $state )
							|| ! array_key_exists( 'post', $state['sent'] )
						) {
							wp_die( esc_html( __( 'Invalid state information for this WPSEO.AI submission.', 'wpseoai' ) ) );
						}

						$response = addslashes( json_encode( $state ) );

//						echo '<script type="text/javascript">';
//						echo "const WPSEOAI_POST_JSON = \"{$response}\";";
//						echo "</script>";
//						echo '<div class="wrap">';
//						echo "<h1>WPSEO.AI Submission</h1>";
//						echo "<h2>{$state['sent']['post']['post_title']}</h2>";
//
//						echo '<div class="card">';
//						echo "<h2 class='title'><label for='submission-toggle'>Submission</label></h2>";
//						echo '<button class="toggle" id="submission-toggle" aria-label="Show card contents" aria-pressed="true">&nbsp;</button>';
//						echo '<div class="show">';
//						echo "<h4>" . esc_html( __( 'Date', 'wpseoai' )) . "</h4>";
//						echo "<p>{$date_before}</p>";
//						echo "<h4>" . esc_html( __( 'Signature', 'wpseoai' )) . "</h4>";
//						echo "<p>{$state['sent']['signature']}</p>";
//						echo "</div></div>";

						?>

                        <script type="text/javascript">
                            const WPSEOAI_POST_JSON = "<?php echo esc_js( $response ) ?>"
                        </script>
                        <div class="wrap">
                            <h1>WPSEO.AI Submission</h1>
                            <h2><?php echo esc_html( $state['sent']['post']['post_title'] ) ?></h2>
                            <div class="card">
                                <h2 class="title"><label for="submission-toggle">Submission</label></h2>
                                <button
                                        class="toggle"
                                        id="submission-toggle"
                                        aria-label="Show card contents"
                                        aria-pressed="true"
                                >&nbsp;
                                </button>
                                <div class="show">
                                    <h4><?php echo esc_html( __( 'Date', 'wpseoai' ) ) ?></h4>
                                    <p><?php echo esc_html( __( $date_before ) ) ?></p>
                                    <h4><?php echo esc_html( __( 'Signature', 'wpseoai' ) ) ?></h4>
                                    <p><?php echo esc_html( $state['sent']['signature'] ) ?></p>
                                </div>
                            </div>

							<?php

							// Process summary data
							if ( array_key_exists( 'received', $state ) && is_array( $state['received'] ) ) {
								$summary = htmlentities( $state['received'][0]['summary'] );

								//							echo '<div class="card">';
								//							echo "<h2 class='title'><label for='changelog-toggle'>Changelog</label></h2>";
								//							echo '<button class="toggle" id="changelog-toggle" aria-label="Show card contents" aria-pressed="false">&nbsp;</button>';
								//							echo "<div>";
								//							echo "<h4>" . esc_html( __( 'Credit used', 'wpseoai' )) . "</h4>";
								//							echo "<p>{$state['received'][0]['creditUsed']}</p>";
								//							echo "<h4>" . esc_html( __( 'Credit remaining', 'wpseoai' )) . "</h4>";
								//							echo "<p>{$state['received'][0]['creditRemaining']}</p>";
								//							echo "<h4>" . esc_html( __( 'Change summary', 'wpseoai' )) . "</h4>";
								//							echo "<p>{$summary}</p>";
								//							echo "</div></div>";

								?>

                                <div class="card">
                                    <h2 class="title"><label for="changelog-toggle">Changelog</label></h2>
                                    <button
                                            class="toggle"
                                            id="changelog-toggle"
                                            aria-label="Show card contents"
                                            aria-pressed="false"
                                    >&nbsp;
                                    </button>
                                    <div>
                                        <h4><?php echo esc_html( __( 'Credit used', 'wpseoai' ) ) ?></h4>
                                        <p><?php echo esc_html( __( $state['received'][0]['creditUsed'] ) ) ?></p>
                                        <h4><?php echo esc_html( __( esc_html( __( 'Credit remaining', 'wpseoai' ) ) ) ) ?></h4>
                                        <p><?php echo esc_html( __( $state['received'][0]['creditRemaining'] ) ) ?></p>
                                        <h4><?php echo esc_html( __( esc_html( __( 'Change summary', 'wpseoai' ) ) ) ) ?></h4>
                                        <p><?php echo esc_html( __( $summary ) ) ?></p>
                                    </div>
                                </div>

								<?php
							}

							// Process sent data
							//						echo '<div class="card">';
							//						echo "<h2 class='title'><label for='sent-toggle'>Sent on {$date_before}</label></h2>";
							//						echo '<button class="toggle" id="sent-toggle" aria-label="Show card contents" aria-pressed="false">&nbsp;</button>';
							//						echo "<div>";

							?>

                            <div class="card">
                                <h2 class="title"><label for="sent-toggle">Sent
                                        on <?php echo esc_html( __( $date_before ) ) ?></label></h2>
                                <button class="toggle" id="sent-toggle" aria-label="Show card contents"
                                        aria-pressed="false">&nbsp;
                                </button>
                                <div>
									<?php if (
										array_key_exists( 'sent', $state )
										&& array_key_exists( 'post', $state['sent'] )
									) {
										echo self::_generate_html_array_key_value( $state['sent']['post'] );
									} ?>
                                </div>
                            </div>

							<?php

							// Processed received responses
							if ( array_key_exists( 'received', $state ) ) :
								$total = count( $state['received'] );
								foreach ( $state['received'] as $i => $received ) :
									$index = $i + 1;
									$date = date( 'jS F, h:i:s a', strtotime( $received['date'] ) );

									$location = '';
									if ( $total > 1 ) {
										$location = "({$index} of {$total})";
									}

									//								echo '<div class="card">';
									//								echo "<h2 class='title'><label for='received{$i}-toggle'>Received {$location} on {$date}</label></h2>";
									//								echo '<button class="toggle" id="received' . $i . '-toggle" aria-label="Show card contents" aria-pressed="false">&nbsp;</button>';
									//								echo "<div>";
									?>

                                    <div class="card">
                                        <h2 class="title">
                                            <label for="<?php echo esc_html( __( "received-{$i}-toggle" ) ) ?>">
                                                Received <?php echo esc_html( __( $location ) ) ?>
                                                on <?php echo esc_html( __( $date ) ) ?>
                                            </label>
                                        </h2>
                                        <button
                                                class="toggle"
                                                id="<?php echo esc_html( __( "received-{$i}-toggle" ) ) ?>"
                                                aria-label="Show card contents"
                                                aria-pressed="false"
                                        >&nbsp;
                                        </button>
                                        <div>

											<?php
											if ( array_key_exists( 'post', $received ) ) :
												$revision_url = admin_url( "revision.php?revision=" . $received['post']['revision_id'] );
												//                                echo '<p><a href="' . $revision_url . '">' . esc_html( __( 'View the post revision for this data', 'wpseoai' )) . '</a></p>';
												?>

                                                <p>
                                                    <a href="<?php echo esc_attr( __( $revision_url ) ) ?>"><?php echo esc_html( __( 'View the post revision for this data', 'wpseoai' ) ) ?></a>
                                                </p>

												<?php
												echo self::_generate_html_array_key_value( $received['post'] );
											endif;
											?>
                                        </div>
                                    </div>
								<?php
								endforeach;
							endif;
							?>
                        </div>
						<?php

						break;
					}
					default :
					{
						echo '';
						// TODO: wp_die();  back to dash?
					}
				}
			} else {
//				$debug = esc_attr( sanitize_text_field( get_option( 'wpseoai_debug', 'false' ) ) );
				$subscription_id = self::_get_subscription_id();
				if ( empty( $subscription_id ) ) {
					wp_die( 'Subscription ID and Secret is missing. Please add these details on the settings page.' );
				}
				$credit = (array) get_option( 'wpseoai_credit', [] );

				?>
                <div class="wrap">
                    <h1>WPSEO.AI Dashboard</h1>
                    <div class="card">
                        <h2>Credit balance</h2>
                        <p>
							<?php
							echo isset( $credit[ $subscription_id ] )
								? esc_html( $credit[ $subscription_id ] )
								: '<i>Unknown</i>'
							?>
                        </p>
                    </div>
                    <div id="post-body" class="metabox-holder">
                        <h1>Submission audit records</h1>
                        <div id="post-body-content">
                            <div class="meta-box-sortables ui-sortable">
                                <form method="post">
									<?php $this->responses_obj->prepare_items();
									$this->responses_obj->search_box( 'search', 'search_id' );
									$this->responses_obj->display(); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
		}

		/**
		 * @return void
		 */
		public function register_settings(): void {
			register_setting( 'wpseoai-settings-group', 'wpseoai_debug' );
			register_setting( 'wpseoai-settings-group', 'wpseoai_host' );
			register_setting( 'wpseoai-settings-group', 'wpseoai_log' );
			register_setting( 'wpseoai-settings-group', 'wpseoai_subscription_id' );
			register_setting( 'wpseoai-settings-group', 'wpseoai_secret' );
		}

		/**
		 * @return void
		 */
		public function settings_page() {
			$wpseoai_subscription_id = self::_get_subscription_id();
			$wpseoai_secret          = self::_get_subscription_secret();

			?>
            <div class="wrap">
                <h1>WPSEO.AI Settings</h1>
                <p>If you don't yet have a <strong>Subscription ID</strong> and <strong>Secret</strong>, you will first
                    need to <a target="_new"
                               href="https://wpseo.ai/subscription-top-up-credits.html#<?php echo $wpseoai_subscription_id ?>">purchase
                        some credits</a> on our website.<br/>There are <strong>no</strong> monthly commitments; your
                    credits will never expire.</p>
                <p>For more information on how credits work, please visit our <a target="_new"
                                                                                 href="https://wpseo.ai/faq.html#<?php echo $wpseoai_subscription_id ?>">frequently
                        asked questions</a>.<br/>
                    Including our <a target="_new"
                                     href="https://wpseo.ai/terms-of-service.html#<?php echo $wpseoai_subscription_id ?>">terms
                        of
                        service</a>, <a target="_new"
                                        href="https://wpseo.ai/privacy-policy.html#<?php echo $wpseoai_subscription_id ?>">privacy
                        policy</a>,
                    and <a target="_new"
                           href="https://wpseo.ai/legal-information.html#<?php echo $wpseoai_subscription_id ?>">legal
                        information</a>.</p>

                <form method="post" action="options.php">
					<?php settings_fields( 'wpseoai-settings-group' ); ?>
					<?php do_settings_sections( 'wpseoai-settings-group' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Subscription ID</th>
                            <td>
                                <input type="text" size="45" name="wpseoai_subscription_id"
                                       value="<?php echo $wpseoai_subscription_id; ?>"/>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Secret</th>
                            <td>
                                <textarea rows="2" cols="45"
                                          name="wpseoai_secret"><?php echo $wpseoai_secret; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Retrieval mode</th>
                            <td>
                                <fieldset>
                                    <input disabled id="wpseoai-mode-0" class="disabled" type="radio"
                                           name="wpseoai_mode"
                                           value="0" <?php checked( 0, (int) get_option( 'wpseoai_mode', 0 ) ); ?> />
                                    <label for="wpseoai-mode-0"><?php esc_html_e( 'Create a new draft on original post' ); ?></label><br/>
                                    <input disabled id="wpseoai-mode-1" class="disabled" type="radio"
                                           name="wpseoai_mode"
                                           value="1" <?php checked( 1, (int) get_option( 'wpseoai_mode', 0 ) ); ?> />
                                    <label for="wpseoai-mode-1"><?php esc_html_e( 'Create a response post for moderation' ); ?></label><br/>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug mode</th>
                            <td>
                                <fieldset>
                                    <input id="wpseoai-debug-false" type="radio"
                                           name="wpseoai_debug"
                                           value="false" <?php checked( 'false', esc_attr( sanitize_text_field( get_option( 'wpseoai_debug', 'false' ) ) ) ); ?> />
                                    <label for="wpseoai-debug-false"><?php esc_html_e( 'Disabled (normal operation)' ); ?></label><br/>
                                    <input id="wpseoai-debug-true" type="radio"
                                           name="wpseoai_debug"
                                           value="true" <?php checked( 'true', esc_attr( sanitize_text_field( get_option( 'wpseoai_debug', 'false' ) ) ) ); ?> />
                                    <label for="wpseoai-debug-true"><?php esc_html_e( 'Enabled (advanced use only)' ); ?></label><br/>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug log</th>
                            <td>
                                <fieldset>
                                    <input id="wpseoai-log-false" type="radio"
                                           name="wpseoai_log"
                                           value="false" <?php checked( 'false', esc_attr( sanitize_text_field( get_option( 'wpseoai_log', 'false' ) ) ) ); ?> />
                                    <label for="wpseoai-log-false"><?php esc_html_e( 'No' ); ?></label><br/>
                                    <input id="wpseoai-log-true" type="radio"
                                           name="wpseoai_log"
                                           value="true" <?php checked( 'true', esc_attr( sanitize_text_field( get_option( 'wpseoai_log', 'false' ) ) ) ); ?> />
                                    <label for="wpseoai-log-true"><?php esc_html_e( 'Yes' ); ?></label><br/>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

					<?php submit_button(); ?>

                </form>
            </div>
			<?php
		}

		/**
		 * Return Subscription ID
		 *
		 * @return string
		 */
		private static function _get_subscription_id(): string {
			$subscription_id = esc_attr( sanitize_text_field( get_option( 'wpseoai_subscription_id', '' ) ) );
			if ( self::validate_subscription_id( $subscription_id ) === false ) {
				$subscription_id = '';
			}

			return $subscription_id;
		}

		/**
		 * Return Subscription secret
		 *
		 * @return string
		 */
		private static function _get_subscription_secret(): string {
			$secret = esc_attr( sanitize_text_field( get_option( 'wpseoai_secret', '' ) ) );
			if ( self::validate_subscription_secret( $secret ) === false ) {
				$secret = '';
			}

			return $secret;
		}

		/**
		 * Validates the format of an HMAC signature
		 *
		 * @param $value
		 *
		 * @return bool
		 */
		public static function validate_signature_id(
			$value
		): bool {
			return preg_match( self::PATTERN_SIGNATURE_ID, $value ) !== false;
		}

		/**
		 * Validates the format of a Subscription ID
		 *
		 * @param $value
		 *
		 * @return bool
		 */
		public static function validate_subscription_id(
			$value
		): bool {
			return preg_match( self::PATTERN_SUBSCRIPTION_ID, $value ) !== false;
		}

		/**
		 * Validates the format of a Subscription secret
		 *
		 * @param $value
		 *
		 * @return bool
		 */
		public static function validate_subscription_secret(
			$value
		): bool {
			return preg_match( self::PATTERN_SECRET, $value ) !== false;
		}

		/**
		 * Retrieve a submission, previously sent using `WPSEOAI::submit_post()`.
		 *
		 * @param $signature_id
		 *
		 * @return array|string|WP_Error
		 * @throws Exception
		 */
		public static function retrieve(
			$signature_id
		) {

			// Validate provided signature ID
			if ( self::validate_signature_id( $signature_id ) === false ) {
				throw new \Exception( 'Invalid signature ID', 1 );
			}

			// Request retrieval from the WPSEO.AI API
			$request = self::_request(
				"/v1/retrieve?signature={$signature_id}"
			);

			if (
				! is_array( $request )
				|| ! array_key_exists( 'code', $request )
				|| ! array_key_exists( 'response', $request )
			) {
				throw new \Exception( 'Malformed response', 2 );
			}

			if ( $request['code'] < 200 || $request['code'] > 204 ) {
				throw new \Exception( 'WPSEO.AI service is not available at this time, please try again later.', $request['code'] );
			}

			// If 200, should have a response payload
			if (
				array_key_exists( 'response', $request )
				&& is_array( $request['response'] )
				&& array_key_exists( 'payload', $request['response'] )
			) {
				$payload = json_decode( $request['response']['payload'], true );

				self::log( "retrieve-{$signature_id}", $payload );

				$data_string = base64_decode( $payload['data'] );
				$data        = json_decode( $data_string, true );

				[ $post_id, $revision_id ] = self::_save_response( $data );

				// Add post ID for audit record
				$data['post']['ID']          = $post_id;
				$data['post']['revision_id'] = $revision_id;

				$audit_post_id = 0;
				if ( $post_id ) {
					$audit_post_id = self::_save_audit( $data );
				}

				$request['post_id']       = $post_id;
				$request['audit_post_id'] = $audit_post_id;
			}

			return $request;
		}

		/**
		 * Submits the latest revision of post `$post_id`, to WPSEO.AI service for processing.
		 *
		 * @param $post_id
		 *
		 * @return array|WP_Error
		 */
		public static function submit_post(
			$post_id,
			$target_locale = null
		) {
			try {
				// Get the post object
				$post = get_post( $post_id );
				if ( is_null( $post ) ) {
					throw new Exception( 'Invalid post' );
				}

				// Prevent action loop
				if ( $post->post_type === self::POST_TYPE_RESPONSE ) {
					throw new Exception( 'Invalid post type' );
				}

				// Establish target locale
				if ( function_exists( 'wpml_get_setting' ) ) {
					global $sitepress;

					$locale = $target_locale;
					if ( is_null( $locale ) ) {
						$language_details = $sitepress->get_element_language_details(
							$post_id,
							"post_{$post->post_type}"
						);

						$locale = $language_details->language_code;
					}
				} else {
					$locale = $target_locale ?? get_locale();
				}

				// Extract metadata
				$submit = [
					'ID'           => $post_id,
					'locale'       => $locale,
					'post_type'    => $post->post_type,
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
					'post_date'    => $post->post_date,
					'post_name'    => $post->post_name,
					'post_url'     => get_permalink( $post_id ),
				];

				self::log( '$submit', $submit );

				// TODO: Implement categories and tags into submissions
//	                $categories = get_the_category( $post_id );
//	                if ( $categories && count( $categories ) ) {
//		                $submit['categories'] = $categories;
//	                }
//
//	                $tags = get_the_tags( $post_id );
//	                if ( $tags && count( $tags ) ) {
//		                $submit['tags'] = $tags;
//	                }

				// Plugin support: Advanced Custom Fields (ACF)
				// https://www.advancedcustomfields.com/resources/get_fields/
				if ( function_exists( 'get_fields' ) ) {
					$supported_types = [
						'text',
						'textarea',
						'wysiwyg',
						'flexible_content',
						'repeater',
					];
					$field_values    = get_fields( $post_id );

					self::log( 'service-input-acf', json_encode( $field_values, JSON_PRETTY_PRINT ) );

					if ( $field_values ) {
						$custom_fields = [];
						$fields        = get_field_objects( $post_id );

						self::log( 'service-input-acf-obj', json_encode( $fields, JSON_PRETTY_PRINT ) );

						// Process all ACF fields
						foreach ( $fields as $key => $field ) {
							if ( ! in_array( $field['type'], $supported_types ) ) {
								continue;
							}

							// Field: Flexible content
							if (
								$field['type'] === 'flexible_content'
								&& array_key_exists( 'layouts', $field )
							) {

								// Field: Layout
								foreach ( $field['layouts'] as $layout ) {
									if (
										! array_key_exists( 'sub_fields', $layout )
										|| ! is_array( $field['value'] )
									) {
										continue;
									}

									$acf_fc_layout             = $layout['name'];
									$acf_fc_layout_value_index = - 1;

									foreach ( $field['value'] as $value_index => $value_set ) {
										if (
											! array_key_exists( 'acf_fc_layout', $value_set )
											|| $value_set['acf_fc_layout'] !== $acf_fc_layout
										) {
											continue;
										}

										$acf_fc_layout_value_index = $value_index;
									}

									if ( $acf_fc_layout_value_index < 0 ) {
										continue;
									}

									foreach ( $layout['sub_fields'] as $sub_field ) {
										if ( ! in_array( $sub_field['type'], $supported_types ) ) {
											continue;
										}

										$labels = [
											$field['label']
										];
										if ( strlen( $sub_field['label'] ) ) {
											$labels[] = $sub_field['label'];
										}

										array_push(
											$custom_fields,
											[
												'context' => implode( ', ', $labels ),
												'key'     => $sub_field['key'],
												'value'   =>
													$field['value']
													[ $acf_fc_layout_value_index ]
													[ $sub_field['name'] ],
												'wysiwyg' => $sub_field['type'] === 'wysiwyg'
											]
										);
									}
								}
							} // Field: Repeater
                            elseif (
								$field['type'] === 'repeater'
								&& array_key_exists( 'sub_fields', $field )
							) {
								foreach ( $field['sub_fields'] as $sub_field ) {
									if ( ! in_array( $sub_field['type'], $supported_types ) ) {
										continue;
									}

									$labels = [
										$field['label']
									];
									if ( strlen( $sub_field['label'] ) ) {
										$labels[] = $sub_field['label'];
									}

									if (
										! empty( $sub_field['name'] )
										&& is_array( $field['value'] )
										&& array_key_exists( $sub_field['name'], $field['value'][0] )
									) {
										array_push(
											$custom_fields,
											[
												'context' => implode( ', ', $labels ),
												'key'     => $sub_field['key'],
												'value'   => $field['value'][0][ $sub_field['name'] ],
												'wysiwyg' => $sub_field['type'] === 'wysiwyg'
											]
										);
									}
								}
							} // Field: Text, Textarea, WYSIWYG
                            elseif (
								is_array( $field_values )
								&& array_key_exists( $key, $field_values )
							) {
								array_push(
									$custom_fields,
									[
										'context' => $field['label'],
										'key'     => $field['key'],
										'value'   => $field_values[ $key ],
										'wysiwyg' => $field['type'] === 'wysiwyg'
									]
								);
							}
						}

						// If we have a collection of fields, add to submission
						if ( count( $custom_fields ) ) {
							$submit['acf'] = $custom_fields;
						}
					}
				}

				// Assemble the post data
				$data = [
					'post'   => $submit,
					'return' => [

						// Post back host
						'host' => esc_attr( sanitize_text_field( get_option( 'wpseoai_return_host', get_site_url() ) ) ),
					]
				];

				// Submit the post data, for processing
				$result = self::_request(
					'/v1/submit',
					$data,
					'POST'
				);

				if ( $result instanceof WP_Error ) {
					throw new Exception( 'Failed to submit' );
				}

				// Add signature for storage post
				$data['signature'] = $result['response']['signature'];

				// Create initial audit record
				$audit_post_id = self::_save_audit( $data );
				if ( ! $audit_post_id ) {
					throw new Exception( 'Failed to create submission audit record' );
				}

				$result['audit_post_id'] = $audit_post_id;

				return $result;
			} catch ( Exception $e ) {
				return new WP_Error( 'wpseoai', $e->getMessage(), $e );
			}
		}

		/**
		 * Get field keys via the current posts meta.
		 *
		 * Why this way? There are cases where sub-fields of `repeater` fields, were setting incomplete
		 * `meta_key` values with `update_field()`. Gathering the `meta_key`, via the field key
		 * in `meta_value` has tested OK, slightly ugly.
		 *
		 * NOTE: May refactor on future improvements for ACF
		 *
		 * @param $post_id
		 * @param $target_keys
		 *
		 * @return array
		 */
		public static function get_acf_field_meta_key_map(
			$post_id,
			$target_keys = []
		): array {
			global $wpdb;

			if ( ! count( $target_keys ) ) {
				return [];
			}

//			$target_keys   = join( "', '", $target_keys );
//			$meta_value_in = "'{$target_keys}'";
//
//			// Prepare the query
//			$query = $wpdb->prepare( "
//                SELECT meta_key, meta_value
//                FROM {$wpdb->postmeta}
//                WHERE post_id = %d AND meta_value IN ({$meta_value_in})
//                ", $post_id );

			$sql_meta_value_placeholders = join( ',', array_fill( 0, count( $target_keys ), '%s' ) );

			// Prepare the query
			$query = call_user_func_array(
				[
					$wpdb,
					'prepare'
				],
				array_merge(
					[
						"SELECT meta_key, meta_value
                        FROM {$wpdb->postmeta}
                        WHERE post_id = %d AND meta_value IN ({$sql_meta_value_placeholders})",
						$post_id
					],
					$target_keys
				)
			);

			// Execute the query
			$results = $wpdb->get_results( $query, ARRAY_A );

			$map = [];
			if ( count( $results ) ) {
				foreach ( $results as $result ) {
					$map[ $result['meta_value'] ] = substr( $result['meta_key'], 1 );
				}
			}

			return $map;
		}

		/**
		 * Send payload to WPSEO.AI with required HMAC signature, derived from Subscription ID, and Secret
		 *
		 * @param string $uri
		 * @param $data
		 * @param string $method
		 * @param bool $json
		 *
		 * @return array|string|WP_Error
		 */
		private static function _request(
			string $uri,
			$data = null,
			string $method = 'GET',
			bool $json = false
		) {
			try {

				// Get subscription ID and secret
				$subscription_id = self::_get_subscription_id();
				$secret          = self::_get_subscription_secret();
				if ( empty( $subscription_id ) || empty( $secret ) ) {
					throw new Exception( 'Missing subscription ID and secret' );
				}

				// Determine service host endpoint
				$debug     = esc_attr( sanitize_text_field( get_option( 'wpseoai_debug', 'false' ) ) );
				$subdomain = ( $debug === 'true' ) ? 'testio' : 'io';
				$host      = "https://{$subdomain}.wpseo.ai";

				// Prepare path
				$path = parse_url( $uri, PHP_URL_PATH );

				// Prepare query string
				$query = parse_url( $uri, PHP_URL_QUERY );

				// Prepare GET request
				$payload = $data;
				$method  = strtoupper( $method );

				// Update for POST request
				if ( $method === 'POST' ) {
					$encoded_data = base64_encode( json_encode( $data ) );
					$payload      = json_encode( [ 'data' => $encoded_data ] );
				}

				// Calculate the HMAC signature
				$timestamp = time();
				$content   = $method . ' ' . $path . $payload . $timestamp;
				$signature = hash_hmac( "sha256", $content, $secret );

				// Service endpoint
				$uri = $host . $path . ( $query ? "?{$query}" : null );

				// Make remote request to WPSEO.AI
				$options = [
					'method'      => $method,
					'sslverify'   => true,
					'data_format' => 'body',
					'body'        => $payload,
					'headers'     => [
						'Content-Type'      => 'application/json',
						'X-Subscription-ID' => $subscription_id,
						'X-Signature'       => $signature,
						'X-Timestamp'       => $timestamp,
					],
				];
				$request = wp_remote_post( $uri, $options );

				// Request failed
				if ( ! is_array( $request ) ) {
					throw new Exception( 'wp_remote_post failed' );
				}

				// Process response data, JSON if available
				$response_data = array_key_exists( 'body', $request ) ? json_decode( $request['body'], true ) : null;

				// Response code
				$code = $request['response']['code'];

				// Response message
				$message =

					// Service data message
					( is_array( $response_data ) && array_key_exists( 'message', $response_data ) )
						? $response_data['message']

						// HTTP response message
						: $request['response']['message'];

				// Return structure
				$return = [
					'code'     => $code,
					'message'  => $message,
					'response' => $response_data,
					'uri'      => $uri
				];

				// Determine if we need to return an array or JSON string
				$return_json = '';
				if ( $json ) {
					$return_json = json_encode( $return, JSON_PRETTY_PRINT );
					if ( $return_json === false ) {
						throw new Exception( 'JSON encode failed' );
					}
				}

				// Log the request
				self::log( "request-{$signature}", $return_json );

				return $json ? $return_json : $return;
			} catch ( Exception $e ) {
				return new WP_Error( 'wpseoai', $e->getMessage(), $e->getCode() );
			}
		}

		/**
		 * @param $data
		 *
		 * @return int
		 */
		private static function _save_audit(
			array $data
		): int {

			// Invalid data structure
			if (
				! array_key_exists( 'post', $data )
				|| ! array_key_exists( 'signature', $data )
			) {
				return 0;
			}

			// Add date for this audit entry
			$data['date'] = date( 'c' );

			// Get existing post ID
			$post_id = isset( $data['post']['ID'] ) ? intval( $data['post']['ID'] ) : 0;

			// Post name identifier for v1 WPSEO.AI responses, inspired by the revision naming convention
			$post_name = "{$post_id}-wpseoai-response-v1";

			// Get existing post, if exists
			$search_args = [
				'numberposts' => 1,
				'post_type'   => self::POST_TYPE_RESPONSE,
				'meta_key'    => self::META_KEY_SIGNATURE,
				'meta_value'  => $data['signature'],
			];
			$result      = get_posts( $search_args );

			// Establish response post structure
			$args = [
				'post_type'    => self::POST_TYPE_RESPONSE,
				'post_title'   => $data['signature'],
				'post_content' => $data['summary'] ?? '',
				'post_name'    => $post_name,
				'post_excerpt' => $data['creditUsed'] ?? '',
				'post_status'  => 'publish',
				'post_parent'  => $post_id,
			];

			// We have an existing response post, update it...
			if ( count( $result ) ) {
				try {

					// If value is `int`, replace with `WP_Post` object
					if ( is_int( $result[0] ) ) {
						$args['ID'] = $result[0];
						$result[0]  = get_post( $args['ID'] );
					}

					// Not an instance of `WP_Post`
					if ( ! ( $result[0] instanceof WP_Post ) ) {
						return 0;
					}

					// Ensure post ID is defined on args
					$args['ID'] = $result[0]->ID;

					// Escape slashes on post values
					foreach ( $data['post'] as $key => $value ) {
						if ( ! is_string( $value ) ) {
							continue;
						}

						$data['post'][ $key ] = stripslashes( $value );
					}

					// Escape slashes on any ACF field values
					if ( array_key_exists( 'acf', $data['post'] ) ) {
						foreach ( $data['post']['acf'] as $field ) {
							if ( ! array_key_exists( 'key', $field ) ) {
								continue;
							}

							$data['post']['acf'][ $field['key'] ]['value'] = stripslashes( $field['value'] );
						}
					}

					// Add receipt to response data
					$json_last          = get_post_meta( $args['ID'], self::META_KEY_JSON, true );
					$json               = is_array( $json_last ) ? $json_last : [];
					$json['received']   = array_key_exists( 'received', $json_last ) ? $json_last['received'] : [];
					$json['received'][] = $data;

					// Update the existing post
					$final_post_id = wp_update_post( $args, false );
					if ( ! $final_post_id ) {
						throw new Exception( 'Failed to update post' );
					}

					// Update subscription credit balance
					$subscription_id = self::_get_subscription_id();
					if ( strlen( $subscription_id ) ) {
						$credit                     = (array) get_option( 'wpseoai_credit', [] );
						$credit[ $subscription_id ] = intval( $data['creditRemaining'] );
						update_option( 'wpseoai_credit', $credit );
					}

					// Submission state - processed
					update_post_meta( $final_post_id, self::META_KEY_STATE, 1 );

					// Update response data
					update_post_meta( $final_post_id, self::META_KEY_JSON, $json );

					return $final_post_id;
				} catch ( \Exception $e ) {
					// TODO: Log error on `_save_audit`? Currently returns silently
//                    var_dump( $e->getMessage(), 'Exception' );
					return 0;
				}
			}

			// Save initial submission data
			$json = [
				'sent' => $data
			];

			// Doesn't exist; Create new response post
			$final_post_id = wp_insert_post( $args );
			if ( ! is_int( $final_post_id ) ) {
				return 0;
			}

			// Used for `get_posts()` lookup, by meta key/value
			add_post_meta( $final_post_id, self::META_KEY_SIGNATURE, $data['signature'] );

			// Submission state - pending
			add_post_meta( $final_post_id, self::META_KEY_STATE, 0 );

			// Add response data
			add_post_meta( $final_post_id, self::META_KEY_JSON, $json );

			return $final_post_id;
		}

		/**
		 * @param $data
		 *
		 * @return int
		 */
		private static function _save_response(
			array $data
		): array {
			global $sitepress;

			if ( ! array_key_exists( 'post', $data ) ) {
//                throw new Exception('Invalid payload data');
				return [ 0, 0 ];
			}

			// Get existing post ID
			$post_id = isset( $data['post']['ID'] ) ? intval( $data['post']['ID'] ) : null;

			self::log( '$post_id', $post_id );
//			self::log( "function_exists( 'wpml_get_setting' )", function_exists( 'wpml_get_setting' ) );
//			self::log( '$sitepress', $sitepress );

			// WPML support checks for specific locale target
			if (
				function_exists( 'wpml_get_setting' )
				&& $sitepress && $post_id
			) {

				// Establish response language
				$locale = $data['post']['locale'];
				self::log( '$locale', $locale );
				if ( strpos( $locale, '_' ) !== false ) {
					[ $language_code, $language_region ] = explode( '_', $locale );
				} else {
					$language_code = $locale;
				}

				// Lookup translation information
				$el_type          = "post_{$data['post']['post_type']}";
				$default_language = $sitepress->get_default_language();

				$language_details = $sitepress->get_element_language_details(
					$post_id,
					$el_type
				);
				self::log( '$language_details', $language_details );

				// Translation doesn't exist, duplicate master post and add response to it.
				if ( is_null( $language_details ) ) {

					// Is not the default language, create a duplicate for the response data
					self::log( '$language_code', $language_code );
					self::log( '$default_language', $default_language );
					if ( $language_code !== $default_language ) {
						$post_id = $sitepress->make_duplicate( $post_id, $language_code );
					}
				} // This post ID is a translation
                elseif ( is_object( $language_details ) ) {
					$translations = $sitepress->get_element_translations(
						$language_details->trid,
						$language_details->element_type
					);
					self::log( '$translations', $translations );

					// Switch to the correct translation post ID
					if ( is_array( $translations ) && array_key_exists( $language_code, $translations ) ) {
						$post_id = $translations[ $language_code ]->element_id;
						self::log( 'translation_found', $post_id );
					} // Not found, create a duplicate for the response data
                    elseif ( $language_code !== $default_language ) {
						$post_id = $sitepress->make_duplicate( $post_id, $language_code );
						self::log( 'make_duplicate', $post_id );
					}

					self::log( '$post_id', $post_id );
				}

				// Update data post ID
				$data['post']['ID'] = $post_id;
			}

			// Add a revision, to existing post.
			if ( $post_id && get_post_status( $post_id ) ) {

				// Post is currently locked
//	                if ( wp_check_post_lock( $post_id ) ) {
//                        return new WP_REST_Response( [ 'message' => 'Post currently locked', 'code' => 8 ], 400 );
//	                }

				// Get original post
				$post = get_post( $post_id, ARRAY_A );
				if ( ! is_array( $post ) ) {
					return [ 0, 0 ];
				}

				// Merge changes into the post
				foreach ( $post as $key => $value ) {
					if ( array_key_exists( $key, $data['post'] ) ) {
						$post[ $key ] = $data['post'][ $key ];
					}
				}

				if ( array_key_exists( 'acf', $data['post'] ) ) {

					// Walk fields to collect keys
					$field_keys = [];
					foreach ( $data['post']['acf'] as $field ) {
						if ( ! array_key_exists( 'key', $field ) ) {
							continue;
						}

						$field_keys[] = $field['key'];
					}

					$field_meta_key = self::get_acf_field_meta_key_map( $post_id, $field_keys );

					self::log( 'field-meta-keys', $field_meta_key );

					foreach ( $data['post']['acf'] as $field ) {
						if ( ! array_key_exists( 'key', $field ) ) {
							continue;
						}

						if ( array_key_exists( $field['key'], $field_meta_key ) ) {
							update_metadata(
								'post',
								$post_id,
								$field_meta_key[ $field['key'] ],
								$field['value']
							);

							continue;
						}

						// Fallback to `update_field()`
						update_field( $field['key'], $field['value'], $post_id );
					}
				}

				// Update post data
				$final_post_id = wp_update_post( $post );
				if ( ! is_int( $final_post_id ) ) {
					return [ 0, 0 ];
				}

				// Get latest revision ID
				$revisions     = wp_get_post_revisions( $final_post_id, [
					'numberposts' => 1
				] );
				$revision_keys = array_keys( $revisions );
				$revision_id   = array_pop( $revision_keys );

				// Copy the post metadata to new revision
				$post_meta = get_post_meta( $final_post_id );
				foreach ( $post_meta as $key => $values ) {
					foreach ( $values as $value ) {
						update_metadata( 'post', $revision_id, $key, $value );
					}
				}

				// Add response optimisation summary
				if ( strlen( $data['summary'] ) ) {
					update_metadata( 'post', $revision_id, self::META_KEY_SUMMARY, $data['summary'] );
					update_metadata( 'post', $final_post_id, self::META_KEY_SUMMARY, $data['summary'] );
				}

				// Add submission signature ID
				update_metadata( 'post', $revision_id, self::META_KEY_SIGNATURE, $data['signature'] );
				update_metadata( 'post', $final_post_id, self::META_KEY_SIGNATURE, $data['signature'] );
			} // Create a new post
			else {
				$final_post_id = wp_insert_post( [
					'post_type'         => $data['post']['post_type'],
					'post_title'        => $data['post']['post_title'],
					'post_content'      => $data['post']['post_content'],
					'post_name'         => $data['post']['post_name'],
					'post_excerpt'      => $data['post']['post_excerpt'],
					'post_status'       => 'draft',
					'post_date'         => '',
					'post_date_gmt'     => '',
					'post_modified'     => '',
					'post_modified_gmt' => '',
				] );
				if ( ! is_int( $final_post_id ) ) {
					return [ 0, 0 ];
				}

				// Get latest revision ID
				$revisions     = wp_get_post_revisions( $final_post_id, [
					'numberposts' => 1
				] );
				$revision_keys = array_keys( $revisions );
				$revision_id   = array_pop( $revision_keys );

				// Update ACF data, if present
				if ( array_key_exists( 'acf', $data['post'] ) ) {
					foreach ( $data['post']['acf'] as $key => $field ) {
						if ( array_key_exists( 'key', $field ) ) {
							update_field( $field['key'], $field['value'], $final_post_id );
						}
					}
				}

				// Add response optimisation summary
				if ( strlen( $data['summary'] ) ) {
					add_post_meta( $final_post_id, self::META_KEY_SUMMARY, $data['summary'] );
				}

				// Add submission signature ID
				add_post_meta(
					$final_post_id,
					self::META_KEY_SIGNATURE,
					$data['signature']
				);
			}

			return [
				$final_post_id,
				$revision_id
			];
		}
	}
}

// Retrieve or establish the `WPSEOAI` instance
WPSEOAI::get_instance();