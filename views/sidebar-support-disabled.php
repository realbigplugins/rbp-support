<?php
/**
 * Outputs the sidebar support disabled section.
 *
 * @since {{VERSION}}
 *
 * @package rbp-support
 * @subpackage rbp-support/core/views
 */

defined( 'ABSPATH' ) || die();
?>

<section class="<?php echo $plugin_prefix; ?>-settings-sidebar-section <?php echo $plugin_prefix; ?>-settings-sidebar-premium-support-disabled">
	<p>
		<span class="dashicons dashicons-editor-help"></span>
		<strong>
			<?php _e( 'Need some help?', 'rbp-support' ); ?>
		</strong>
	</p>

	<p>
		<em>
			<?php _e( 'Premium support is disabled. Please register your product and activate your license for this ' .
			          'website to enable.', 'rbp-support' ); ?>
		</em>
	</p>
</section>