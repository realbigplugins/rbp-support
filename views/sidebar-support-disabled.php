<?php
/**
 * Outputs the sidebar support disabled section.
 *
 * @since 1.0.0
 * 
 * @var string $plugin_prefix
 * @var string $plugin_name
 * @var array  $l10n
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
				<?php printf( $l10n['title'], $plugin_name ); ?>
			</strong>
		</p>

		<p>
			<em>
				<?php echo $l10n['disabled_message']; ?>
			</em>
		</p>
	</section>
	
</div>