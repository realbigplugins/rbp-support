<?php
/**
 * Outputs the sidebar support section.
 * The <form> _must_ have a data attribute named "prefix" with the Plugin Prefix for this to work!
 *
 * @since {{VERSION}}
 *
 * @package rpb-support
 * @subpackage rpb-support/core/views
 */

defined( 'ABSPATH' ) || die();
?>

<section class="<?php echo $plugin_prefix; ?>-settings-sidebar-section <?php echo $plugin_prefix; ?>-settings-sidebar-premium-support">
	<p>
		<span class="dashicons dashicons-editor-help"></span>
		<strong>
			<?php _e( 'Need some help?', 'rbp-support' ); ?>
		</strong>
	</p>

	<form id="<?php echo $plugin_prefix; ?>-settings-sidebar-support-form" class="rbp-support-form" data-prefix="<?php echo $plugin_prefix; ?>">

		<?php wp_nonce_field( $plugin_prefix . '_support_send_support_email', $plugin_prefix . '_support_nonce' ); ?>

		<p>
			<label>
				<input type="text" name="support_subject" class="form-field" required
				       placeholder="<?php _e( 'Subject', 'rbp-support' ); ?>"/>
			</label>
		</p>

		<p>
			<label>
					<textarea name="support_message" class="form-field" rows="5" required
					          placeholder="<?php _e( 'Message', 'rbp-support' ); ?>"></textarea>
			</label>
		</p>

		<p>
			<input type="submit" class="button" value="<?php _e( 'Send', 'rbp-support' ); ?>" />
		</p>
	</form>
</section>