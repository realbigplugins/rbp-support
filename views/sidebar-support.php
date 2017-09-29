<?php
/**
 * Outputs the sidebar support section.
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

	<form method="post" id="<?php echo $plugin_prefix; ?>-settings-sidebar-support-form">

		<?php wp_nonce_field( $plugin_prefix . '_support_send_support_email', $plugin_prefix . '_support_nonce' ); ?>

		<p>
			<label>
				<input type="text" name="support_subject" required
				       placeholder="<?php _e( 'Subject', 'rbp-support' ); ?>"/>
			</label>
		</p>

		<p>
			<label>
					<textarea name="support_message" rows="5" required
					          placeholder="<?php _e( 'Message', 'rbp-support' ); ?>"></textarea>
			</label>
		</p>

		<p>
			<button type="submit" class="button">
				<?php _e( 'Send', 'rbp-support' ); ?>
			</button>
		</p>
	</form>
</section>