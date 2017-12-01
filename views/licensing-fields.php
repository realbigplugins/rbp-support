<?php
/**
 * Outputs the licensing settings.
 *
 * @since 1.0.0
 *
 * @var string $plugin_prefix
 * @var string $license_key
 * @var string $license_status
 * @var string $plugin_name
 * @var array  $l10n
 *
 * @package RBP_Support
 * @subpackage RBP_Support/views
 */

defined( 'ABSPATH' ) || die();

?>

<div class="rbp-support-licensing<?php echo ( $license_status !== 'valid' ) ? ' licensing-inactive' : ''; ?>">

	<?php wp_nonce_field( $plugin_prefix . '_license', $plugin_prefix . '_license' ); ?>

	<p>
		<label for="<?php echo $plugin_prefix; ?>_license_key">
			<strong>
				<?php printf( $l10n['title'], $plugin_name ); ?>
			</strong>
		</label>
	</p>

	<?php wp_nonce_field( $plugin_prefix . '_license', $plugin_prefix . '_nonce' ); ?>

	<input type="text" name="<?php echo $plugin_prefix; ?>_license_key" id="<?php echo $plugin_prefix; ?>_license_key"
	       class="regular-text" <?php echo $license_key ? 'disabled' : ''; ?>
	       value="<?php echo esc_attr( $license_key ); ?>"/>

	<?php if ( $license_key ) : ?>

		<?php
		if ( $license_status == 'valid' ) : ?>

			<button name="<?php echo $plugin_prefix; ?>_license_action" value="deactivate" class="button"
			        id="<?php echo $plugin_prefix; ?>_license_deactivate">
				<?php echo $l10n['deactivate_button']; ?>
			</button>

		<?php else : ?>

			<button name="<?php echo $plugin_prefix; ?>_license_action" value="activate" class="button button-primary"
			        id="<?php echo $plugin_prefix; ?>_license_activate">
				<?php echo $l10n['activate_button']; ?>
			</button>

		<?php endif; ?>

		&nbsp;

		<?php if ( $license_status && $license_status == 'valid' ) : ?>

			<button class="button" id="<?php echo $plugin_prefix; ?>_license_delete" name="<?php echo $plugin_prefix; ?>_license_action" value="delete_deactivate">
				<?php echo $l10n['delete_deactivate_button']; ?>
			</button>

		<?php else: ?>

			<button class="button" id="<?php echo $plugin_prefix; ?>_license_delete" name="<?php echo $plugin_prefix; ?>_license_action" value="delete">
				<?php echo $l10n['delete_button']; ?>
			</button>

		<?php endif; ?>


		<p class="license-status <?php echo $license_status === 'valid' ? 'active' : 'inactive'; ?>">
				<span>
					<?php
					if ( $license_status === 'valid' ) {

						echo $l10n['license_active_label'];

					} else {

						echo $l10n['license_inactive_label'];
						
					}
					?>
				</span>
		</p>

	<?php else: ?>

		<button name="<?php echo $plugin_prefix; ?>_license_action" value="save" class="button button-primary"
		        id="<?php echo $plugin_prefix; ?>_license_activate">
			<?php echo $l10n['save_activate_button']; ?>
		</button>

	<?php endif; ?>

</div>