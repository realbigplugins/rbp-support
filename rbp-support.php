<?php
/**
 * Class RBP_Support
 *
 * Allows a Support Form to be quickly added to our Plugins
 * It includes a bunch of (filterable) Debug Info that gets sent along with the Email
 *
 * @since {{VERSION}}
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RBP_Support' ) ) {
	
	class RBP_Support {
		
		private $store_url = 'https://realbigplugins.com';
		
		
		public $plugin_data;
		private $license_key;
		private $license_validity;
		private $prefix;
		
		/**
		 * RBP_Support constructor.
		 * The Plugin Data Array is REQUIRED as it makes grabbing data from EDD's API possible
		 * However, the other two parameters can either be provided by your own code or you can allow this class to determine them
		 * 
		 * @param		array	$plugin_data	  get_plugin_data( <your_plugin_file>, false ); This is REQUIRED.
		 * @param		string  $license_key	  License Key for this plugin. Null is not set.
		 * @param		string  $license_validity True for Valid, False for Invalid. Null to grab validity from Server
		 *                                                                                              
		 * @since		{{VERSION}}
		 */
		function __construct( $plugin_data = null, $license_key = null, $license_validity = null ) {
			
			$this->load_textdomain();
			
			if ( $plugin_data == null || 
			   ! is_array( $plugin_data ) ) {
				throw new Exception( __( 'Missing Plugin Data Array in RBP_Support Constructor', 'rbp-support' ) );
			}
			
			$this->plugin_data = $plugin_data;
			
			// Create Prefix used for things like Transients
			// This is used for some Actions/Filters and if License Key and/or Validity aren't provided
			$this->prefix = str_replace( '-', '_', $this->plugin_data['TextDomain'] );
			
			if ( $license_key == null ) {
				$this->license_key = get_option( $this->prefix . '_license_key' );
			}
			else {
				$this->license_key = $license_key;
			}
			
			if ( $license_validity == null ) {
				// Check validity itself
				$this->license_validity = $this->check_license_validity( $this->license_key, $this->plugin_data );
			}
			else {
				$this->license_validity = $license_validity;
			}
			
		}
		
		/**
		 * Internationalization
		 *
		 * @access		private
		 * @since		1.0.0
		 * @return		void
		 */
		private function load_textdomain() {

			// Set filter for language directory
			$lang_dir = __DIR__ . '/languages/';
			$lang_dir = apply_filters( 'rbp_support_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'rbp-support' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'rbp-support', $locale );

			// Setup paths to current locale file
			$mofile_local   = $lang_dir . $mofile;
			$mofile_global  = WP_LANG_DIR . '/' . 'rbp-support' . '/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/rbp-support/ folder
				// This way translations can be overridden via the Theme/Child Theme
				load_textdomain( 'rbp-support', $mofile_global );
			}
			else if ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/<some_plugin_directory>/rbp-support/languages/ folder
				load_textdomain( 'rbp-support', $mofile_local );
			}
			else {
				// Load the default language files
				load_plugin_textdomain( 'rbp-support', false, $lang_dir );
			}

		}
		
		/**
		 * Outputs the Support Form. Call this method within whatever container you like.
		 * You can override the Template as needed, but it should pull in any and all data for your Plugin automatically
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		void
		 */
		public function support_form() {
			
			// Makes the variable make more sense within the context of the HTML
			$plugin_prefix = $this->prefix;
			
			if ( $this->get_license_validity() == 'valid' ) {
				
				include_once apply_filters( $this->prefix . '_sidebar_support_path', __DIR__ . '/views/sidebar-support.php' );
				
			}
			else {
				
				include_once apply_filters( $this->prefix . '_sidebar_support_disabled_path', __DIR__ . '/views/sidebar-support-disabled.php' );
			}
			
		}
		
		/**
		 * Getter Method for License Validty
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		string License Validity
		 */
		public function get_license_validity() {
			
			if ( ! $this->license_validity ) {
				$this->license_validity = $this->check_license_validity();
			}
			
			return $this->license_validity;
			
		}
		
		/**
		 * Check the License Key's Validity. This is used if Validity is not provided.
		 *                                                   
		 * @access		private
		 * @since		{{VERSION}}
		 * @return		string License Validity
		 */
		private function check_license_validity() {
			
			if ( $this->license_validity !== null ) {
				return $this->license_validity;
			}
			
			if ( ! $this->license_key ) {
				return 'invalid';
			}
			
			if ( ! isset( $_GET['force-check-license'] ) && 
				$license_status = get_transient( $this->prefix . '_license_validity' ) ) {
				return $license_status;
			}
			
			$api_params = array(
				'edd_action' => 'check_license',
				'license' => $this->license_key,
				'item_name' => $this->plugin_data['Name'],
				'url' => home_url(),
			);
			
			// Call the custom API.
			$response = wp_remote_get(
				add_query_arg( $api_params, $this->store_url ),
				array(
					'timeout'   => 10,
					'sslverify' => false
				)
			);
			
			if ( is_wp_error( $response ) ) {
				return false;
			}
			
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
			if ( ! $license_data->success ||
				$license_data->license !== 'valid' ) {
				
				$message = self::get_license_error_message(
					! $license_data->success ? $license_data->error : $license_data->license,
					$license_data,
					$this->plugin_data
				);
				
				add_settings_error( $this->prefix, '', $message, 'error ' . $this->prefix . '-notice' );
				
			}
			
			$license_status = isset( $license_data->license ) ? $license_data->license : 'invalid';
			
			set_transient( $this->prefix . '_license_validity', $license_status, DAY_IN_SECONDS );
			
			$this->license_validity = $license_status;
			
			return $license_status;
			
		}
		
		/**
		 * Grabs the appropriate Error Message for each License Error
		 * This is a "static" method so that if necessary, this method can be used without an instance of the Class
		 * 
		 * @param		string $error_code   Type of Error
		 * @param		object $license_data License Data response object from EDD API
		 * @param		array  $plugin_data  get_plugin_data( <your_plugin_file>, false );
		 *                                                                         
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		string Error Message
		 */
		public static function get_license_error_message( $error_code, $license_data, $plugin_data = null ) {
			
			if ( $plugin_data == null || 
			   ! is_array( $plugin_data ) ) {
				throw new Exception( __( 'Missing Plugin Data Array while checking License Error Message', 'rbp-support' ) );
			}
			
			switch ( $error_code ) {
					
				case 'expired':
					$message = sprintf(
						__( 'Your license key expired on %s.', 'rbp-support' ),
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
					);
					break;
				case 'revoked':
					$message = __( 'Your license key has been disabled.', 'rbp-support' );
					break;
				case 'missing':
					$message = __( 'Invalid license.', 'rbp-support' );
					break;
				case 'invalid':
				case 'site_inactive':
					$message = __( 'Your license is not active for this URL.', 'rbp-support' );
					break;
				case 'item_name_mismatch':
					$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'rbp-support' ), $plugin_data['Name'] );
					break;
				case 'no_activations_left':
					$message = __( 'Your license key has reached its activation limit.', 'rbp-support' );
					break;
				default:
					$message = __( 'An error occurred, please try again.', 'rbp-support' );
					break;
					
			}
			
			return $message;
			
		}
		
	}
	
}