<?php
/**
 * Outputs the sidebar support disabled section.
 *
 * @since 1.0.0
 * 
 * @var string $plugin_prefix
 * @var string $plugin_name
 *
 * @package rbp-support
 * @subpackage rbp-support/views
 */

defined( 'ABSPATH' ) || die();
?>

<div class="rbp-support-sidebar <?php echo $plugin_prefix; ?>-settings-sidebar">

	<section class="sidebar-section <?php echo $plugin_prefix; ?>-settings-sidebar-support-disabled">
		<p>
			<span class="dashicons dashicons-editor-help"></span>
			<strong>
				<?php printf( __( 'Need some help with %s?', 'rbp-support' ), $plugin_name ); ?>
			</strong>
		</p>

		<p>
			<em>
				<?php _e( 'Premium support is disabled. Please register your product and activate your license for this website to enable.', 'rbp-support' ); ?>
			</em>
		</p>
	</section>
	
</div>