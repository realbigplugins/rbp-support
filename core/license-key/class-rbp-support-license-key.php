<?php
/**
 * Class RBP_Support_License_Key
 *
 * @since {{VERSION}}
 *
 * @package RBP_Support
 * @subpackage RBP_Support/core/license-key
 */
class RBP_Support_License_Key {

    /**
     * The main RBP Support object, used to grab some global data
     *
     * @since       {{VERSION}}
     * 
     * @var         RBP_Support
     */
    private $rbp_support;

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
     * RBP_Support_License_Key constructor.
     * 
     * @param		RBP_Support $rbp_support    RBP_Support object, used to pull in some settings
     *
     * @since {{VERSION}}
     */
    function __construct( $rbp_support ) {

        $this->rbp_support = $rbp_support;

        if ( isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) ) {
                
            switch ( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) {
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

        // Ensures all License Data is allowed to fully clear out from the database
        if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) ||
        strpos( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ], 'delete' ) === false ) {

            // Check License Validity
            add_action( 'admin_init', array( $this, 'get_license_validity') );
            
        }

    }

    /**
     * Outputs the Licensing Form. 
     * You can override the Template as needed, but it should pull in any and all data for your Plugin automatically
     * 
     * @access		public
     * @since		{{VERSION}}
     * @return		void
     */
    public function licensing_fields() {

        // Only grab the License Key to output on the Form if we haven't just deleted it
        $license_key = '';
        if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) ||
               strpos( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ], 'delete' ) === false ) {
            $license_key = $this->get_license_key();
        }
            
        $this->rbp_support->load_template( 'licensing-fields.php', array(
            'plugin_prefix' => $this->rbp_support->get_prefix(),
            'license_status' => $this->get_license_status(),
            'license_key' => $license_key,
            'plugin_name' => $this->rbp_support->plugin_data['Name'],
            'l10n' => $this->rbp_support->get_l10n()['licensing_fields'],
        ) );

    }

    /**
     * Activates the License Key
     * 
     * @access		public
     * @since		1.0.0
     * @return		void
     */
    public function activate_license() {
        
        if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license"] ) ||
            ! wp_verify_nonce( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license" ], "{$this->rbp_support->get_prefix()}_license" )
            ) {
            return;
        }
        
        $key = $this->get_license_key();
        
        update_option( "{$this->rbp_support->get_prefix()}_license_key", $key );
        
        $plugin_data = $this->rbp_support->plugin_data;
        
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
        $item_id = apply_filters( "{$this->rbp_support->get_prefix()}_download_id", false );
        
        if ( $item_id ) {
            
            $api_params['item_id'] = (int) $item_id;
            unset( $api_params['item_name'] );
            
        }
        
        $response = wp_remote_get(
            add_query_arg( $api_params, $this->rbp_support->get_store_url() ),
            array(
                'timeout' => 10,
                'sslverify' => false,
            )
        );
        
        if ( is_wp_error( $response ) ) {
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                sprintf( $this->rbp_support->get_l10n()['license_error_messages']['no_connection'], $this->rbp_support->plugin_data['Name'], $this->rbp_support->get_store_url() ),
                'error ' . "{$this->rbp_support->get_prefix()}-notice"
            );
            return false;
        }

        $this->delete_license_data();
        $license_data = $this->get_license_data();
        
        if ( ! isset( $license_data['success'] ) || 
            $license_data['success'] === false ) {
            
            $message = $this->get_license_error_message(
                $license_data['error'],
                $license_data
            );
            
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                $message,
                'error ' . "{$this->rbp_support->get_prefix()}-notice"
            );
            
        }
        else {
            
            $l10n = $this->rbp_support->get_l10n()['license_activation'];
            
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                sprintf( $l10n, $this->rbp_support->plugin_data['Name'] ),
                'updated ' . "{$this->rbp_support->get_prefix()}-notice"
            );
            
            $status = isset( $license_data['license'] ) ? $license_data['license'] : 'invalid';
            
            update_option( "{$this->rbp_support->get_prefix()}_license_status", $status );
            set_transient( "{$this->rbp_support->get_prefix()}_license_validity", $status, DAY_IN_SECONDS );
            
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
        
        $this->delete_license_key();
        $this->delete_license_status();
        $this->delete_license_data();
        $this->delete_license_validity();
        
        if ( isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) && 
            strpos( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ], 'deactivate' ) === false ) {
            
            $l10n = $this->rbp_support->get_l10n()['license_deletion'];
            
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                sprintf( $l10n, $this->rbp_support->plugin_data['Name'] ),
                'updated ' . "{$this->rbp_support->get_prefix()}-notice"
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
        
        if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license" ] ) ||
            ! wp_verify_nonce( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license" ], "{$this->rbp_support->get_prefix()}_license" )
            ) {
            return;
        }
        
        $key = $this->get_license_key();
        
        $plugin_data = $this->rbp_support->plugin_data;
        
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
        $item_id = apply_filters( "{$this->rbp_support->get_prefix()}_download_id", false );
        
        if ( $item_id ) {
            
            $api_params['item_id'] = (int) $item_id;
            unset( $api_params['item_name'] );
            
        }
        
        $this->delete_license_data();
        $license_data = $this->get_license_data();
        
        $l10n = $this->rbp_support->get_l10n()['license_deactivation'];
        
        if ( ! isset( $license_data['success'] ) || 
            $license_data['success'] === false ) {
            
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                sprintf( $l10n['error'], $this->rbp_support->plugin_data['Name'] ),
                'error ' . "{$this->rbp_support->get_prefix()}-notice"
            );
            
        }
        else {
            
            add_settings_error(
                $this->rbp_support->get_settings_error(),
                '',
                sprintf( $l10n['success'], $this->rbp_support->plugin_data['Name'] ),
                'updated ' . "{$this->rbp_support->get_prefix()}-notice"
            );
            
            $this->delete_license_status();
            
        }
        
    }
    
    /**
     * Grabs the appropriate Error Message for each License Error
     * 
     * @param		string $error_code   Type of Error
     * @param		object $license_data License Data response object from EDD API
     *                                                                    
     * @access		public
     * @since		1.0.0
     * @return		string Error Message
     */
    public function get_license_error_message( $error_code, $license_data ) {
        
        $l10n = $this->rbp_support->get_l10n()['license_error_messages'];
        
        switch ( $error_code ) {
                
            case 'expired':
                $message = sprintf(
                    $l10n['expired'],
                    $this->rbp_support->plugin_data['Name'],
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
                $message = sprintf( $l10n['item_name_mismatch'], $this->rbp_support->plugin_data['Name'] );
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
     * Getter Method for License Validty
     * 
     * @access		public
     * @since		1.0.0
     * @return		string License Validity
     */
    public function get_license_validity() {
        
        if ( ! $this->license_validity ) {
            
            if ( ! $this->get_license_key() ) {
                return 'invalid';
            }
            
            if ( ! isset( $_GET['force-check-license'] ) && 
                $license_validity = get_transient( "{$this->rbp_support->get_prefix()}_license_validity" ) ) {
                return $license_validity;
            }
            
            $license_data = $this->get_license_data();
            
            if ( ( isset( $license_data['success'] ) && ! $license_data['success'] ) ||
                $license_data['license'] !== 'valid' ) {
                
                $message = $this->get_license_error_message(
                    ! $license_data['success'] && isset( $license_data['error'] ) ? $license_data['error'] : $license_data['license'],
                    $license_data
                );
                
                // Don't throw up an error. The License Action already has
                if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) ) {
                    add_settings_error(
                       $this->rbp_support->get_settings_error(),
                        '',
                        $message,
                        'error ' . "{$this->rbp_support->get_prefix()}-notice"
                    );
                }
                
            }
            
            $license_validity = isset( $license_data['license'] ) ? $license_data['license'] : 'invalid';
            
            set_transient( "{$this->rbp_support->get_prefix()}_license_validity", $license_validity, DAY_IN_SECONDS );
            
            $this->license_validity = $license_validity;

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
            
            $this->license_status = 'valid';
            
            if ( ! ( $license_status = $this->license_status = get_option( "{$this->rbp_support->get_prefix()}_license_status" ) ) ) {
            
                $this->license_status = 'invalid';
                
            }
            
            if ( get_transient( "{$this->rbp_support->get_prefix()}_license_validity" ) !== 'valid' &&
                $this->get_license_validity() !== 'valid' ) {
                
                $this->license_status = 'invalid';
                
            }

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
            
            if ( isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_key" ] ) ) {
               $this->license_key = trim( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_key" ] );
            }
            else {
               $this->license_key = trim( get_option( "{$this->rbp_support->get_prefix()}_license_key" ) );
            }
            
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

            $data = get_transient( "{$this->rbp_support->get_prefix()}_license_data" );

            if ( $data ) {
                $this->license_data = $data;
                // Array casting here to ensure plugins that have previously stored this an object won't fail
                return (array) $data;
            }

            $license_key = $this->get_license_key();

            if ( ! $license_key ) {
                return array();
            }

            $api_params = array(
                'edd_action' => 'check_license',
                'license' => $license_key,
                'item_name' => $this->rbp_support->plugin_data['Name'],
                'url' => home_url(),
            );

            /**
             * Allow using Download ID for License interactions if desired
             * 
             * @since		1.0.7
             * @return		integer|boolean Download ID, false to use Download Name (default)
             */
            $item_id = apply_filters( "{$this->rbp_support->get_prefix()}_download_id", false );
            
            if ( $item_id ) {
                
                $api_params['item_id'] = (int) $item_id;
                unset( $api_params['item_name'] );
                
            }

            // Call the custom API.
            $response = wp_remote_get(
                add_query_arg( $api_params, $this->rbp_support->get_store_url() ),
                array(
                    'timeout'   => 10,
                    'sslverify' => false
                )
            );

            if ( is_wp_error( $response ) ) {

                add_settings_error(
                   $this->rbp_support->get_settings_error(),
                    '',
                    sprintf( $this->rbp_support->get_l10n()['license_error_messages']['no_connection'], $this->rbp_support->plugin_data['Name'], $this->rbp_support->get_store_url() ),
                    'error ' . "{$this->rbp_support->get_prefix()}-notice"
                );

                return array();

            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            set_transient( "{$this->rbp_support->get_prefix()}_license_data", $data, DAY_IN_SECONDS );

            $this->license_data = $data;
            
        }

        return $this->license_data;

    }

    /**
     * Enqueuee the scripts for the License Key form
     *
     * @access  public
     * @since   {{VERSION}}
     * @return  void
     */
    public function enqueue_scripts() {

        wp_enqueue_script( "rbp_support_licensing" );
        wp_enqueue_style( "rbp_support_licensing" );

    }

    /**
     * Deletes any stored License Validity
     *
     * @access  private
     * @since   {{VERSION}}
     * @return  void
     */
    private function delete_license_validity() {

        if ( delete_transient( "{$this->rbp_support->get_prefix()}_license_validity" ) ) {

            $this->license_validity = false;

        }

    }

    /**
     * Deletes any stored License Data
     *
     * @access  private
     * @since   {{VERSION}}
     * @return  void
     */
    private function delete_license_data() {

        if ( delete_transient( "{$this->rbp_support->get_prefix()}_license_data" ) ) {

            $this->license_data = false;

        }

    }

    /**
     * Deletes a stored License Key
     *
     * @access  private
     * @since   {{VERSION}}
     * @return  void
     */
    private function delete_license_key() {

        if ( delete_option( "{$this->rbp_support->get_prefix()}_license_key" ) ) {
            $this->license_key = '';
        }

    }

    /**
     * Deletes a stored License Status
     *
     * @access  private
     * @since   {{VERSION}}
     * @return  void
     */
    private function delete_license_status() {

        if ( delete_option( "{$this->rbp_support->get_prefix()}_license_status" ) ) {
            $this->license_status = false;
        }

    }

}