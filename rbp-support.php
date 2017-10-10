<?php
/**
 * Class RBP_Support
 *
 * Allows a Support Form to be quickly added to our Plugins
 * It includes a bunch of (filterable) Debug Info that gets sent along with the Email
 *
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RBP_Support' ) ) {
	
	class RBP_Support {
		
		/**
		 * Holds the Version Number of RBP_Support.
		 * This is used in the Support Email to help us know which version of RBP_Support is being used in the event multiple Plugins are utilizing it on a certain site. If a plugin loads an outdated version, all other Plugins will use that outdated version.
		 * See https://github.com/realbigplugins/rbp-support/issues/5
		 *
		 * @since		{{VERSION}}
		 *
		 * @var			string
		 */
		public $version = '1.0.0';
		
		/**
		 * The RBP Store URL
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $store_url = 'https://realbigplugins.com';
		
		/**
		 * The full Plugin File path of the Plugin this Class is instantiated from
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		public $plugin_file;
		
		/**
		 * The full path to the containing directory of the Plugin File. This is for convenience within the Class
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $plugin_dir;
		
		/**
		 * The Plugin's Data as an Array. This is used by the Licensing aspects of this Class
		 *
		 * @since		1.0.0
		 *
		 * @var			array
		 */
		public $plugin_data;
		
		/**
		 * The stored License Key for this Plugin
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $license_key;
		
		/**
		 * The stored License Status for the License Key
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $license_status;
		
		/**
		 * The stored License Validity for the License Key
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $license_validity;
		
		/**
		 * The stored License Data for the License Key
		 *
		 * @since		1.0.0
		 *
		 * @var			array
		 */
		private $license_data;
		
		/**
		 * The Prefix used when creating/reading from the Database. This is determined based on the Text Domain within Plugin Data
		 * If License Key and/or License Validity are not defined, this is used to determine where to look in the Database for them
		 * It is also used to form the occasional Hook or Filter to make it specific to your Plugin
		 *
		 * @since		1.0.0
		 *
		 * @var			string
		 */
		private $prefix;
		
		/**
		 * This stores the "Setting" to apply Settings Errors to. EDD in particular is picky about this and it needs to be 'edd-notices'
		 * There is a Filter in the Constructor for this for cases like this. Otherwise this is <prefix>_license_key
		 * 
		 * @since		1.0.0
		 * 
		 * @var			string
		 */
		private $settings_error;
		
		/**
		 * RBP_Support constructor.
		 * The Plugin Data Array is REQUIRED as it makes grabbing data from EDD's API possible
		 * However, the other two parameters can either be provided by your own code or you can allow this class to determine them
		 * 
		 * @param		string	$plugin_file	  Path to the Plugin File. REQUIRED
		 * @param		array	$plugin_data	  get_plugin_data( <your_plugin_file>, false ); This is REQUIRED.
		 *                                                                                              
		 * @since		1.0.0
		 */
		function __construct( $plugin_file = null ) {
			
			$this->load_textdomain();
			
			if ( $plugin_file == null || 
			   ! is_string( $plugin_file ) ) {
				throw new Exception( __( 'Missing Plugin File Path in RBP_Support Constructor', 'rbp-support' ) );
			}
			
			$this->plugin_file = $plugin_file;
			
			// Helpful for allowing the Plugin to override views
			$this->plugin_dir = trailingslashit( dirname( $this->plugin_file ) );
			
			$this->plugin_data = get_plugin_data( $plugin_file, false );
			
			// Create Prefix used for things like Transients
			// This is used for some Actions/Filters and if License Key and/or Validity aren't provided
			$this->prefix = strtolower( trim( str_replace( '-', '_', $this->plugin_data['TextDomain'] ) ) );
			
			/**
			 * WARNING: This is a global Filter
			 * You should only apply this directly before creating your RBP_Support object and should remove it immediately after
			 * 
			 * @since		{{VERSION}}
			 * @return		string
			 */
			$this->prefix = apply_filters( 'rbp_support_prefix', $this->prefix );
			
			/**
			 * Allows the "Setting" for Settings Errors to be overriden
			 * EDD in particular is picky about this and it needs to be 'edd-notices', so this can be very useful
			 *
			 * @since		1.0.0
			 * @return		string
			 */
			$this->settings_error = apply_filters( $this->prefix . '_settings_error', $this->prefix . '_license_key' );
			
			$this->license_key = $this->retrieve_license_key();
			
			if ( isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ) {
				
				switch ( $_REQUEST[ $this->prefix . '_license_action' ] ) {
					case 'activate':
					case 'save':
						add_action( 'admin_init', array( $this, 'activate_license' ) );
						break;
					case 'deactivate':
						add_action( 'admin_init', array( $this, 'deactivate_license' ) );
						break;
					case 'delete':
						add_action( 'admin_init', array( $this, 'delete_license' ) );
						break;
					case 'delete_deactivate':
						add_action( 'admin_init', array( $this, 'delete_license' ) );
						add_action( 'admin_init', array( $this, 'deactivate_license' ) );
						break;
				}
				
			}
			
			if ( isset( $_REQUEST[ $this->prefix . '_support_submit' ] ) ) {
				
				add_action( 'admin_init', array( $this, 'send_support_email' ) );
				
			}
			
			// Set up plugin updates
			add_action( 'admin_init', array( $this, 'setup_plugin_updates' ) );
			
			// Check License Validity
			add_action( 'admin_init', array( $this, 'get_license_validity') );
			
			// Scripts are registered/localized, but it is on the Plugin Developer to enqueue them
			add_action( 'admin_init', array( $this, 'register_scripts' ) );
			
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
		 * @since		1.0.0
		 * @return		void
		 */
		public function support_form() {
			
			// Makes the variable make more sense within the context of the HTML
			$plugin_prefix = $this->prefix;
			
			$plugin_name = $this->plugin_data['Name'];
			
			if ( $this->get_license_status() == 'valid' ) {
				
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
		 * Outputs the Support Form. Call this method within whatever container you like.
		 * You can override the Template as needed, but it should pull in any and all data for your Plugin automatically
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function licensing_fields() {
			
			// Makes the variable make more sense within the context of the HTML
			$plugin_prefix = $this->prefix;
			$license_status = $this->get_license_status();
			
			$license_key = $this->get_license_key();
			$plugin_name = $this->plugin_data['Name'];
				
			if ( file_exists( $this->plugin_dir . 'rbp-support/licensing-fields.php' ) ) {
				include_once $this->plugin_dir . 'rbp-support/licensing-fields.php';
			}
			else {
				include_once __DIR__ . '/views/licensing-fields.php';
			}
			
		}
		
		/**
		 * Enqueues Styles and Scripts for both the Form and Licensing. Use this if they're on the same page
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function enqueue_all_scripts() {
			
			$this->enqueue_form_scripts();
			$this->enqueue_licensing_scripts();
			
		}
		
		/**
		 * Enqueues the Styles and Scripts for the Support Form only
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function enqueue_form_scripts() {
			
			wp_enqueue_script( $this->prefix . '_form' );
			wp_enqueue_style( $this->prefix . '_form' );
			
		}
		
		/**
		 * Enqueues the Styles and Scripts for the Licensing stuff only
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function enqueue_licensing_scripts() {
			
			//wp_enqueue_script( $this->prefix . '_licensing' );
			wp_enqueue_style( $this->prefix . '_licensing' );
			
		}
		
		/**
		 * Getter Method for License Validty
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		string License Validity
		 */
		public function get_license_validity() {
			
			if ( ! $this->license_validity ) {
				$this->license_validity = $this->check_license_validity();
			}
			
			return $this->license_validity;
			
		}
		
		/**
		 * Getter Method for License Status
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		string License Status
		 */
		public function get_license_status() {
			
			if ( ! $this->license_status ) {
				$this->license_status = $this->retrieve_license_status();
			}
			
			return $this->license_status;
			
		}
		
		/**
		 * Getter Method for License Key
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		string License Key
		 */
		public function get_license_key() {
			
			if ( ! $this->license_key ) {
				
				$this->license_key = $this->retrieve_license_key();
				
			}
			
			return $this->license_key;
			
		}
		
		/**
		 * Getter Method for License Data
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		array License Data
		 */
		public function get_license_data() {

			if ( ! $this->license_data ) {

				$this->license_data = $this->retrieve_license_data();
				
			}

			return $this->license_data;
		}
		
		/**
		 * Register Scripts
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function register_scripts() {
			
			wp_register_script(
				$this->prefix . '_form',
				plugins_url( '/assets/js/form.js', __FILE__ ),
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : time(),
				true
			);
			
			wp_register_style(
				$this->prefix . '_form',
				plugins_url( '/assets/css/form.css', __FILE__ ),
				array(),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : time(),
				'all'
			);
			
			wp_register_script(
				$this->prefix . '_licensing',
				plugins_url( '/assets/js/licensing.js', __FILE__ ),
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : time(),
				true
			);
			
			wp_register_style(
				$this->prefix . '_licensing',
				plugins_url( '/assets/css/licensing.css', __FILE__ ),
				array(),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : time(),
				'all'
			);
			
			wp_localize_script( 
				$this->prefix . '_form',
				$this->prefix . '_support_form',
				apply_filters( $this->prefix . '_localize_form_script', array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'validationError' => __( 'This field is required', 'rbp-support' ), // Only used for legacy browsers
				) )
			);
			
		}
		
		/**
		 * Check the License Key's Validity
		 *                                                   
		 * @access		private
		 * @since		1.0.0
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
				$license_validity = get_transient( $this->prefix . '_license_validity' ) ) {
				return $license_validity;
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
				
				$message = $this->get_license_error_message(
					! $license_data->success ? $license_data->error : $license_data->license,
					$license_data
				);
				
				add_settings_error( $this->prefix, '_license_key', $message, 'error ' . $this->prefix . '-notice' );
				
			}
			
			$license_validity = isset( $license_data->license ) ? $license_data->license : 'invalid';
			
			set_transient( $this->prefix . '_license_validity', $license_validity, DAY_IN_SECONDS );
			
			$this->license_validity = $license_validity;
			
			return $license_validity;
			
		}
		
		/**
		 * Gets the License Status from the Database
		 * 
		 * @access		private
		 * @since		1.0.0
		 * @return		string License Status
		 */
		private function retrieve_license_status() {
			
			if ( ! ( $license_status = $this->license_status = get_option( $this->prefix . '_license_status' ) ) ) {
				
				return 'invalid';
				
			}
			
			if ( get_transient( $this->prefix . '_license_validity' ) !== 'valid' &&
				$this->check_license_validity() !== 'valid' ) {
				
				return 'invalid';
				
			}
			
			return 'valid';
			
		}
		
		/**
		 * Gets the License Key from the Database
		 * 
		 * @access		private
		 * @since		1.0.0
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
		 * @since		1.0.0
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
		 * Sets up Plugin Updates as well as place a License Nag within the Plugins Table
		 * 
		 * @access		public
		 * @since		1.0.0
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
		 * @since		1.0.0
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
		 * Activates the License Key
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function activate_license() {
			
			if ( ! isset( $_REQUEST[ $this->prefix . '_license'] ) ||
				! wp_verify_nonce( $_REQUEST[ $this->prefix . '_license'], $this->prefix . '_license' )
			   ) {
				return;
			}
			
			$key = $this->get_license_key();
			
			$plugin_data = $this->plugin_data;
			
			$api_params = array(
				'edd_action' => 'activate_license',
				'license' => $key,
				'item_name' => urlencode( $plugin_data['Name'] ),
				'url' => home_url()
			);
			
			$response = wp_remote_get(
				add_query_arg( $api_params, $this->store_url ),
				array(
					'timeout' => 10,
					'sslverify' => false,
				)
			);
			
			if ( is_wp_error( $response ) ) {
				return false;
			}
			
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			$status = isset( $license_data->license ) ? $license_data->license : 'invalid';
			
			set_transient( $this->prefix . '_license_status', $license_data->license, HOUR_IN_SECONDS );
			
			if ( $license_data->success === false ) {
				
				$message = $this->get_license_error_message(
					$license_data->error,
					$license_data
				);
				
				add_settings_error(
					$this->settings_error,
					'',
					$message,
					'error ' . $this->prefix . '-notice'
				);
				
			}
			else {
				
				add_settings_error(
					$this->settings_error,
					'',
					sprintf( __( '%s license successfully activated.', 'rbp-support' ), $this->plugin_data['Name'] ),
					'updated ' . $this->prefix . '-notice'
				);
				
				update_option( $this->prefix . '_license_key', $key );
				update_option( $this->prefix . '_license_status', $license_data->license );
				set_transient( $this->prefix . '_license_validity', 'valid', DAY_IN_SECONDS );
				
			}
			
		}
		
		/**
		 * Deletes the License Key
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function delete_license() {
			
			delete_option( $this->prefix . '_license_key' );
			
		}
		
		/**
		 * Deactivates the License Key
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function deactivate_license() {
			
			if ( ! isset( $_REQUEST[ $this->prefix . '_license' ] ) ||
				! wp_verify_nonce( $_REQUEST[ $this->prefix . '_license' ], $this->prefix . '_license' )
			   ) {
				return;
			}
			
			$key = $this->get_license_key();
			
			$plugin_data = $this->plugin_data;
			
			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $key,
				'item_name'  => $plugin_data['Name'],
				'url'        => home_url()
			);
			
			// Call the custom API.
			$response = wp_remote_get(
				add_query_arg( $api_params, $this->store_url ),
				array(
					'timeout'   => 10,
					'sslverify' => false
				)
			);
			
			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				return false;
			}
			
			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
			if ( $license_data->success === false ) {
				
				$message = __( 'Error: could not deactivate the license', 'rbp-support' );
				
				add_settings_error(
					$this->settings_error,
					'',
					$message,
					'error ' . $this->prefix . '-notice'
				);
				
			}
			else {
				
				add_settings_error(
					$this->settings_error,
					'',
					sprintf( __( '%s license successfully deactivated.', 'rbp-support' ), $this->plugin_data['Name'] ),
					'updated ' . $this->prefix . '-notice'
				);
				
				delete_option( $this->prefix . '_license_status' );
				delete_transient( $this->prefix . '_license_validity' );
				
			}
			
		}
		
		/**
		 * Grabs the appropriate Error Message for each License Error
		 * 
		 * @param		string $error_code   Type of Error
		 * @param		object $license_data License Data response object from EDD API
		 * @param		array  $plugin_data  get_plugin_data( <your_plugin_file>, false );
		 *                                                                         
		 * @access		public
		 * @since		1.0.0
		 * @return		string Error Message
		 */
		public function get_license_error_message( $error_code, $license_data ) {
			
			switch ( $error_code ) {
					
				case 'expired':
					$message = sprintf(
						__( 'Your license key expired on %s.', 'rbp-support' ),
						date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
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
					$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'rbp-support' ), $this->plugin_data['Name'] );
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
		
		/**
		 * Create Debug File to attach to the Email. This is a base64 buffer.
		 * This has an obscene amount of Filters in it for flexibility. While there is no space between some, I figure
		 *                                      
		 * @access		public
		 * @since		1.0.0
		 * @return		string base64 buffer
		 */
		public function debug_file() {

			$output = "= RBP_Support v" . $this->version . " =\n\n";
			
			/**
			 * Allows text to be included directly after the RBP_Support version. Sorry, no one gets to easily place data before it :P
			 * Granted, this is a Filter. Adding before it is trivial if you must. Just please don't.
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_start', $output );
			
			/**
			 * Allows text to be included directly before the Installed Plugins Header
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_installed_plugins_header', $output );

			// Installed Plugins
			$installed_plugins = get_plugins();

			if ( $installed_plugins ) {

				$output .= "= Installed Plugins =\n";
				
				/**
				 * Allows text to be included directly before the Installed Plugins List
				 * 
				 * @param		string Debug File Output
				 *                       
				 * @since		{{VERSION}}
				 * @return		string Debug File Output
				 */
				$output = apply_filters( $this->prefix . '_debug_file_before_installed_plugins_list', $output );

				foreach ( $installed_plugins as $id => $plugin ) {
					
					/**
					 * Allows additional information about a Installed Plugin to be inserted before it in the Debug File
					 * 
					 * @param		string Debug File Output
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		{{VERSION}}
					 * @return		string Debug File Output
					 */
					$output = apply_filters( $this->prefix . '_debug_file_before_installed_plugin', $output, $plugin, $id );

					$output .= "$plugin[Name]: $plugin[Version]\n";
					
					/**
					 * Allows additional information about a Installed Plugin to be inserted after it in the Debug File
					 * 
					 * @param		string Debug File Output
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		{{VERSION}}
					 * @return		string Debug File Output
					 */
					$output = apply_filters( $this->prefix . '_debug_file_after_installed_plugin', $output, $plugin, $id );
					
				}
				
			}
			
			/**
			 * Allows text to be included directly after the Installed Plugins List
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_after_installed_plugins_list', $output );
			
			/**
			 * Allows text to be included directly before the Active Plugins Header
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_active_plugins_header', $output );

			// Active Plugins
			$active_plugins = get_option( 'active_plugins' );

			if ( $active_plugins ) {

				$output .= "\n= Active Plugins =\n";
				
				$output = apply_filters( $this->prefix . '_debug_file_before_active_plugins_list', $output );

				foreach ( $active_plugins as $id ) {

					$plugin = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $id );

					$output .= "$plugin[Name]: $plugin[Version]\n";
					
				}
				
				/**
				 * Allows text to be included directly before the Active Plugins List
				 * 
				 * @param		string Debug File Output
				 *                       
				 * @since		{{VERSION}}
				 * @return		string Debug File Output
				 */
				$output = apply_filters( $this->prefix . '_debug_file_after_active_plugins_list', $output );
				
			}
			
			/**
			 * Allows text to be included directly after the Active Plugins List
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_after_active_plugins_list', $output );
			
			/**
			 * Allows text to be included directly before the Active Theme Header
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_active_theme_header', $output );

			// Theme
			$output .= "\n= Active Theme =\n";
			
			/**
			 * Allows text to be included directly before the Active Theme Data
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_active_theme_data', $output );

			$theme = wp_get_theme();

			$output .= "Name: " . $theme->get( 'Name' ) . "\n";
			$output .= "Version: " . $theme->get( 'Version' ) . "\n";
			$output .= "Theme URI: " . $theme->get( 'ThemeURI' ) . "\n";
			$output .= "Author URI: " . $theme->get( 'AuthorURI' ) . "\n";

			$template = $theme->get( 'Template' );

			if ( $template ) {

				$output .= "Parent Theme: $template\n";
				
			}
			
			/**
			 * Allows text to be included directly after the Active Theme Data
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_after_active_theme_data', $output );
			
			/**
			 * Allows text to be included directly before the PHP Info Header
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_php_info_header', $output );

			// PHP
			$output .= "\n= PHP Info =\n";
			
			/**
			 * Allows text to be included directly before the PHP Info List
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_before_php_info_list', $output );
			
			$output .= "Version: " . phpversion();
			
			/**
			 * Allows text to be included directly after the PHP Info List
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_after_php_info_list', $output );
			
			/**
			 * Allows text to be included at the end of the Debug File
			 * 
			 * @param		string Debug File Output
			 *                       
			 * @since		{{VERSION}}
			 * @return		string Debug File Output
			 */
			$output = apply_filters( $this->prefix . '_debug_file_end', $output );

			return $output;
			
		}
		
		/**
		 * Send a Support Email via Ajax
		 * This is done via Ajax to allow more flexibility in DOM structure. Depending on the plugin, you may not have much freedom in how or where the Support Form is placed. Using Ajax helps alleviate any conflicts where you may potentially submit the wrong Form
		 * 
		 * @access		public
		 * @since		1.0.0
		 * @return		void
		 */
		public function send_support_email() {
			
			if ( ! isset( $_POST[ $this->prefix . '_support_nonce' ] ) ||
				! wp_verify_nonce( $_POST[ $this->prefix . '_support_nonce' ], $this->prefix . '_send_support_email' ) ||
				! current_user_can( 'manage_options' ) ) {

				return;
				
			}

			/**
			 * Data to be sent in the support email.
			 * 
			 * @since		1.0.0
			 */
			$data = apply_filters( $this->prefix . '_support_email_data', array(
				'subject' => esc_attr( $_POST['support_subject'] ),
				'message' => esc_attr( $_POST['support_message'] ),
				'license_data' => $this->get_license_data(),
			), $_POST );

			$license_data = $data['license_data'];
			$subject = trim( $data['subject'] );
			$message = trim( $data['message'] );

			if ( ! $license_data ||
				empty( $subject ) ||
				empty( $message ) ) {

				$result = false;

			}
			else {

				// Grab Debug Info as a String
				$debug_file = $this->debug_file();
				
				// Prepend Message with RBP_Support Version and Plugin Name
				$message_prefix = "Sent via RBP_Support v" . $this->version . "\n" . 
					"Plugin: " . $this->plugin_data['Name'] . "\n\n";
				
				/**
				 * Prepend some information before the Message Content
				 * This allows HelpScout to auto-tag and auto-assign Tickets
				 * 
				 * @param		string Debug File Output
				 *                       
				 * @since		{{VERSION}}
				 * @return		string Debug File Output
				 */
				$message_prefix = apply_filters( $this->prefix . '_support_email_before_message', $message_prefix );
				
				$message = $message_prefix . $message;
				
				// This is where things start to look confusing.
				// Emails with Attachments are Multipart with a "Boundary" between each part. This boundary goes in the Message Body.
				// We generate a Boundary for the message (Every example I saw has a semi-random boundary) and place that between everything.
				// Each "Part" has its own Headers, but those Headers go within the Message Body
				
				// Generate a semi-random boundary
				$semi_rand = md5( time() );
				$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x"; 
				
				$message_multipart = "--{$mime_boundary}\n";
				
				// Wrap the Message in a boundary with its own Headers
				$message_multipart .= "Content-Type: text/plain; charset=\"iso-8859-1\"\n";
				$message_multipart .= "Content-Transfer-Encoding: 7bit\n\n";
				$message_multipart .= $message . "\n\n";
				
				$message_multipart .= "--{$mime_boundary}\n";
				
				// Wrap the Attachment Buffer in a boundary with its own Headers
				$message_multipart .= "Content-Type: text/plain\n";
				$message_multipart .= " charset=UTF-8\n";
				$message_multipart .= " name=\"support_site_info.txt\"\n";
				$message_multipart .= "Content-Transfer-Encoding: base64\n";
				$message_multipart .= "Content-Disposition: attachment;\n";
				$message_multipart .= " filename=\"support_site_info.txt\";\n\n\n";
				$message_multipart .= chunk_split( base64_encode( $debug_file ) ) . "\n\n";
				
				$message_multipart .= "--{$mime_boundary}--";

				$result = wp_mail(
					'support@realbigplugins.com',
					$subject,
					$message_multipart,
					array(
						"From: $license_data->customer_name <$license_data->customer_email>",
						"Content-Type: multipart/mixed boundary=\"{$mime_boundary}\"", // Here we define the Boundary within the primary Email Header
					),
					array(
					)
				);
					
				add_settings_error(
					$this->settings_error,
					'',
					$result ? __( 'Support message succesfully sent!', 'rbp-support' ) :
						__( 'Could not send support message.', 'rbp-support' ),
					$result ? 'updated' : 'error'
				);
				
			}
			
		}
		
	}
	
}