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
        private $version = '1.4.0';
        
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
        public $plugin_data;
        
        /**
         * The stored Beta Status for the Plugin
         *
         * @since		1.1.5
         *
         * @var			boolean
         */
        private $beta_status;
        
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
         * Holds a link to the License Activation URI to help direct our users to where they need to enter their License Key
         *
         * @since	{{VERSION}}
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
         * @since   {{VERSION}}
         *
         * @var RBP_Support_Updater
         */
        private $updater_class;

        /**
         * Holds the license key object. This sets up everything necessary to activate, deactivate, and store license keys
         * 
         * @since   {{VERSION}}
         *
         * @var RBP_Support_License_Key
         */
        private $license_key_class;
        
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
            
            $this->beta_status = $this->get_beta_status();

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
            
            if ( isset( $_REQUEST[ "{$this->prefix}_enable_beta" ] ) && 
                      ! isset( $_REQUEST[ "{$this->prefix}_license_action" ] ) ) {
                
                add_action( 'admin_init', array( $this, 'save_beta_status' ) );
                
            }
            else if ( $this->get_beta_status() &&
                     ! isset( $_REQUEST[ "{$this->prefix}_license_action" ] ) ) {
                
                add_action( 'admin_init', array( $this, 'delete_beta_status' ) );
                
            }
            
            if ( isset( $_REQUEST[ "{$this->prefix}_rbp_support_submit" ] ) ) {
                
                add_action( 'phpmailer_init', array( $this, 'add_debug_file_to_email' ) );
                
                add_action( 'admin_init', array( $this, 'send_support_email' ) );
                
            }
            
            // Scripts are registered/localized, but it is on the Plugin Developer to enqueue them
            add_action( 'admin_init', array( $this, 'register_scripts' ) );

            // Set up the Updater functionality
            require_once trailingslashit( __DIR__ ) . 'core/updater/class-rbp-support-updater.php';
            $this->updater_class = new RBP_Support_Updater( $this );

            // Set up License Key Activation/Deactivation/Storage
            require_once trailingslashit( __DIR__ ) . 'core/license-key/class-rbp-support-license-key.php';
            $this->license_key_class = new RBP_Support_License_Key( $this );
            
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
            
            if ( $this->license_key_class->get_license_status() == 'valid' ) {

                $this->load_template( 'sidebar-support.php', array(
                    'plugin_prefix' => $this->prefix,
                    'plugin_name' => $this->plugin_data['Name'],
                    'l10n' => $this->l10n['support_form']['enabled'],
                ) );
                
            }
            else {

                $this->load_template( 'sidebar-support-disabled.php', array(
                    'plugin_prefix' => $this->prefix,
                    'plugin_name' => $this->plugin_data['Name'],
                    'l10n' => $this->l10n['support_form']['disabled'],
                ) );
                
            }
            
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

            $this->load_template( 'beta-checkbox.php', array(
                'plugin_prefix' => $this->prefix,
                'license_status' => $this->license_key_class->get_license_status(),
                'beta_enabled' => $this->get_beta_status(),
                'l10n' => $this->l10n['beta_checkbox'],
            ) );
            
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
            
            wp_enqueue_script( "rbp_support_form" );
            wp_enqueue_style( "rbp_support_form" );
            
        }
        
        /**
         * Enqueues the Styles and Scripts for the Licensing stuff only
         * 
         * @access		public
         * @since		1.0.0
         * @return		void
         */
        public function enqueue_licensing_scripts() {
            
            wp_enqueue_script( "rbp_support_licensing" );
            wp_enqueue_style( "rbp_support_licensing" );
            
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
         * Getter method for Beta Status
         * 
         * @access		public
         * @since		1.1.5
         * @return		boolean Beta Status
         */
        public function get_beta_status() { 
            
            if ( ! $this->beta_status ) {
                
                if ( isset( $_REQUEST[ "{$this->prefix}_enable_beta" ] ) ) {
                    $this->beta_status = (bool) $_REQUEST[ "{$this->prefix}_enable_beta" ];
                }
                else {
                    $this->beta_status = (bool) get_option( "{$this->prefix}_enable_beta" );
                }
                
            }
            
            return $this->beta_status;
        
        }

        /**
         * Retrieve the Store URL for this object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  string  Store URL
         */
        public function get_store_url() {
            return $this->store_url;
        }

        /**
         * Retrieves the Prefix for this object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  string  Prefix
         */
        public function get_prefix() {
            return $this->prefix;
        }

        /**
         * Retrieves the Plugin File for this object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  string  Plugin File
         */
        public function get_plugin_file() {
            return $this->plugin_file;
        }

        /**
         * Retrieves the License Activation URI for this object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  string  License Activation URI
         */
        public function get_license_activation_uri() {
            return $this->license_activation_uri;
        }

        /**
         * Retrieves the Localization options for this object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  array  Localization options
         */
        public function get_l10n() {
            return $this->l10n;
        }

        /**
         * Retrieves the set Settings error to use for the object
         *
         * @access  public
         * @since   {{VERSION}}
         * @return  string  Settings Error
         */
        public function get_settings_error() {
            return $this->settings_error;
        }

        /**
         * Load a template file, passing in variables
         * If it exists, it will load a matching template from the plugin that created this class as an override
         *
         * @param   string $template_path  Path to the template file, relative to the ./ directory
         * @param   array $args            Associative array of variables to pass through
         *
         * @access	public
         * @since	{{VERSION}}
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
            
            wp_register_script(
                "rbp_support_form",
                plugins_url( '/assets/dist/js/form.js', __FILE__ ),
                array( 'jquery' ),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
                true
            );
            
            wp_register_style(
                "rbp_support_form",
                plugins_url( '/assets/dist/css/form.css', __FILE__ ),
                array(),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
                'all'
            );
            
            wp_register_script(
                "rbp_support_licensing",
                plugins_url( '/assets/dist/js/licensing.js', __FILE__ ),
                array( 'jquery' ),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
                true
            );
            
            wp_register_style(
                "rbp_support_licensing",
                plugins_url( '/assets/dist/css/licensing.css', __FILE__ ),
                array(),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->get_version(),
                'all'
            );
            
            wp_localize_script( 
                "rbp_support_form",
                "rbp_support_form",
                apply_filters( "rbp_support_form_localize_form_script", wp_parse_args( array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                ), $this->l10n['support_form']['enabled'] ) )
            );
            
        }
        
        /**
         * Save the Beta Status when enabled
         * 
         * @access		public
         * @since		1.1.5
         * @return		void
         */
        public function save_beta_status() {
            
            if ( ! isset( $_REQUEST[ "{$this->prefix}_beta" ] ) ||
                ! wp_verify_nonce( $_REQUEST[ "{$this->prefix}_beta" ], "{$this->prefix}_beta" )
               ) {
                return;
            }
            
            update_option( "{$this->prefix}_enable_beta", true );
            
            $l10n = $this->l10n['beta_checkbox'];
            
            add_settings_error(
                $this->settings_error,
                '',
                sprintf( $l10n['enabled_message'], $this->plugin_data['Name'] ),
                "updated {$this->prefix}-notice"
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
            
            if ( ! isset( $_REQUEST[ "{$this->prefix}_beta" ] ) ||
                ! wp_verify_nonce( $_REQUEST[ "{$this->prefix}_beta" ], "{$this->prefix}_beta" )
               ) {
                return;
            }
            
            delete_option( "{$this->prefix}_enable_beta" );
            
            // Reset value
            $this->beta_status = false;
            
            $l10n = $this->l10n['beta_checkbox'];
            
            add_settings_error(
                $this->settings_error,
                '',
                sprintf( $l10n['disabled_message'], $this->plugin_data['Name'] ),
                "updated {$this->prefix}-notice"
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
            do_action( "{$this->prefix}_debug_file_start" );
            
            /**
             * Allows text to be included directly before the Installed Plugins Header
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_installed_plugins_header" );

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
                do_action( "{$this->prefix}_debug_file_before_installed_plugins_list" );

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
                    do_action( "{$this->prefix}_debug_file_before_installed_plugin", $plugin, $id );

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
                    do_action( "{$this->prefix}_debug_file_after_installed_plugin", $plugin, $id );
                    
                }
                
            }
            
            /**
             * Allows text to be included directly after the Installed Plugins List
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_after_installed_plugins_list" );
            
            /**
             * Allows text to be included directly before the Active Plugins Header
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_active_plugins_header" );

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
                do_action( "{$this->prefix}_debug_file_before_active_plugins_list" );

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
                    do_action( "{$this->prefix}_debug_file_before_active_plugin", $plugin, $plugin_path );
                    
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
                    do_action( "{$this->prefix}_debug_file_after_active_plugin", $plugin, $plugin_path );
                    
                }
                
                /**
                 * Allows text to be included directly before the Active Plugins List
                 *                       
                 * @since		1.0.4
                 * @return		void
                 */
                do_action( "{$this->prefix}_debug_file_after_active_plugins_list" );
                
            }
            
            /**
             * Allows text to be included directly after the Active Plugins List
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_after_active_plugins_list" );
            
            /**
             * Allows text to be included directly before the Active Theme Header
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_active_theme_header" );

            // Active Theme
            echo "\n= Active Theme =\n";
            
            /**
             * Allows text to be included directly before the Active Theme Data
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_active_theme_data" );

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
            do_action( "{$this->prefix}_debug_file_after_active_theme_data" );
            
            /**
             * Allows text to be included directly before the WordPress Install Info Header
             *                       
             * @since		1.2.0
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_wordpress_info_header" );
            
            // WordPress Info
            echo "\n= WordPress Info =\n";
            
            /**
             * Allows text to be included directly before the WordPress Install Info List
             *                       
             * @since		1.2.0
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_wordpress_info_list" );
            
            echo "Version: " . get_bloginfo( 'version' ) . "\n";
            
            /**
             * Allows text to be included directly after the WordPress Install Info List
             *                       
             * @since		1.2.0
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_after_wordpress_info_list" );
            
            /**
             * Allows text to be included directly before the PHP Info Header
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_php_info_header" );

            // PHP Info
            echo "\n= PHP Info =\n";
            
            /**
             * Allows text to be included directly before the PHP Info List
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_before_php_info_list" );
            
            echo "Version: " . phpversion();
            
            /**
             * Allows text to be included directly after the PHP Info List
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_after_php_info_list" );
            
            /**
             * Allows text to be included at the end of the Debug File
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->prefix}_debug_file_end" );
            
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
            
            if ( ! isset( $_POST[ "{$this->prefix}_support_nonce" ] ) ||
                ! wp_verify_nonce( $_POST[ "{$this->prefix}_support_nonce" ], "{$this->prefix}_send_support_email" ) ||
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
            $data = apply_filters( "{$this->prefix}_support_email_data", array(
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
                    "Plugin: {$this->plugin_data['Name']} v{$this->plugin_data['Version']}" . 
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
                $message_prefix = apply_filters( "{$this->prefix}_support_email_before_message", $message_prefix );
                
                /**
                 * In the event that per-plugin we'd like to change the mail-to, we can
                 * 
                 * @param		string Email Address
                 *                     
                 * @since		1.1.0
                 * @return		string Email Address
                 */
                $mail_to = apply_filters( "{$this->prefix}_support_email_mail_to", 'support@realbigplugins.com' );
                
                $message = "{$message_prefix}{$message}";

                $result = wp_mail(
                    $mail_to,
                    stripslashes( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) ),
                    stripslashes( html_entity_decode( $message, ENT_QUOTES, 'UTF-8' ) ),
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
                    $result ? $l10n['success'] : $l10n['error'],
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
                    do_action_ref_array( "{$this->prefix}_rbp_support_phpmailer_init", array( &$phpmailer ) );
                    
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