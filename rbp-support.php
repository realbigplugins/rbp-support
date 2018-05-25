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

/**
 * This Constant gets defined the first time RBP_Support gets loaded
 * This is useful in the event multiple Plugins are utilizing it on a certain site. If a plugin loads an outdated version, all other Plugins will use that outdated version. This can assist in pinning down the source of an outdated version.
 * 
 * @since		1.0.2
 * 
 * @var			string
 */
if ( ! defined( 'RBP_SUPPORT_LOADED_FROM' ) ) {

	define( 'RBP_SUPPORT_LOADED_FROM', __FILE__ );

}

if ( ! class_exists( 'RBP_Support' ) ) {
	
	class RBP_Support {
		
		/**
		 * Holds the Version Number of RBP_Support.
		 * This is used in the Support Email to help us know which version of RBP_Support is being used in the event multiple Plugins are utilizing it on a certain site. If a plugin loads an outdated version, all other Plugins will use that outdated version.
		 * See https://github.com/realbigplugins/rbp-support/issues/5
		 *
		 * @since		1.0.1
		 *
		 * @var			string
		 */
		private $version = '1.2.1';
		
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
		 * The stored Beta Status for the Plugin
		 *
		 * @since		1.1.5
		 *
		 * @var			boolean
		 */
		private $beta_status;
		
		/**
		 * The version of the loaded copy of EDD_SL_Plugin_Updater
		 *
		 * @since		1.2.0
		 *
		 * @var			string
		 */
		private $edd_sl_plugin_updater_version;
		
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
		 * Stores the localization for each String in use by RBP Support
		 * If no localization for a given String was provided in the Constructor, then it will default to one included in RBP Support
		 * Using the default RBP Support localizations is not recommended as it will be more difficult/confusing for any volunteers translating your Plugin
		 * 
		 * @since		1.1.0
		 * 
		 * @var			array
		 */
		public $l10n;
		
		/**
		 * RBP_Support constructor.
		 * 
		 * @param		string $plugin_file Path to the Plugin File. REQUIRED
		 * @param		array  $l10n        Localization for Strings within RBP Support. This also allows you to alter text strings without the need to override templates.
		 *                                                                                                                           
		 * @since		1.0.0
		 */
		function __construct( $plugin_file = null, $l10n = array() ) {
			
			$this->load_textdomain();
			
			if ( $plugin_file == null || 
			   ! is_string( $plugin_file ) ) {
				throw new Exception( __( 'Missing Plugin File Path in RBP_Support Constructor', 'rbp-support' ) );
			}

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
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
			 * @since		1.0.1
			 * @return		string
			 */
			$this->prefix = apply_filters( 'rbp_support_prefix', $this->prefix );
			
			// Rip out the EDD SL Plugin Updater version and store it
			$this->edd_sl_plugin_updater_version = $this->retrieve_edd_sl_plugin_updater_version();
			
			/**
			 * Allow overriding the Store URL for your plugin if necessary
			 * 
			 * @since		1.1.0
			 * @return		string
			 */
			$this->store_url = apply_filters( $this->prefix . '_store_url', $this->store_url );
			
			/**
			 * Allows the "Setting" for Settings Errors to be overriden
			 * EDD in particular requires the "Setting" to be 'edd-notices', so this can be very useful
			 *
			 * @since		1.0.0
			 * @return		string
			 */
			$this->settings_error = apply_filters( $this->prefix . '_settings_error', $this->prefix . '_support' );
			
			$this->license_key = $this->retrieve_license_key();
			
			$this->beta_status = $this->retrieve_beta_status();
			
			/**
			 * Takes passed in localization for Strings and uses those where applicable rather than the "built-in" ones
			 * This is important in the event that someone is translating your plugin. If they translate your plugin but then the Support/Licensing stuff is still in English, it would be confusing to them
			 * 
			 * @since		1.1.0
			 */ 
			$this->l10n = $this->wp_parse_args_recursive( $l10n, array(
				'support_form' => array(
					'enabled' => array(
						'title' => _x( 'Need some help with %s?', '%s is the Plugin Name', 'rbp-support' ),
						'subject_label' => __( 'Subject', 'rbp-support' ),
						'message_label' => __( 'Message', 'rbp-support' ),
						'send_button' => __( 'Send', 'rbp-support' ),
						'subscribe_text' => _x( 'We make other cool plugins and share updates and special offers to anyone who %ssubscribes here%s.', 'Both %s are used to place HTML for the <a> in the message', 'rbp-support' ),
						'validationError' => _x( 'This field is required', 'Only used by legacy browsers for JavaScript Form Validation', 'rbp-support' ),
						'success' => __( 'Support message succesfully sent!', 'rbp-support' ),
						'error' => __( 'Could not send support message.', 'rbp-support' ),
					),
					'disabled' => array(
						'title' => _x( 'Need some help with %s?', '%s is the Plugin Name', 'rbp-support' ),
						'disabled_message' => __( 'Premium support is disabled. Please register your product and activate your license for this website to enable.', 'rbp-support' )
					),
				),
				'licensing_fields' => array(
					'title' => _x( '%s License', '%s is the Plugin Name', 'rbp-support' ),
					'deactivate_button' => __( 'Deactivate', 'rbp-support' ),
					'activate_button' => __( 'Activate', 'rbp-support' ),
					'delete_deactivate_button' => __( 'Delete and Deactivate', 'rbp-support' ),
					'delete_button' => __( 'Delete', 'rbp-support' ),
					'license_active_label' => __( 'License Active', 'rbp-support' ),
					'license_inactive_label' => __( 'License Inactive', 'rbp-support' ),
					'save_activate_button' => __( 'Save and Activate', 'rbp-support' ),
				),
				'license_nag' => array(
					'register_message' => _x( 'Register your copy of %s now to receive automatic updates and support.', '%s is the Plugin Name', 'rbp-support' ),
					'purchase_message' => _x( 'If you do not have a license key, you can %1$spurchase one%2$s.', 'Both %s are used to place HTML for the <a> in the message', 'rbp-support' ),
				),
				'license_activation' => _x( '%s license successfully activated.', '%s is the Plugin Name', 'rbp-support' ),
				'license_deletion' => _x( '%s license successfully deleted.', '%s is the Plugin Name', 'rbp-support' ),
				'license_deactivation' => array(
					'error' => _x( 'Error: could not deactivate the license for %s', '%s is the Plugin Name', 'rbp-support' ),
					'success' => _x( '%s license successfully deactivated.', '%s is the Plugin Name', 'rbp-support' ),
				),
				'license_error_messages' => array(
					'expired' => _x( 'Your license key expired on %s.', '%s is a localized timestamp', 'rbp-support' ),
					'revoked' => __( 'Your license key has been disabled.', 'rbp-support' ),
					'missing' => __( 'Invalid license.', 'rbp-support' ),
					'site_inactive' => __( 'Your license is not active for this URL.', 'rbp-support' ),
					'item_name_mismatch' => _x( 'This appears to be an invalid license key for %s.', '%s is the Plugin Name', 'rbp-support' ),
					'no_activations_left' => __( 'Your license key has reached its activation limit.', 'rbp-support' ),
					'default' => __( 'An error occurred, please try again.', 'rbp-support' ),
				),
				'beta_checkbox' => array(
					'label' => __( 'Enable Beta Releases', 'rbp-support' ),
					'disclaimer' => __( 'Beta Releases should not be considered as Stable. Enabling this on your Production Site is done at your own risk.', 'rbp-support' ),
					'enabled_message' => _x( 'Beta Releases for %s enabled.', '%s is the Plugin Name', 'rbp-support' ),
					'disabled_message' => _x( 'Beta Releases for %s disabled.', '%s is the Plugin Name', 'rbp-support' ),
				),
			) );
			
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
			
			if ( isset( $_REQUEST[ $this->prefix . '_enable_beta' ] ) && 
					  ! isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ) {
				
				add_action( 'admin_init', array( $this, 'save_beta_status' ) );
				
			}
			else if ( $this->get_beta_status() &&
					 ! isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ) {
				
				add_action( 'admin_init', array( $this, 'delete_beta_status' ) );
				
			}
			
			if ( isset( $_REQUEST[ $this->prefix . '_rbp_support_submit' ] ) ) {
				
				add_action( 'phpmailer_init', array( $this, 'add_debug_file_to_email' ) );
				
				add_action( 'admin_init', array( $this, 'send_support_email' ) );
				
			}
			
			// Ensures all License Data is allowed to fully clear out from the database
			if ( ! isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ||
				   strpos( $_REQUEST[ $this->prefix . '_license_action' ], 'delete' ) === false ) {
			
				// Set up plugin updates
				add_action( 'admin_init', array( $this, 'setup_plugin_updates' ) );

				// Check License Validity
				add_action( 'admin_init', array( $this, 'get_license_validity') );
				
			}
			
			// Scripts are registered/localized, but it is on the Plugin Developer to enqueue them
			add_action( 'admin_init', array( $this, 'register_scripts' ) );
			
		}
		
		/**
		 * This returns the version of the RBP_Support Class
		 * This is helpful for debugging as the version you included in your Plugin may not necessarily be the one being loaded if multiple Plugins are utilizing it
		 * 
		 * @access		public
		 * @since		1.0.2
		 * @return		string Version Number
		 */
		public function get_version() {
			
			return $this->version;
			
		}
		
		/**
		 * Returns the File Path to the loaded copy of RBP_Support
		 * This is useful in the event multiple Plugins are utilizing it on a certain site. If a plugin loads an outdated version, all other Plugins will use that outdated version. This can assist in pinning down the source of an outdated version.
		 * 
		 * @access		public
		 * @since		1.0.2
		 * @return		string File Path to loaded copy of RBP_Support
		 */
		public function get_file_path() {
			
			if ( ! defined( 'RBP_SUPPORT_LOADED_FROM' ) ) {
				return __( 'The RBP_SUPPORT_LOADED_FROM Constant is undefined. This should never happen.', 'rbp-support' );
			}
			
			return RBP_SUPPORT_LOADED_FROM;
			
		}
		
		/**
		 * We are forcibly loading the Class into a Namespace, so we do not need to worry about conflicts with other Plugins
		 * As a result, we arguably know that we're always running at least v1.6.14 of EDD_SL_Plugin_Updater since RBP Support has never been put into the wild with a lower version
		 * However, this helps us know whether we are running the version we expect or higher. It can potentially be helpful in the future
		 * 
		 * @access		public
		 * @since		1.2.0
		 * @return		string EDD_SL_Plugin_Updater Class Version
		 */
		public function get_edd_sl_plugin_updater_version() {
			
			if ( ! $this->edd_sl_plugin_updater_version ) {
				
				$this->edd_sl_plugin_updater_version = $this->retrieve_edd_sl_plugin_updater_version();
				
			}
			
			return $this->edd_sl_plugin_updater_version;
			
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
			$mofile_global  = WP_LANG_DIR . '/rbp-support/' . $mofile;

			if ( is_file( $mofile_global ) ) {
				// Look in global /wp-content/languages/rbp-support/ folder
				// This way translations can be overridden via the Theme/Child Theme
				load_textdomain( 'rbp-support', $mofile_global );
			}
			else if ( is_file( $mofile_local ) ) {
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
				
				$l10n = $this->l10n['support_form']['enabled'];
				
				if ( is_file( $this->plugin_dir . 'rbp-support/sidebar-support.php' ) ) {
					include $this->plugin_dir . 'rbp-support/sidebar-support.php';
				}
				else {
					include __DIR__ . '/views/sidebar-support.php';
				}
				
			}
			else {
				
				$l10n = $this->l10n['support_form']['disabled'];
				
				if ( is_file( $this->plugin_dir . 'rbp-support/sidebar-support-disabled.php' ) ) {
					include $this->plugin_dir . 'rbp-support/sidebar-support-disabled.php';
				}
				else {
					include __DIR__ . '/views/sidebar-support-disabled.php';
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
			
			$license_key = '';
			$plugin_name = $this->plugin_data['Name'];
			
			$l10n = $this->l10n['licensing_fields'];
			
			// Only grab the License Key to output on the Form if we haven't just deleted it
			if ( ! isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ||
				   strpos( $_REQUEST[ $this->prefix . '_license_action' ], 'delete' ) === false ) {
				$license_key = $this->get_license_key();
			}
				
			if ( is_file( $this->plugin_dir . 'rbp-support/licensing-fields.php' ) ) {
				include $this->plugin_dir . 'rbp-support/licensing-fields.php';
			}
			else {
				include __DIR__ . '/views/licensing-fields.php';
			}
			
		}
		
		/**
		 * Outputs the Beta Enabler Checkbox
		 * 
		 * @access		public
		 * @since		1.1.5
		 * @return		void
		 */
		public function beta_checkbox() {
			
			// The loaded version of EDD_SL_Plugin_Updater does not support Betas, bail
			if ( version_compare( $this->get_edd_sl_plugin_updater_version(), '1.6.9' ) == -1 ) return;
			
			// Makes the variable make more sense within the context of the HTML
			$plugin_prefix = $this->prefix;
			$license_status = $this->get_license_status();
			$beta_enabled = $this->get_beta_status();
			
			$l10n = $this->l10n['beta_checkbox'];
				
			if ( file_exists( $this->plugin_dir . 'rbp-support/beta-checkbox.php' ) ) {
				include $this->plugin_dir . 'rbp-support/beta-checkbox.php';
			}
			else {
				include __DIR__ . '/views/beta-checkbox.php';
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
			
			wp_enqueue_script( $this->prefix . '_licensing' );
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

			return (array) $this->license_data;
		}
		
		/**
		 * Getter method for Beta Status
		 * 
		 * @access		public
		 * @since		1.1.5
		 * @return		boolean Beta Status
		 */
		public function get_beta_status() { 
			
			if ( ! $this->beta_status ) {
				
				$this->beta_status = $this->retrieve_beta_status();
				
			}
			
			return $this->beta_status;
		
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
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
				true
			);
			
			wp_register_style(
				$this->prefix . '_form',
				plugins_url( '/assets/css/form.css', __FILE__ ),
				array(),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
				'all'
			);
			
			wp_register_script(
				$this->prefix . '_licensing',
				plugins_url( '/assets/js/licensing.js', __FILE__ ),
				array( 'jquery' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
				true
			);
			
			wp_register_style(
				$this->prefix . '_licensing',
				plugins_url( '/assets/css/licensing.css', __FILE__ ),
				array(),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
				'all'
			);
			
			wp_localize_script( 
				$this->prefix . '_form',
				$this->prefix . '_support_form',
				apply_filters( $this->prefix . '_localize_form_script', wp_parse_args( array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				), $this->l10n['support_form']['enabled'] ) )
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
			
			/**
			 * Allow using Download ID for License interactions if desired
			 * 
			 * @since		1.0.7
			 * @return		integer|boolean Download ID, false to use Download Name (default)
			 */
			$item_id = apply_filters( $this->prefix . '_download_id', false );
			
			if ( $item_id ) {
				
				$api_params['item_id'] = (int) $item_id;
				unset( $api_params['item_name'] );
				
			}
			
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
			
			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );
			
			set_transient( $this->prefix . '_license_data', $license_data, DAY_IN_SECONDS );
			
			if ( ! $license_data['success'] ||
				$license_data['license'] !== 'valid' ) {
				
				$message = $this->get_license_error_message(
					! $license_data['success'] && isset( $license_data['error'] ) ? $license_data['error'] : $license_data['license'],
					$license_data
				);
				
				// Don't throw up an error. The License Action already has
				if ( ! isset( $_REQUEST[ $this->prefix . '_license_action' ] ) ) {
					add_settings_error(
						$this->settings_error,
						'',
						$message,
						'error ' . $this->prefix . '-notice'
					);
				}
				
			}
			
			$license_validity = isset( $license_data['license'] ) ? $license_data['license'] : 'invalid';
			
			set_transient( $this->prefix . '_license_validity', $license_validity, DAY_IN_SECONDS );
			
			$this->license_validity = $license_validity;
			
			return $license_validity;
			
		}
		
		/**
		 * Grabs the EDD_SL_Plugin_Updater Class version
		 * There is no "Version" Property, so we have to rip it out of the PHP Docblock itself
		 * 
		 * @access		private
		 * @since		1.2.0
		 * @return		string EDD_SL_Plugin_Updater Class Version
		 */
		private function retrieve_edd_sl_plugin_updater_version() {
			
			// Holds the PHP file contents of the included version of the Class
			// Since we are eval-ing the code in order to force a Namespace, we cannot find the file path from the Class itself, so we must hardcode it
			$plugin_updater = file_get_contents( __DIR__ . '/includes/EDD-License-handler/EDD_SL_Plugin_Updater.php' );
			
			// Search file for @version <version_number>
			preg_match_all( '/@version\s([\d|.]+)/i', $plugin_updater, $matches );
			
			// We want our Capture Group for the first Match
			$version = $matches[1][0];
			
			return $version;
			
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
		 * Gets the Beta Status from the Database
		 * 
		 * @access		private
		 * @since		1.1.5
		 * @return		boolean Beta Status
		 */
		private function retrieve_beta_status() {
				
			if ( isset( $_REQUEST[ $this->prefix . '_enable_beta' ] ) ) {
				$this->beta_status = (bool) $_REQUEST[ $this->prefix . '_enable_beta' ];
			}
			else {
				$this->beta_status = (bool) get_option( $this->prefix . '_enable_beta' );
			}
			
			return $this->beta_status;
			
		}
		
		/**
		 * Gets License Data from Database/Remote Store
		 * 
		 * @access		private
		 * @since		1.0.0
		 * @return 		array|bool License Data or FALSE on error.
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
			
			/**
			 * This forces the EDD_SL_Plugin_Updater Class into a Namespace, thereby enabling us to never have to worry about other Plugins including an older/newer version than what we expect
			 * Normally to accomplish this we would need to manually rename the Class or manually add the Namespace and thereby maintain our own copy of the Class in our Version Control instead of just pulling in changes
			 * While hacky, this prevents us from needing to ensure that as the Class Updates we keep our modified Class Name or added Namespace from being overwritten
			 * 
			 * @since		1.2.0
			 */
			if ( ! class_exists( 'RBP_Support\EDD_SL_Plugin_Updater' ) ) {
				eval( 'namespace RBP_Support { ?>' . file_get_contents( __DIR__ . '/includes/EDD-License-handler/EDD_SL_Plugin_Updater.php' ) . '}' );
			}
			
			if ( is_admin() ) {
				
				$api_params = array(
					'item_name' => $this->plugin_data['Name'],
					'version'   => $this->plugin_data['Version'],
					'license'   => $this->license_key,
					'author'    => $this->plugin_data['Author'],
					'beta'		=> $this->get_beta_status(),
				);
				
				/**
				 * Allow using Download ID for License interactions if desired
				 * 
				 * @since		1.0.7
				 * @return		integer|boolean Download ID, false to use Download Name (default)
				 */
				$item_id = apply_filters( $this->prefix . '_download_id', false );

				if ( $item_id ) {

					$api_params['item_id'] = (int) $item_id;
					unset( $api_params['item_name'] );

				}
				
				$license = new RBP_Support\EDD_SL_Plugin_Updater(
					$this->store_url,
					$this->plugin_file,
					$api_params
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
			
			$l10n = $this->l10n['license_nag'];
			
			?>

			<tr class="plugin-update-tr">
				<td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
					<div class="update-message">
						<?php

							/**
							 * We can't know or predict the URL of your Plugin's Settings/Licensing page
							 * This filter will allow you to include a link to it if you want
							 * 
							 * @param		string Register Plugin Message
							 * 
							 * @since		1.0.0
							 * @return		string Register Plugin Message
							 */
							$register_message = apply_filters( $this->prefix . '_register_message', sprintf(
								$l10n['register_message'],
								$this->plugin_data['Name']
							) );
			
							echo $register_message;
			
							if ( ! $this->get_license_key() && 
							   ! empty( $this->plugin_data['PluginURI'] ) ) {
								printf(
									' ' . $l10n['purchase_message'],
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
			
			update_option( $this->prefix . '_license_key', $key );
			
			$plugin_data = $this->plugin_data;
			
			$api_params = array(
				'edd_action' => 'activate_license',
				'license' => $key,
				'item_name' => urlencode( $plugin_data['Name'] ),
				'url' => home_url()
			);
			
			/**
			 * Allow using Download ID for License interactions if desired
			 * 
			 * @since		1.0.7
			 * @return		integer|boolean Download ID, false to use Download Name (default)
			 */
			$item_id = apply_filters( $this->prefix . '_download_id', false );
			
			if ( $item_id ) {
				
				$api_params['item_id'] = (int) $item_id;
				unset( $api_params['item_name'] );
				
			}
			
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
				
				$l10n = $this->l10n['license_activation'];
				
				add_settings_error(
					$this->settings_error,
					'',
					sprintf( $l10n, $this->plugin_data['Name'] ),
					'updated ' . $this->prefix . '-notice'
				);
				
				$status = isset( $license_data->license ) ? $license_data->license : 'invalid';
				
				update_option( $this->prefix . '_license_status', $status );
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
			delete_option( $this->prefix . '_license_status' );
			delete_transient( $this->prefix . '_license_data' );
			delete_transient( $this->prefix . '_license_validity' );
			
			if ( isset( $_REQUEST[ $this->prefix . '_license_action' ] ) && 
			   strpos( $_REQUEST[ $this->prefix . '_license_action' ], 'deactivate' ) === false ) {
				
				$l10n = $this->l10n['license_deletion'];
				
				add_settings_error(
					$this->settings_error,
					'',
					sprintf( $l10n, $this->plugin_data['Name'] ),
					'updated ' . $this->prefix . '-notice'
				);
				
			}
			
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
			
			/**
			 * Allow using Download ID for License interactions if desired
			 * 
			 * @since		1.0.7
			 * @return		integer|boolean Download ID, false to use Download Name (default)
			 */
			$item_id = apply_filters( $this->prefix . '_download_id', false );
			
			if ( $item_id ) {
				
				$api_params['item_id'] = (int) $item_id;
				unset( $api_params['item_name'] );
				
			}
			
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
			
			$l10n = $this->l10n['license_deactivation'];
			
			if ( $license_data->success === false ) {
				
				$message = $l10n['error'];
				
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
					sprintf( $l10n['success'], $this->plugin_data['Name'] ),
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
		 * @param		array $license_data License Data response object from EDD API
		 * @param		array  $plugin_data  get_plugin_data( <your_plugin_file>, false );
		 *                                                                         
		 * @access		public
		 * @since		1.0.0
		 * @return		string Error Message
		 */
		public function get_license_error_message( $error_code, $license_data ) {
			
			$l10n = $this->l10n['license_error_messages'];
			
			switch ( $error_code ) {
					
				case 'expired':
					$message = sprintf(
						$l10n['expired'],
						date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $license_data['expires'], current_time( 'timestamp' ) ) )
					);
					break;
				case 'revoked':
					$message = $l10n['revoked'];
					break;
				case 'missing':
				case 'invalid':
					$message = $l10n['missing'];
					break;
				case 'site_inactive':
					$message = $l10n['site_inactive'];
					break;
				case 'item_name_mismatch':
					$message = sprintf( $l10n['item_name_mismatch'], $this->plugin_data['Name'] );
					break;
				case 'no_activations_left':
					$message = $l10n['no_activations_left'];
					break;
				default:
					$message = $l10n['default'];
					break;
					
			}
			
			return $message;
			
		}
		
		/**
		 * Save the Beta Status when enabled
		 * 
		 * @access		public
		 * @since		1.1.5
		 * @return		void
		 */
		public function save_beta_status() {
			
			if ( ! isset( $_REQUEST[ $this->prefix . '_beta' ] ) ||
				! wp_verify_nonce( $_REQUEST[ $this->prefix . '_beta' ], $this->prefix . '_beta' )
			   ) {
				return;
			}
			
			update_option( $this->prefix . '_enable_beta', true );
			
			$l10n = $this->l10n['beta_checkbox'];
			
			add_settings_error(
				$this->settings_error,
				'',
				sprintf( $l10n['enabled_message'], $this->plugin_data['Name'] ),
				'updated ' . $this->prefix . '-notice'
			);
			
		}
		
		/**
		 * Delete the Beta Status when disabled
		 * 
		 * @access		public
		 * @since		1.1.5
		 * @return		void
		 */
		public function delete_beta_status() {
			
			if ( ! isset( $_REQUEST[ $this->prefix . '_beta' ] ) ||
				! wp_verify_nonce( $_REQUEST[ $this->prefix . '_beta' ], $this->prefix . '_beta' )
			   ) {
				return;
			}
			
			delete_option( $this->prefix . '_enable_beta' );
			
			// Reset value
			$this->beta_status = false;
			
			$l10n = $this->l10n['beta_checkbox'];
			
			add_settings_error(
				$this->settings_error,
				'',
				sprintf( $l10n['disabled_message'], $this->plugin_data['Name'] ),
				'updated ' . $this->prefix . '-notice'
			);
			
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
			
			ob_start();

			echo "= RBP_Support v" . $this->get_version() . " =\n";
			echo "Loaded from: " . $this->get_file_path() . "\n\n";
			
			/**
			 * Allows text to be included directly after the RBP_Support version. Sorry, no one gets to place data before it :P
			 *      
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_start' );
			
			/**
			 * Allows text to be included directly before the Installed Plugins Header
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_installed_plugins_header' );

			// Installed Plugins
			$installed_plugins = get_plugins();

			if ( $installed_plugins ) {

				echo "= Installed Plugins =\n";
				
				/**
				 * Allows text to be included directly before the Installed Plugins List
				 *                       
				 * @since		1.0.4
				 * @return		void
				 */
				do_action( $this->prefix . '_debug_file_before_installed_plugins_list' );

				foreach ( $installed_plugins as $id => $plugin ) {
					
					/**
					 * Allows additional information about a Installed Plugin to be inserted before it in the Debug File
					 * 
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		1.0.4
					 * @return		void
					 */
					do_action( $this->prefix . '_debug_file_before_installed_plugin', $plugin, $id );

					echo "$plugin[Name]: $plugin[Version]\n";
					
					/**
					 * Allows additional information about a Installed Plugin to be inserted after it in the Debug File
					 * 
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		1.0.4
					 * @return		void
					 */
					do_action( $this->prefix . '_debug_file_after_installed_plugin', $plugin, $id );
					
				}
				
			}
			
			/**
			 * Allows text to be included directly after the Installed Plugins List
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_after_installed_plugins_list' );
			
			/**
			 * Allows text to be included directly before the Active Plugins Header
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_active_plugins_header' );

			// Active Plugins
			$active_plugins = get_option( 'active_plugins' );

			if ( $active_plugins ) {

				echo "\n= Active Plugins =\n";
				
				/**
				 * Allows text to be included directly before the Active Plugins List
				 *                       
				 * @since		1.0.4
				 * @return		void
				 */
				do_action( $this->prefix . '_debug_file_before_active_plugins_list' );

				foreach ( $active_plugins as $id ) {
					
					$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $id;
					$plugin = get_plugin_data( $plugin_path, false );
					
					/**
					 * Allows additional information about an Active Plugin to be inserted before it in the Debug File
					 * 
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		1.0.4
					 * @return		void
					 */
					do_action( $this->prefix . '_debug_file_before_active_plugin', $plugin, $plugin_path );
					
					if ( isset( $plugin['Name'] ) && 
					   isset( $plugin['Version'] ) && 
					   ! empty( $plugin['Name'] ) && 
					   ! empty( $plugin['Version'] ) ) {

						echo "$plugin[Name]: $plugin[Version]\n";
						
					}
					else {
						
						/**
						 * LearnDash shows as two Plugins somehow, with one being at sfwd-lms/sfwd_lms.php and having no Plugin Data outside of what seems to be an incorrect Text Domain
						 * This seems to have something to do with some weird legacy support within LearnDash Core
						 * However, in the off-chance that something similar happens with any other plugins, here's a fallback
						 * 
						 * @since		1.0.4
						 */ 
						echo "No Plugin Data found for Plugin at " . $plugin_path . "\n";
						
					}
					
					/**
					 * Allows additional information about an Active Plugin to be inserted after it in the Debug File
					 * 
					 * @param		array  Plugin Data Array
					 * @param		string Plugin Path
					 *                       
					 * @since		1.0.4
					 * @return		void
					 */
					do_action( $this->prefix . '_debug_file_after_active_plugin', $plugin, $plugin_path );
					
				}
				
				/**
				 * Allows text to be included directly before the Active Plugins List
				 *                       
				 * @since		1.0.4
				 * @return		void
				 */
				do_action( $this->prefix . '_debug_file_after_active_plugins_list' );
				
			}
			
			/**
			 * Allows text to be included directly after the Active Plugins List
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_after_active_plugins_list' );
			
			/**
			 * Allows text to be included directly before the Active Theme Header
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_active_theme_header' );

			// Active Theme
			echo "\n= Active Theme =\n";
			
			/**
			 * Allows text to be included directly before the Active Theme Data
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_active_theme_data' );

			$theme = wp_get_theme();

			echo "Name: " . $theme->get( 'Name' ) . "\n";
			echo "Version: " . $theme->get( 'Version' ) . "\n";
			echo "Theme URI: " . $theme->get( 'ThemeURI' ) . "\n";
			echo "Author URI: " . $theme->get( 'AuthorURI' ) . "\n";

			$template = $theme->get( 'Template' );

			if ( $template ) {

				echo "Parent Theme: $template\n";
				
			}
			
			/**
			 * Allows text to be included directly after the Active Theme Data
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_after_active_theme_data' );
			
			/**
			 * Allows text to be included directly before the WordPress Install Info Header
			 *                       
			 * @since		1.2.0
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_wordpress_info_header' );
			
			// WordPress Info
			echo "\n= WordPress Info =\n";
			
			/**
			 * Allows text to be included directly before the WordPress Install Info List
			 *                       
			 * @since		1.2.0
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_wordpress_info_list' );
			
			echo "Version: " . get_bloginfo( 'version' ) . "\n";
			
			/**
			 * Allows text to be included directly after the WordPress Install Info List
			 *                       
			 * @since		1.2.0
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_after_wordpress_info_list' );
			
			/**
			 * Allows text to be included directly before the PHP Info Header
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_php_info_header' );

			// PHP Info
			echo "\n= PHP Info =\n";
			
			/**
			 * Allows text to be included directly before the PHP Info List
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_before_php_info_list' );
			
			echo "Version: " . phpversion();
			
			/**
			 * Allows text to be included directly after the PHP Info List
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_after_php_info_list' );
			
			/**
			 * Allows text to be included at the end of the Debug File
			 *                       
			 * @since		1.0.4
			 * @return		void
			 */
			do_action( $this->prefix . '_debug_file_end' );
			
			$output = ob_get_clean();

			return $output;
			
		}
		
		/**
		 * Send a Support Email via Ajax
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
			 * @param		array Support Email Data
			 * @param		array $_POST
			 * 
			 * @since		1.0.0
			 * @return		array Support Email Data
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
				
				// Prepend Message with RBP_Support Version and Plugin Name
				$message_prefix = "Sent via RBP_Support v" . $this->get_version() . "\n" . 
					"Plugin: " . $this->plugin_data['Name'] . " v" . $this->plugin_data['Version'] . 
					( ( $this->get_beta_status() ) ? ' (Betas Enabled)' : '' ) . "\n" . 
					"Customer Name: $license_data[customer_name]\n" . 
					"Customer Email: $license_data[customer_email]\n\n";
				
				/**
				 * Prepend some information before the Message Content
				 * This allows HelpScout to auto-tag and auto-assign Tickets
				 * 
				 * @param		string Debug File Output
				 *                       
				 * @since		1.0.1
				 * @return		string Debug File Output
				 */
				$message_prefix = apply_filters( $this->prefix . '_support_email_before_message', $message_prefix );
				
				/**
				 * In the event that per-plugin we'd like to change the mail-to, we can
				 * 
				 * @param		string Email Address
				 *                     
				 * @since		1.1.0
				 * @return		string Email Address
				 */
				$mail_to = apply_filters( $this->prefix . '_support_email_mail_to', 'support@realbigplugins.com' );
				
				$message = $message_prefix . $message;

				$result = wp_mail(
					$mail_to,
					$subject,
					$message,
					array(
						"From: $license_data[customer_name] <$license_data[customer_email]>",
						"X-RBP-SUPPORT: " . $this->get_version(),
					),
					array(
					)
				);
				
				$l10n = $this->l10n['support_form']['enabled'];
					
				add_settings_error(
					$this->settings_error,
					'',
					$result ? $l10n['success'] :
						$l10n['error'],
					$result ? 'updated' : 'error'
				);
				
			}
			
		}
		
		/**
		 * Add the Debug File to the Email in a way that PHPMailer can understand
		 * 
		 * @param		object $phpmailer PHPMailer object passed by reference
		 *                                                      
		 * @access		public
		 * @since		1.0.6
		 * @return		void
		 */
		public function add_debug_file_to_email( &$phpmailer ) {
			
			foreach ( $phpmailer->getCustomHeaders() as $header ) {
				
				if ( $header[0] == 'X-RBP-SUPPORT' ) {
					
					$phpmailer->addStringAttachment( $this->debug_file(), 'support_site_info.txt' );
					
					/**
					 * Allows easy access to the PHPMailer object for our RBP Support Emails on a Per-Plugin Basis
					 * 
					 * @param		object PHPMailer object passed by reference
					 * 
					 * @since		1.0.6
					 * @return		void
					 */
					do_action_ref_array( $this->prefix . '_rbp_support_phpmailer_init', array( &$phpmailer ) );
					
					break;
					
				}
				
			}
			
		}
		
		/**
		 * Basically wp_parse_args(), but it can go multiple levels deep
		 * https://mekshq.com/recursive-wp-parse-args-wordpress-function/
		 * 
		 * @param		array $a Array you're using
		 * @param		array $b Array of Defaults
		 *                           
		 * @access		private
		 * @since		1.1.0
		 * @return		array Array with defaults filled in
		 */
		private function wp_parse_args_recursive( &$a, $b ) {
			
			$a = (array) $a;
			$b = (array) $b;
			
			// Result is pre-filled with Defaults from the start
			$result = $b;
			
			foreach ( $a as $key => &$value ) {
				
				// If $value is an Array and we already have the $key within our $result, start parsing args for $value
				if ( is_array( $value ) && 
				   isset( $result[ $key ] ) ) {
					
					$result[ $key ] = $this->wp_parse_args_recursive( $value, $result[ $key ] );
					
				}
				else {
					
					$result[ $key ] = $value;
					
				}
				
			}
			
			return $result;
			
		}
		
	}
	
}