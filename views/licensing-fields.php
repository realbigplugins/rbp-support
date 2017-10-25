<?php
/**
 * Outputs the licensing settings.
 *
 * @since 1.0.0
 *
 * @var string $plugin_prefix
 * @var string $license_key
 * @var string $license_validity
 * @var string $plugin_name
 *
 * @package RBP_Support
 * @subpackage RBP_Support/views
 */

defined( 'ABSPATH' ) || die();

?>

<div class="rbp-support-licensing<?php echo ( $license_validity !== 'valid' ) ? ' licensing-inactive' : ''; ?>">

	<?php wp_nonce_field( $plugin_prefix . '_license', $plugin_prefix . '_license' ); ?>

	<p>
		<label for="<?php echo $plugin_prefix; ?>_license_key">
			<strong>
				<?php printf( __( '%s License', 'rbp-support' ), $plugin_name ); ?>
			</strong>
		</label>
	</p>

	<?php wp_nonce_field( $plugin_prefix . '_license', $plugin_prefix . '_nonce' ); ?>

	<input type="text" name="<?php echo $plugin_prefix; ?>_license_key" id="<?php echo $plugin_prefix; ?>_license_key"
	       class="regular-text" <?php echo $license_key ? 'disabled' : ''; ?>
	       value="<?php echo esc_attr( $license_key ); ?>"/>

	<?php if ( $license_key ) : ?>

		<?php
		if ( $license_validity == 'valid' ) : ?>

			<button name="<?php echo $plugin_prefix; ?>_license_action" value="deactivate" class="button"
			        id="<?php echo $plugin_prefix; ?>_license_deactivate">
				<?php _e( 'Deactivate', 'rbp-support' ); ?>
			</button>

		<?php else : ?>

			<button name="<?php echo $plugin_prefix; ?>_license_action" value="activate" class="button button-primary"
			        id="<?php echo $plugin_prefix; ?>_license_activate">
				<?php _e( 'Activate', 'rbp-support' ); ?>
			</button>

		<?php endif; ?>

		&nbsp;

		<?php if ( $license_validity && $license_validity == 'valid' ) : ?>

			<button class="button" id="<?php echo $plugin_prefix; ?>_license_delete" name="<?php echo $plugin_prefix; ?>_license_action" value="delete_deactivate">
				<?php _e( 'Delete and Deactivate', 'rbp-support' ); ?>
			</button>

		<?php else: ?>

			<button class="button" id="<?php echo $plugin_prefix; ?>_license_delete" name="<?php echo $plugin_prefix; ?>_license_action" value="delete">
				<?php _e( 'Delete', 'rbp-support' ); ?>
			</button>

		<?php endif; ?>


		<p class="license-status <?php echo $license_validity === 'valid' ? 'active' : 'inactive'; ?>">
				<span>
					<?php
					if ( $license_validity === 'valid' ) {

						_e( 'License Active', 'rbp-support' );

					} else {

						_e( 'License Inactive', 'rbp-support' );
					}
					?>
				</span>
		</p>

	<?php else: ?>

		<button name="<?php echo $plugin_prefix; ?>_license_action" value="save" class="button button-primary"
		        id="<?php echo $plugin_prefix; ?>_license_activate">
			<?php _e( 'Save and Activate', 'rbp-support' ); ?>
		</button>

	<?php endif; ?>

</div>