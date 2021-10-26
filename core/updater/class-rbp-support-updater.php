<?php
/**
 * Class RBP_Support_Updater
 *
 * @since 2.0.0
 *
 * @package RBP_Support
 * @subpackage RBP_Support/core/updater
 */
class RBP_Support_Updater {

    /**
     * The version of the loaded copy of EDD_SL_Plugin_Updater
     *
     * @since		1.2.0
     *
     * @var			string
     */
    private $edd_sl_plugin_updater_version;

    /**
     * The main RBP Support object, used to grab some global data
     *
     * @since       2.0.0
     * 
     * @var         RBP_Support
     */
    private $rbp_support;

    /**
     * RBP_Support_Updater constructor.
     * 
     * @param		RBP_Support $rbp_support    RBP_Support object, used to pull in some settings
     *
     * @since 2.0.0
     */
    function __construct( $rbp_support ) {

        $this->rbp_support = $rbp_support;

        // Ensures all License Data is allowed to fully clear out from the database
        if ( ! isset( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ] ) ||
        strpos( $_REQUEST[ "{$this->rbp_support->get_prefix()}_license_action" ], 'delete' ) === false ) {

            add_action( 'admin_init', array( $this, 'setup_plugin_updates' ) );

        }

        // Ensure Contributors are handled correctly
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 11, 3 );

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
            eval( 'namespace RBP_Support { ?>' . file_get_contents( trailingslashit( dirname( __DIR__ ) ) . 'library/EDD-License-handler/EDD_SL_Plugin_Updater.php' ) . '}' );
        }
        
        if ( is_admin() ) {
            
            $api_params = array(
                'item_name' => $this->rbp_support->get_plugin_data()['Name'],
                'version'   => $this->rbp_support->get_plugin_data()['Version'],
                'license'   => $this->rbp_support->get_license_key(),
                'author'    => $this->rbp_support->get_plugin_data()['Author'],
                'beta'		=> $this->rbp_support->get_beta_status(),
            );

            if ( $item_id = $this->rbp_support->get_item_id() ) {

                $api_params['item_id'] = (int) $item_id;
                unset( $api_params['item_name'] );

            }
            
            $license = new RBP_Support\EDD_SL_Plugin_Updater(
                $this->rbp_support->get_store_url(),
                $this->rbp_support->get_plugin_file(),
                $api_params
            );
            
            if ( ! $this->rbp_support->get_license_key() || $this->rbp_support->get_license_validity() != 'valid' || $this->rbp_support->get_license_status() != 'valid' ) {
                add_action( 'after_plugin_row_' . plugin_basename( $this->rbp_support->get_plugin_file() ),
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

        $register_message = $this->rbp_support->get_l10n()['license_nag']['register_message'];

        if ( $this->rbp_support->get_license_activation_uri() ) {
            $register_message = "<a href=\"{$this->rbp_support->get_license_activation_uri()}\">{$register_message}</a>";
        }

        $this->rbp_support->load_template( 'license-nag.php', array(
            'wp_list_table' => _get_list_table( 'WP_Plugins_List_Table' ),
            'prefix' => $this->rbp_support->get_prefix(),
            'register_message' => $register_message,
            'purchase_message' => $this->rbp_support->get_l10n()['license_nag']['purchase_message'],
            'plugin_uri' => $this->rbp_support->get_plugin_data()['PluginURI'],
            'plugin_name' => $this->rbp_support->get_plugin_data()['Name'],
            'license_key' => $this->rbp_support->get_license_key(),
        ) );

    }

    /**
     * Ensure that Contributors are loaded correctly from the API response
     * It is returned as an Object for each Contributor by default, but WP expects an Array
     *
     * @param   object  $data    plugins_api() result
     * @param   string  $action  Action name
     * @param   array   $args    plugins_api() args
     *
     * @access	public
     * @since	1.4.0
     * @return  object           plugins_api() result
     */
    public function plugins_api_filter( $data, $action, $args ) {

        if ( $action !== 'plugin_information' ) return $data;

        if ( isset( $data->contributors ) && ! empty( $data->contributors ) ) {

            foreach ( $data->contributors as &$contributor ) {

                if ( is_array( $contributor ) ) continue;

                $new_data = array();

                foreach ( $contributor as $key => $value ) {
                    $new_data[ $key ] = $value; 
                }

                $contributor = $new_data;

            }

            unset( $new_data );
            unset( $contributor );

        }

        return $data;

    }

    /**
     * We are forcibly loading the Class into a Namespace, so we do not need to worry about conflicts with other Plugins
     * As a result, we arguably know that we're always running at least v1.6.14 of EDD_SL_Plugin_Updater since RBP Support has never been put into the wild with a lower version
     * However, this helps us know whether we are running the version we expect or higher. It can potentially be helpful in the future for debug purposes
     * 
     * @access		public
     * @since		2.0.0
     * @return		string EDD_SL_Plugin_Updater Class Version
     */
    public function get_edd_sl_plugin_updater_version( $plugin_updater_class_contents = '' ) {
        
        if ( ! $this->edd_sl_plugin_updater_version ) {

            // Holds the PHP file contents of the included version of the Class
            // Since we are eval-ing the code in order to force a Namespace, we cannot find the file path from the Class itself, so we must hardcode it
            $plugin_updater = file_get_contents( trailingslashit( dirname( __DIR__ ) ) . 'library/EDD-License-handler/EDD_SL_Plugin_Updater.php' );
            
            // Search file for @version <version_number>
            preg_match_all( '/@version\s([\d|.]+)/i', $plugin_updater, $matches );
            
            // We want our Capture Group for the first Match
            $version = $matches[1][0];
            
            $this->edd_sl_plugin_updater_version = $version;
            
        }
        
        return $this->edd_sl_plugin_updater_version;
        
    }

}