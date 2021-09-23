<?php
/**
 * Class RBP_Support_Support_Form
 *
 * @since 2.0.0
 *
 * @package RBP_Support
 * @subpackage RBP_Support/core/support-form
 */
class RBP_Support_Support_Form {

    /**
     * The main RBP Support object, used to grab some global data
     *
     * @since       2.0.0
     * 
     * @var         RBP_Support
     */
    private $rbp_support;

    /**
     * RBP_Support_Support_Form constructor.
     * 
     * @param		RBP_Support $rbp_support    RBP_Support object, used to pull in some settings
     *
     * @since 2.0.0
     */
    function __construct( $rbp_support ) {

        $this->rbp_support = $rbp_support;

        if ( isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_rbp_support_submit" ] ) ) {
                
            add_action( 'phpmailer_init', array( $this, 'add_debug_file_to_email' ) );
            
            add_action( 'admin_init', array( $this, 'send_support_email' ) );
            
        }

    }
    
    /**
     * Outputs the Support Form. Call this method within whatever container you like.
     * You can override the Template as needed, but it should pull in any and all data for your Plugin automatically
     * 
     * @access		public
     * @since		2.0.0
     * @return		void
     */
    public function support_form() {

        $l10n = $this->rbp_support->get_l10n()['support_form'];
        
        if ( $this->rbp_support->get_license_status() == 'valid' ) {

            $this->rbp_support->load_template( 'sidebar-support.php', array(
                'plugin_prefix' => $this->rbp_support->get_prefix(),
                'plugin_name' => $this->rbp_support->get_plugin_data()['Name'],
                'l10n' => $l10n['enabled'],
            ) );
            
        }
        else {

            $this->rbp_support->load_template( 'sidebar-support-disabled.php', array(
                'plugin_prefix' => $this->rbp_support->get_prefix(),
                'plugin_name' => $this->rbp_support->get_plugin_data()['Name'],
                'l10n' => $l10n['disabled'],
            ) );
            
        }
        
    }

    /**
     * Create Debug File to attach to the Email. This is a base64 buffer.
     * This has an obscene amount of Action Hooks in it for flexibility. While there is no space between some, I figure
     *                                      
     * @access		public
     * @since		1.0.0
     * @return		string base64 buffer
     */
    public function debug_file() {
        
        ob_start();

        echo "= RBP_Support v" . $this->rbp_support->get_version() . " =\n";
        echo "Loaded from: " . $this->rbp_support->get_file_path() . "\n\n";
        
        /**
         * Allows text to be included directly after the RBP_Support version. Sorry, no one gets to place data before it :P
         *      
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_start" );
        
        /**
         * Allows text to be included directly before the Installed Plugins Header
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_installed_plugins_header" );

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
            do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_installed_plugins_list" );

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
                do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_installed_plugin", $plugin, $id );

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
                do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_installed_plugin", $plugin, $id );
                
            }
            
        }
        
        /**
         * Allows text to be included directly after the Installed Plugins List
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_installed_plugins_list" );
        
        /**
         * Allows text to be included directly before the Active Plugins Header
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_active_plugins_header" );

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
            do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_active_plugins_list" );

            foreach ( $active_plugins as $id ) {
                
                $plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $id;

                try {

                    if ( ! is_file( $plugin_path ) ) {
                        throw new Exception( 'This plugin does not exist' );
                    }

                    $plugin = get_plugin_data( $plugin_path, false, false );
                    
                    /**
                     * Allows additional information about an Active Plugin to be inserted before it in the Debug File
                     * 
                     * @param		array  Plugin Data Array
                     * @param		string Plugin Path
                     *                       
                     * @since		1.0.4
                     * @return		void
                     */
                    do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_active_plugin", $plugin, $plugin_path );
                    
                    if ( isset( $plugin['Name'] ) && 
                        isset( $plugin['Version'] ) && 
                        ! empty( $plugin['Name'] ) && 
                        ! empty( $plugin['Version'] ) ) {

                        echo "$plugin[Name]: $plugin[Version]\n";
                        
                    }
                    else {
                        throw new Exception( 'Missing vital plugin headers' );
                    }

                }
                catch ( Exception $exception ) {
                    
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
                do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_active_plugin", $plugin, $plugin_path );
                
            }
            
