<?php
/**
 * Outputs the license nag on the plugins screen
 *
 * @since {{VERSION}}
 *
 * @var string $wp_list_table
 * @var string $prefix
 * @var string $register_message
 * @var string $purchase_message
 * @var string $plugin_uri
 * @var string $plugin_name
 * @var string $license_key
 *
 * @package RBP_Support
 * @subpackage RBP_Support/templates
 */

defined( 'ABSPATH' ) || die();

?>

<tr class="plugin-update-tr">
    <td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
        <div class="update-message">
            <?php

                /**
                 * This Filter was originally included as a way to potentially include a link to the License Activation page, but this is now an option as part of the plugin Constructor
                 * 
                 * @param		string Register Plugin Message
                 * 
                 * @since		1.0.0
                 * @return		string Register Plugin Message
                 */
                $register_message = apply_filters( "{$prefix}_register_message", sprintf(
                    $register_message,
                    $plugin_name
                ) );

                echo $register_message;

                if ( ! $license_key && 
                    ! empty( $plugin_uri ) ) {
                    printf(
                        ' ' . $purchase_message,
                        '<a href="' . $plugin_uri . '">',
                        '</a>'
                    );
                }

            ?>
        </div>
    </td>
</tr>