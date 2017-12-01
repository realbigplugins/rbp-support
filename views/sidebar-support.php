<?php
/**
 * Outputs the sidebar support section.
 * The <form> _must_ have a data attribute named "prefix" with the Plugin Prefix for this to work!
 *
 * @since 1.0.0
 * 
 * @var string $plugin_prefix
 * @var string $plugin_name
 * @var array  $l10n
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
				<?php printf( $l10n['title'], $plugin_name ); ?>
			</strong>
		</p>
		
		<?php 
		
		
		/**
		 * This one is a doozy, so let me take a bit to explain
		 * In most cases, it is obviously ideal to have the Form actually be a <form>.
		 * However, most of our plugins are tying into other, 3rd party services where we often have to place our things inside of another <form>
		 * HTML doesn't let you nest <form>s (It will strip them out), so by using a <div> we can use some creative JavaScript to conditionally validate this subform
		 * See ./build/js/admin/form/submit.js for more details
		 * 
		 * If this is a <form>, the JS Validation is not used. Instead the fields use regular ol' `required` validation
		 * 
		 * @since		1.0.0
		 * @return		string Tag
		 * 
		 */
		$form_tag = apply_filters( $plugin_prefix . '_support_form_tag', 'div' );
		
		?>

		<<?php echo $form_tag; ?> id="<?php echo $plugin_prefix; ?>-settings-sidebar-support-form" class="rbp-support-form<?php echo ( $form_tag == 'div' ) ? ' javascript-interrupt' : ''; ?>"<?php echo ( $form_tag == 'form' ) ? ' method="post"' : ''; ?> data-prefix="<?php echo $plugin_prefix; ?>">

			<?php wp_nonce_field( $plugin_prefix . '_send_support_email', $plugin_prefix . '_support_nonce' ); ?>

			<p>
				<label>
					<input type="text" name="support_subject" class="form-field required"
						   placeholder="<?php echo $l10n['subject_label']; ?>"<?php echo ( $form_tag == 'form' ) ? ' required' : ''; ?>/>
				</label>
			</p>

			<p>
				<label>
						<textarea name="support_message" class="form-field required" rows="5"
								  placeholder="<?php echo $l10n['message_label']; ?>"<?php echo ( $form_tag == 'form' ) ? ' required' : ''; ?>></textarea>
				</label>
			</p>

			<p>
				
				<input type="submit" name="<?php echo $plugin_prefix; ?>_rbp_support_submit" class="button" value="<?php _e( 'Send', 'rbp-support' ); ?>" />
				
				<?php
				/**
				 * This allows submission to happen despite disabling our Submit Button
				 * This gets passed through and lets our code know that the submission was successful and to fire off the email
				 * This is _mostly_ necessary for the <div> version of the form, but the <form> version uses it too 
				 * 
				 * @since		1.0.3
				 */
				?>
				<input type="hidden" name="<?php echo $plugin_prefix; ?>_rbp_support_submit" class="submit-hidden" value="<?php echo $l10n['send_button']; ?>" />
				
			</p>

		</<?php echo $form_tag; ?>>

	</section>

	<section class="sidebar-section subscribe-section">
		
		<?php
			printf(
				$l10n['subscribe_text'],
				'<a href="http://realbigplugins.com/subscribe/?utm_source=' . rawurlencode( $plugin_name ) . '&utm_medium=Plugin' .
				'%20settings%20sidebar%20link&utm_campaign=' . rawurlencode( $plugin_name ) . '%20Plugin" target="_blank">',
				'</a>'
			);
		?>

	</section>
	
</div>