<?php
/**
 * Outputs the sidebar support section.
 * The <form> _must_ have a data attribute named "prefix" with the Plugin Prefix for this to work!
 *
 * @since {{VERSION}}
 *
 * @package rpb-support
 * @subpackage rpb-support/views
 */

defined( 'ABSPATH' ) || die();
?>

<div class="rbp-support-sidebar <?php echo $plugin_prefix; ?>-settings-sidebar">

	<section class="sidebar-section form-section">

		<p>
			<span class="dashicons dashicons-editor-help"></span>
			<strong>
				<?php _e( 'Need some help?', 'rbp-support' ); ?>
			</strong>
		</p>

		<form id="<?php echo $plugin_prefix; ?>-settings-sidebar-support-form" class="rbp-support-form" data-prefix="<?php echo $plugin_prefix; ?>">

			<?php wp_nonce_field( $plugin_prefix . '_send_support_email', $plugin_prefix . '_support_nonce' ); ?>

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

		<div class="success-message hidden">
			<?php _e( 'Message Sent Successfully', 'rbp-support' ); ?>
		</div>

	</section>

	<section class="sidebar-section subscribe-section">
		
		<?php
			printf(
				__( 'We make other cool plugins and share updates and special offers to anyone who %ssubscribes here%s.', 'rbp-support' ),
				'<a href="http://realbigplugins.com/subscribe/?utm_source=' . rawurlencode( $plugin_name ) . '&utm_medium=Plugin' .
				'%20settings%20sidebar%20link&utm_campaign=' . rawurlencode( $plugin_name ) . '%20Plugin" target="_blank">',
				'</a>'
			);
		?>

	</section>
	
</div>