            /**
             * Allows text to be included directly before the Active Plugins List
             *                       
             * @since		1.0.4
             * @return		void
             */
            do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_active_plugins_list" );
            
        }
        
        /**
         * Allows text to be included directly after the Active Plugins List
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_active_plugins_list" );
        
        /**
         * Allows text to be included directly before the Active Theme Header
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_active_theme_header" );

        // Active Theme
        echo "\n= Active Theme =\n";
        
        /**
         * Allows text to be included directly before the Active Theme Data
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_active_theme_data" );

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
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_active_theme_data" );
        
        /**
         * Allows text to be included directly before the WordPress Install Info Header
         *                       
         * @since		1.2.0
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_wordpress_info_header" );
        
        // WordPress Info
        echo "\n= WordPress Info =\n";
        
        /**
         * Allows text to be included directly before the WordPress Install Info List
         *                       
         * @since		1.2.0
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_wordpress_info_list" );
        
        echo "Version: " . get_bloginfo( 'version' ) . "\n";
        
        /**
         * Allows text to be included directly after the WordPress Install Info List
         *                       
         * @since		1.2.0
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_wordpress_info_list" );
        
        /**
         * Allows text to be included directly before the PHP Info Header
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_php_info_header" );

        // PHP Info
        echo "\n= PHP Info =\n";
        
        /**
         * Allows text to be included directly before the PHP Info List
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_before_php_info_list" );
        
        echo "Version: " . phpversion();
        
        /**
         * Allows text to be included directly after the PHP Info List
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_after_php_info_list" );
        
        /**
         * Allows text to be included at the end of the Debug File
         *                       
         * @since		1.0.4
         * @return		void
         */
        do_action( "{$this->rbp_support->get_prefix()}_debug_file_end" );
        
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
        
        if ( ! isset( $_POST[ "{$this->rbp_support->get_prefix()}_support_nonce" ] ) ||
            ! wp_verify_nonce( $_POST[ "{$this->rbp_support->get_prefix()}_support_nonce" ], "{$this->rbp_support->get_prefix()}_send_support_email" ) ||
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
        $data = apply_filters( "{$this->rbp_support->get_prefix()}_support_email_data", array(
            'subject' => esc_attr( $_POST['support_subject'] ),
            'message' => esc_attr( $_POST['support_message'] ),
            'license_data' => $this->rbp_support->get_license_data(),
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
            $message_prefix = "Sent via RBP_Support v" . $this->rbp_support->get_version() . "\n" . 
                "Plugin: {$this->rbp_support->get_plugin_data()['Name']} v{$this->rbp_support->get_plugin_data()['Version']}" . 
                ( ( $this->rbp_support->get_beta_status() ) ? ' (Betas Enabled)' : '' ) . "\n" . 
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
            $message_prefix = apply_filters( "{$this->rbp_support->get_prefix()}_support_email_before_message", $message_prefix );
            
            /**
             * In the event that per-plugin we'd like to change the mail-to, we can
             * 
             * @param		string Email Address
             *                     
             * @since		1.1.0
             * @return		string Email Address
             */
            $mail_to = apply_filters( "{$this->rbp_support->get_prefix()}_support_email_mail_to", 'support@realbigplugins.com' );
            
            $message = "{$message_prefix}{$message}";

            $result = wp_mail(
                $mail_to,
                stripslashes( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) ),
                stripslashes( html_entity_decode( $message, ENT_QUOTES, 'UTF-8' ) ),
                array(
                    "From: $license_data[customer_name] <$license_data[customer_email]>",
                    "X-RBP-SUPPORT: " . $this->rbp_support->get_version(),
                ),
                array(
                )
            );
            
            $l10n = $this->rbp_support->get_l10n()['support_form']['enabled'];
                
            add_settings_error(
                $this->rbp_support->get_settings_error(),
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
                do_action_ref_array( "{$this->rbp_support->get_prefix()}_rbp_support_phpmailer_init", array( &$phpmailer ) );
                
                break;
                
            }
            
        }
        
    }

    /**
     * Register Scripts for the Support Form
     *
     * @access  public
     * @since   2.0.0
     * @return  void
     */
    public function register_scripts() {

        wp_register_script(
            'rbp_support_form',
            plugins_url( '/dist/assets/js/form.js', $this->rbp_support->get_file_path() ),
            array( 'jquery' ),
            defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->rbp_support->get_version(),
            true
        );
        
        wp_register_style(
            'rbp_support_form',
            plugins_url( '/dist/assets/css/form.css', $this->rbp_support->get_file_path() ),
            array(),
            defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : $this->rbp_support->get_version(),
            'all'
        );

        wp_localize_script( 
            'rbp_support_form',
            'rbp_support_form',
            apply_filters( "rbp_support_form_localize_support_form_script", wp_parse_args( array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            ), $this->rbp_support->get_l10n()['support_form']['enabled'] ) )
        );

    }

    /**
     * Enqueuee the scripts for the Support Form
     *
     * @access  public
     * @since   2.0.0
     * @return  void
     */
    public function enqueue_scripts() {

        wp_enqueue_script( 'rbp_support_form' );
        wp_enqueue_style( 'rbp_support_form' );

    }

}