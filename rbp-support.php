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
		
		/**
		 * The RBP Store URL
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		private $store_url = 'https://realbigplugins.com';
		
		/**
		 * The full Plugin File path of the Plugin this Class is instantiated from
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		public $plugin_file;
		
		/**
		 * The full path to the containing directory of the Plugin File. This is for convenience within the Class
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		private $plugin_dir;
		
		/**
		 * The Plugin's Data as an Array. This is used by the Licensing aspects of this Class
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			array
		 */
		public $plugin_data;
		
		/**
		 * The stored License Key for this Plugin
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		private $license_key;
		
		/**
		 * The stored License Validity for the License Key
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		private $license_validity;
		
		/**
		 * The stored License Data for the License Key
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			array
		 */
		private $license_data;
		
		/**
		 * The Prefix used when creating/reading from the Database. This is determined based on the Text Domain within Plugin Data
		 * If License Key and/or License Validity are not defined, this is used to determine where to look in the Database for them
		 * It is also used to form the occasional Hook or Filter to make it specific to your Plugin
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		private $prefix;
		
		/**
		 * RBP_Support constructor.
		 * The Plugin Data Array is REQUIRED as it makes grabbing data from EDD's API possible
		 * However, the other two parameters can either be provided by your own code or you can allow this class to determine them
		 * 
		 * @param		string	$plugin_file	  Path to the Plugin File. REQUIRED
		 * @param		array	$plugin_data	  get_plugin_data( <your_plugin_file>, false ); This is REQUIRED.
		 * @param		string  $license_key	  License Key for this plugin. Null is not set.
		 * @param		string  $license_validity True for Valid, False for Invalid. Null to grab validity from Server
		 *                                                                                              
		 * @since		{{VERSION}}
		 */
		function __construct( $plugin_file = null, $plugin_data = null, $license_key = null, $license_validity = null ) {
			
			$this->load_textdomain();
			
			if ( $plugin_file == null || 
			   ! is_string( $plugin_file ) ) {
				throw new Exception( __( 'Missing Plugin File Path in RBP_Support Constructor', 'rbp-support' ) );
			}
			
			$this->plugin_file = $plugin_file;
			
			// Helpful for allowing the Plugin to override views
			$this->plugin_dir = trailingslashit( dirname( $this->plugin_file ) );
			
			if ( $plugin_data == null || 
			   ! is_array( $plugin_data ) ) {
				throw new Exception( __( 'Missing Plugin Data Array in RBP_Support Constructor', 'rbp-support' ) );
			}
			
			$this->plugin_data = $plugin_data;
			
			// Create Prefix used for things like Transients
			// This is used for some Actions/Filters and if License Key and/or Validity aren't provided
			$this->prefix = str_replace( '-', '_', $this->plugin_data['TextDomain'] );
			
			if ( $license_key == null ) {
				$this->license_key = $this->retrieve_license_key();
			}
			else {
				$this->license_key = $license_key;
			}
			
			if ( $license_validity == null ) {
				// Check validity itself
				$this->license_validity = $this->retrieve_license_validity( $this->license_key, $this->plugin_data );
			}
			else {
				$this->license_validity = $license_validity;
			}
			
			add_action( 'admin_init', array( $this, 'setup_plugin_updates' ) );
			
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
				
				if ( file_exists( $this->plugin_dir . 'rbp-support/sidebar-support.php' ) ) {
					include_once $this->plugin_dir . 'rbp-support/sidebar-support.php';
				}
				else {
					include_once __DIR__ . '/views/sidebar-support.php';
				}
				
			}
			else {
				
				if ( file_exists( $this->plugin_dir . 'rbp-support/sidebar-support-disabled.php' ) ) {
					include_once $this->plugin_dir . 'rbp-support/sidebar-support-disabled.php';
				}
				else {
					include_once __DIR__ . '/views/sidebar-support-disabled.php';
				}
				
			}
			
		}
		
		/**
		 * Sets up Plugin Updates as well as place a License Nag within the Plugins Table
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		void
		 */
		public function setup_plugin_updates() {
			
			if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				require_once __DIR__ . '/includes/EDD-License-handler/EDD_SL_Plugin_Updater.php';
			}
			
			if ( is_admin() ) {
				
				$license = new EDD_SL_Plugin_Updater(
					$this->store_url,
					$this->plugin_file,
					array(
						'item_name' => $this->plugin_data['Name'],
						'version'   => $this->plugin_data['Version'],
						'license'   => $this->license_key,
						'author'    => $this->plugin_data['Author'],
					)
				);
				
				if ( $this->get_license_validity() != 'valid' ) {
					add_action( 'after_plugin_row_' . plugin_basename( $this->plugin_file ),
						array( $this, 'show_license_nag' ), 10, 2 );
				}
				
			}
			
		}
		
		/**
		 * Displays a nag to activate the license.
		 *
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		void
		 */
		public function show_license_nag() {
			
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			
			?>

			<tr class="plugin-update-tr">
				<td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
					<div class="update-message">
						<?php
			
							// We can't know or predict the URL of your Plugin's Settings/Licensing page
							// This filter will allow you to include a link to it if you want
							$register_message = apply_filters( $this->prefix . '_register_message', sprintf(
								__( 'Register your copy of %s now to receive automatic updates and support.', 'rbp-support' ),
								$this->plugin_data['Name']
							) );
			
							echo $register_message;
			
							if ( ! $this->get_license_key() ) {
								printf(
									__( ' If you do not have a license key, you can %1$spurchase one%2$s.', 'rbp-support' ),
									'<a href="' . $this->plugin_data['PluginURI'] . '">',
									'</a>'
								);
							}
			
						?>
					</div>
				</td>
			</tr>

			<?php
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
				$this->license_validity = $this->retrieve_license_validity();
			}
			
			return $this->license_validity;
			
		}
		
		/**
		 * Getter Method for License Key
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		string License Key
		 */
		public function get_license_key() {
			
			if ( ! $this->license_key ) {
				
				$this->license_key = $this->retrieve_license_key();
				
			}
			
			return $this->license_key;
			
		}
		
		/**
		 * Returns license data.
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		array License Data
		 */
		public function get_license_data() {

			if ( ! $this->license_data ) {

				$this->license_data = $this->retrieve_license_data();
				
			}

			return $this->license_data;
		}
		
		/**
		 * Check the License Key's Validity. This is used if Validity is not provided.
		 *                                                   
		 * @access		private
		 * @since		{{VERSION}}
		 * @return		string License Validity
		 */
		private function retrieve_license_validity() {
			
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
			
			set_transient( $this->prefix . '_license_data', $license_data, DAY_IN_SECONDS );
			
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
		 * Gets the License Key from the Database. This is used when one is not provided by the constructor.
		 * 
		 * @access		private
		 * @since		{{VERSION}}
		 * @return		string License Key
		 */
		private function retrieve_license_key() {
			
			if ( ! $this->license_key ) {
				
				if ( isset( $_REQUEST[ $this->prefix . '_license_key' ] ) ) {
					$this->license_key = trim( $_REQUEST[ $this->prefix . '_license_key' ] );
				}
				else {
					$this->license_key = trim( get_option( $this->prefix . '_license_key' ) );
				}
				
			}
			
			return $this->license_key;
			
		}
		
		/**
		 * Gets License Data from Database/Remote Store
		 * 
		 * @access		private
		 * @since		{{VERSION}}
		 * @return array License Data
		 */
		private function retrieve_license_data() {

			$data = get_transient( $this->prefix . '_license_data' );

			if ( $data ) {
				return $data;
			}

			$license_key = $this->get_license_key();

			if ( ! $license_key ) {
				return false;
			}

			$api_params = array(
				'edd_action' => 'check_license',
				'license' => $license_key,
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

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			set_transient( $this->prefix . '_license_data', $data, DAY_IN_SECONDS );

			return $data;
			
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
		
		public static function debug_file( $plugin_prefix ) {

			$output = '';

			// Installed Plugins
			$installed_plugins = get_plugins();

			if ( $installed_plugins ) {

				$output .= "= Installed Plugins =\n";

				foreach ( $installed_plugins as $id => $plugin ) {

					$output .= "$plugin[Name]: $plugin[Version]\n";
					
				}
				
			}

			// Active Plugins
			$active_plugins = get_option( 'active_plugins' );

			if ( $active_plugins ) {

				$output .= "\n= Active Plugins =\n";

				foreach ( $active_plugins as $id ) {

					$plugin = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $id );

					$output .= "$plugin[Name]: $plugin[Version]\n";
					
				}
				
			}

			// Theme
			$output .= "\n= Active Theme =\n";

			$theme = wp_get_theme();

			$output .= "Name: " . $theme->get( 'Name' ) . "\n";
			$output .= "Version: " . $theme->get( 'Version' ) . "\n";
			$output .= "Theme URI: " . $theme->get( 'ThemeURI' ) . "\n";
			$output .= "Author URI: " . $theme->get( 'AuthorURI' ) . "\n";

			$template = $theme->get( 'Template' );

			if ( $template ) {

				$output .= "Parent Theme: $template\n";
				
			}

			// PHP
			$output .= "\n= PHP Info =\n";
			$output .= "Version: " . phpversion();
			
			/**
			 * Allow additional information to be added to the Debug File
			 * 
			 * @since		{{VERSION}}
			 */
			$output = apply_filters( $plugin_prefix . '_debug_file', $output );

			return $output;
			
		}
		
		/**
		 * Send a Support Email via Ajax
		 * This is done via Ajax to allow more flexibility in DOM structure. Depending on the plugin, you may not have much freedom in how or where the Support Form is placed. Using Ajax helps alleviate any conflicts where you may potentially submit the wrong Form
		 * 
		 * @access		public
		 * @since		{{VERSION}}
		 * @return		void
		 */
		public static function send_support_mail() {
			
			$plugin_prefix = $_POST['plugin_prefix'];
			$license_data = json_decode( $_POST['license_data'] );
			
			if ( ! isset( $_POST[ $plugin_prefix . '_nonce' ] ) ||
				! wp_verify_nonce( $_POST[ $plugin_prefix . '_nonce' ],  $plugin_prefix . '_send_support_email' ) ||
				! current_user_can( 'manage_options' ) ) {

				return;
				
			}

			/**
			 * Data to be sent in the support email.
			 * 
			 * @since		{{VERSION}}
			 */
			$data = apply_filters( $plugin_prefix . '_support_email_data', array(
				'subject' => esc_attr( $_POST['support_subject'] ),
				'message' => esc_attr( $_POST['support_message'] ),
			), $_POST );

			$subject = trim( $data['subject'] );
			$message = trim( $data['message'] );

			if ( ! $license_data ||
				empty( $subject ) ||
				empty( $message ) ) {

				$result = false;

			}
			else {

				$debugging_file = self::debug_file( $plugin_prefix );

				$result = wp_mail(
					'support@realbigplugins.com',
					$data['subject'],
					$data['message'],
					array(
						"From: $license_data[customer_name] <$license_data[customer_email]>",
					),
					array(
						$debugging_file,
					)
				);
			}
			
		}
		
	}
	
}

add_action( 'wp_ajax_rbp_support', array( 'RBP_Support', 'send_support_mail' ) );