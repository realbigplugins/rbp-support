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
        private $version = '2.0.1';
        
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
        private $plugin_file;
        
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
        private $plugin_data;
        
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
         * This is the Download ID on the website. This allows for more accurate License interactions by not relying on the Download Title matching the Plugin Name exactly
         *
         * @since   2.0.0
         * 
         * @var     integer
         */
        private $item_id;

        /**
         * Holds a link to the License Activation URI to help direct our users to where they need to enter their License Key
         *
         * @since	2.0.0
         * 
         * @var 	string
         */
        private $license_activation_uri;
        
        /**
         * Stores the localization for each String in use by RBP Support
         * If no localization for a given String was provided in the Constructor, then it will default to one included in RBP Support
         * Using the default RBP Support localizations is not recommended as it will be more difficult/confusing for any volunteers translating your Plugin
         * 
         * @since		1.1.0
         * 
         * @var			array
         */
        private $l10n;

        /**
         * Holds the updater object. This sets up everything necessary to pull updates from our site.
         * 
         * @since   2.0.0
         *
         * @var RBP_Support_Updater
         */
        private $updater_class;

        /**
         * Holds the license key object. This sets up everything necessary to activate, deactivate, and store license keys
         * 
         * @since   2.0.0
         *
         * @var RBP_Support_License_Key
         */
        private $license_key_class;

        /**
         * Holds the support form object. This sets up everything necessary to activate, deactivate, and store license keys
         * 
         * @since   2.0.0
         *
         * @var RBP_Support_Support_Form
         */
        private $support_form_class;
        
        /**
         * RBP_Support constructor.
         * 
         * @param		string $plugin_file 			Path to the Plugin File. REQUIRED
         * @param		string $license_activation_uri	URI to the page where a user would activate their License Key
         * @param		array  $l10n        			Localization for Strings within RBP Support. This also allows you to alter text strings without the need to override templates.
         * 
         * @since		1.0.0
         */
        function __construct( $plugin_file = null, $license_activation_uri = '', $l10n = array() ) {
            
            $this->load_textdomain();
            
            if ( $plugin_file == null || 
               ! is_string( $plugin_file ) ) {
                throw new Exception( __( 'Missing Plugin File Path in RBP_Support Constructor', 'rbp-support' ) );
            }

            if ( $license_activation_uri && is_array( $license_activation_uri ) ) {
                // Help support plugins that may not have updated to the new Constructor format
                $l10n = $license_activation_uri;
                $license_activation_uri = '';
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
            
            /**
             * Allow overriding the Store URL for your plugin if necessary
             * 
             * @since		1.1.0
             * @return		string
             */
            $this->store_url = apply_filters( "{$this->prefix}_store_url", $this->store_url );
            
            /**
             * Allows the "Setting" for Settings Errors to be overriden
             * EDD in particular requires the "Setting" to be 'edd-notices', so this can be very useful
             *
             * @since		1.0.0
             * @return		string
             */
            $this->settings_error = apply_filters( "{$this->prefix}_settings_error", "{$this->prefix}_support" );

            /**
             * Allow using Download ID for License interactions if desired
             * 
             * @since		1.0.7
             * @return		integer|boolean Download ID, false to use Download Name  (default)
             */
            $this->item_id = apply_filters( "{$this->prefix}_download_id", false );

            $this->license_activation_uri = $license_activation_uri;
            
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
                    'expired' => _x( 'Your %s license key expired on %s.', 'The first %s is the Plugin name and the second %s is a localized timestamp', 'rbp-support' ),
                    'revoked' => __( 'Your license key has been disabled.', 'rbp-support' ),
                    'missing' => __( 'Invalid license.', 'rbp-support' ),
                    'site_inactive' => __( 'Your license is not active for this URL.', 'rbp-support' ),
                    'item_name_mismatch' => _x( 'This appears to be an invalid license key for %s.', '%s is the Plugin Name', 'rbp-support' ),
                    'no_activations_left' => __( 'Your license key has reached its activation limit.', 'rbp-support' ),
                    'manage_license_link_text' => __( 'You can manage your license key here.', 'rbp-support' ),
                    'no_connection' => _x( '%s cannot communicate with %s for License Key Validation. Please check your server configuration settings.', '%s is the Plugin Name followed by the Store URL', 'rbp-support' ),
                    'default' => __( 'An error occurred, please try again.', 'rbp-support' ),
                ),
                'beta_checkbox' => array(
                    'label' => __( 'Enable Beta Releases', 'rbp-support' ),
                    'disclaimer' => __( 'Beta Releases should not be considered as Stable. Enabling this on your Production Site is done at your own risk.', 'rbp-support' ),
                    'enabled_message' => _x( 'Beta Releases for %s enabled.', '%s is the Plugin Name', 'rbp-support' ),
                    'disabled_message' => _x( 'Beta Releases for %s disabled.', '%s is the Plugin Name', 'rbp-support' ),
                ),
            ) );

            // Set up the Updater functionality
            require_once trailingslashit( __DIR__ ) . 'core/updater/class-rbp-support-updater.php';
            $this->updater_class = new RBP_Support_Updater( $this );

            // Set up License Key Activation/Deactivation/Storage
            require_once trailingslashit( __DIR__ ) . 'core/license-key/class-rbp-support-license-key.php';
            $this->license_key_class = new RBP_Support_License_Key( $this );

            // Set up Support Form logicStorage
            require_once trailingslashit( __DIR__ ) . 'core/support-form/class-rbp-support-support-form.php';
            $this->support_form_class = new RBP_Support_Support_Form( $this );
            
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
            
            $this->support_form_class->support_form();
            
        }
        
        /**
         * Outputs the Licensing Fields. Call this method within whatever container you like.
         * 
         * @access		public
         * @since		1.0.0
         * @return		void
         */
        public function licensing_fields() {

            $this->license_key_class->licensing_fields();
            
        }
        
        /**
         * Outputs the Beta Enabler Checkbox
         * 
         * @access		public
         * @since		1.1.5
         * @return		void
         */
        public function beta_checkbox() {

            $this->license_key_class->beta_checkbox();
            
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
            
            $this->support_form_class->enqueue_scripts();
            
        }
        
        /**
         * Enqueues the Styles and Scripts for the Licensing stuff only
         * 
         * @access		public
         * @since		1.0.0
         * @return		void
         */
        public function enqueue_licensing_scripts() {
            
            $this->license_key_class->enqueue_scripts();
            
        }
        
        /**
         * Getter Method for License Validty
         * 
         * @access		public
         * @since		1.0.0
         * @return		string License Validity
         */
        public function get_license_validity() {
            
            return $this->license_key_class->get_license_validity();
            
        }
        
        /**
         * Getter Method for License Status
         * 
         * @access		public
         * @since		1.0.0
         * @return		string License Status
         */
        public function get_license_status() {
            
            return $this->license_key_class->get_license_status();
            
        }
        
        /**
         * Getter Method for License Key
         * 
         * @access		public
         * @since		1.0.0
         * @return		string License Key
         */
        public function get_license_key() {
            
            return $this->license_key_class->get_license_key();
            
        }
        
        /**
         * Getter Method for License Data
         * 
         * @access		public
         * @since		1.0.0
         * @return		array License Data
         */
        public function get_license_data() {

            return $this->license_key_class->get_license_data();

        }

        /**
         * We are forcibly loading the Class into a Namespace, so we do not need to worry about conflicts with other Plugins
         * As a result, we arguably know that we're always running at least v1.6.14 of EDD_SL_Plugin_Updater since RBP Support has never been put into the wild with a lower version
         * However, this helps us know whether we are running the version we expect or higher. It can potentially be helpful in the future for debug purposes
         * 
         * @access		public
         * @since		1.2.0
         * @return		string EDD_SL_Plugin_Updater Class Version
         */
        public function get_edd_sl_plugin_updater_version() {
            
            return $this->updater_class->get_edd_sl_plugin_updater_version();
            
        }
        
        /**
         * Getter method for Beta Status
         * 
         * @access		public
         * @since		1.1.5
         * @return		boolean Beta Status
         */
        public function get_beta_status() {

            return $this->license_key_class->get_beta_status();
        
        }

        /**
         * Retrieve the Store URL for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  string  Store URL
         */
        public function get_store_url() {
            return $this->store_url;
        }

        /**
         * Retrieves the Prefix for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  string  Prefix
         */
        public function get_prefix() {
            return $this->prefix;
        }

        /**
         * Retrieves Plugin Data for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  array  Plugin Data
         */
        public function get_plugin_data() {
            return $this->plugin_data;
        }

        /**
         * Retrieves the Plugin File for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  string  Plugin File
         */
        public function get_plugin_file() {
            return $this->plugin_file;
        }

        /**
         * Retrieves the License Activation URI for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  string  License Activation URI
         */
        public function get_license_activation_uri() {
            return $this->license_activation_uri;
        }

        /**
         * Retrieves the Localization options for this object
         *
         * @access  public
         * @since   2.0.0
         * @return  array  Localization options
         */
        public function get_l10n() {
            return $this->l10n;
        }

        /**
         * Retrieves the set Settings error to use for the object
         *
         * @access  public
         * @since   2.0.0
         * @return  string  Settings Error
         */
        public function get_settings_error() {
            return $this->settings_error;
        }

        /**
         * Retrieves the set Item ID to use for the object
         *
         * @access  public
         * @since   2.0.0
         * @return  integer|boolean  Item ID. False for unset
         */
        public function get_item_id() {
            return $this->item_id;
        }

        /**
         * Load a template file, passing in variables
         * If it exists, it will load a matching template from the plugin that created this class as an override
         *
         * @param   string $template_path  Path to the template file, relative to the ./ directory
         * @param   array $args            Associative array of variables to pass through
         *
         * @access	public
         * @since	2.0.0
         * @return  void
         */
        public function load_template( $template_path, $args = array() ) {

            $template_path = ltrim( $template_path, '/' );

            extract( $args );

            if ( is_file( "{$this->plugin_dir}rbp-support/{$template_path}" ) ) {
                include "{$this->plugin_dir}rbp-support/{$template_path}";
            }
            else {
                include trailingslashit( __DIR__ ) . "templates/{$template_path}";
            }

        }
        
        /**
         * Register Scripts
         * 
         * @access		public
         * @since		1.0.0
         * @return		void
         */
        public function register_scripts() {

            $this->license_key_class->register_scripts();
            $this->support_form_class->register_scripts();
            
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