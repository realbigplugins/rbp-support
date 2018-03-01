<?php
/**
 * Outputs the Beta Enabling Checkbox
 *
 * @since 1.1.5
 *
 * @var string $plugin_prefix
 * @var string $license_status
 * @var boolean $beta_enabled
 * @var array  $l10n
 *
 * @package RBP_Support
 * @subpackage RBP_Support/views
 */

defined( 'ABSPATH' ) || die();

if ( $license_status == 'valid' ) : ?>

	<div class="rbp-support-enable-beta">
		
		<?php wp_nonce_field( $plugin_prefix . '_beta', $plugin_prefix . '_beta' ); ?>

		<label>
			<input type="checkbox" name="<?php echo $plugin_prefix; ?>_enable_beta" value="1"<?php echo ( $beta_enabled ) ? ' checked' : ''; ?> />
			<?php echo $l10n['label']; ?>&nbsp;
		</label>
		
		<p class="description">
			<?php echo $l10n['disclaimer']; ?>
		</p>

	</div>

<?php endif;