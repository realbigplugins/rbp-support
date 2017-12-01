# rbp-support
Support Email Module to be included in our Premium Plugins

Using this submodule is super easy! Just follow the following steps and you'll be all set.

1. Add this submodule as a Git Submodule to your Plugin or otherwise download the ZIP from the Releases Tab.
    - To add it as a Submodule, run:
      - `git submodule add https://github.com/realbigplugins/rbp-support.git ./whatever/path/you/want`
      - `git submodule update --init --recursive`
    - This ensures that it pulls in its own dependency as well, which is the [EDD-License-handler](https://github.com/easydigitaldownloads/EDD-License-handler) library.
2. Next, instantiate the Class like so. The Array as the second parameter allows you to provide your own Translations or otherwise replace text Strings throughout RBP Support with text that you'd prefer. By doing this, the translations in use lives inside your own Plugin, which will make more sense to volunteer translators.
    ```
    require_once __DIR__ . '/whatever/path/you/want/rbp-support.php';
    $this->support = new RBP_Support( <PATH_TO_PLUGIN_FILE>, array(
		'support_form' => array(
			'enabled' => array(
				'title' => _x( 'Need some help with %s?', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
				'subject_label' => __( 'Subject', <PLUGIN_TEXT_DOMAIN> ),
				'message_label' => __( 'Message', <PLUGIN_TEXT_DOMAIN> ),
				'send_button' => __( 'Send', <PLUGIN_TEXT_DOMAIN> ),
				'subscribe_text' => _x( 'We make other cool plugins and share updates and special offers to anyone who %ssubscribes here%s.', 'Both %s are used to place HTML for the <a> in the message', <PLUGIN_TEXT_DOMAIN> ),
				'validationError' => _x( 'This field is required', 'Only used by legacy browsers for JavaScript Form Validation', <PLUGIN_TEXT_DOMAIN> ),
				'success' => __( 'Support message succesfully sent!', <PLUGIN_TEXT_DOMAIN> ),
				'error' => __( 'Could not send support message.', <PLUGIN_TEXT_DOMAIN> ),
			),
			'disabled' => array(
				'title' => _x( 'Need some help with %s?', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
				'disabled_message' => __( 'Premium support is disabled. Please register your product and activate your license for this website to enable.', <PLUGIN_TEXT_DOMAIN> )
			),
		),
		'licensing_fields' => array(
			'title' => _x( '%s License', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
			'deactivate_button' => __( 'Deactivate', <PLUGIN_TEXT_DOMAIN> ),
			'activate_button' => __( 'Activate', <PLUGIN_TEXT_DOMAIN> ),
			'delete_deactivate_button' => __( 'Delete and Deactivate', <PLUGIN_TEXT_DOMAIN> ),
			'delete_button' => __( 'Delete', <PLUGIN_TEXT_DOMAIN> ),
			'license_active_label' => __( 'License Active', <PLUGIN_TEXT_DOMAIN> ),
			'license_inactive_label' => __( 'License Inactive', <PLUGIN_TEXT_DOMAIN> ),
			'save_activate_button' => __( 'Save and Activate', <PLUGIN_TEXT_DOMAIN> ),
		),
		'license_nag' => array(
			'register_message' => _x( 'Register your copy of %s now to receive automatic updates and support.', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
			'purchase_message' => _x( 'If you do not have a license key, you can %1$spurchase one%2$s.', 'Both %s are used to place HTML for the <a> in the message' ),
		),
		'license_activation' => __( '%s license successfully activated.', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
		'license_deletion' => __( '%s license successfully deleted.', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
		'license_deactivation' => array(
			'error' => __( 'Error: could not deactivate the license', <PLUGIN_TEXT_DOMAIN> ),
			'success' => __( '%s license successfully deactivated.', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
		),
		'license_error_messages' => array(
			'expired' => _x( 'Your license key expired on %s.', '%s is a localized timestamp', <PLUGIN_TEXT_DOMAIN> ),
			'revoked' => __( 'Your license key has been disabled.', <PLUGIN_TEXT_DOMAIN> ),
			'missing' => __( 'Invalid license.', <PLUGIN_TEXT_DOMAIN> ),
			'site_inactive' => __( 'Your license is not active for this URL.', <PLUGIN_TEXT_DOMAIN> ),
			'item_name_mismatch' => _x( 'This appears to be an invalid license key for %s.', '%s is the Plugin Name', <PLUGIN_TEXT_DOMAIN> ),
			'no_activations_left' => __( 'Your license key has reached its activation limit.', <PLUGIN_TEXT_DOMAIN> ),
			'default' => __( 'An error occurred, please try again.', <PLUGIN_TEXT_DOMAIN> ),
		),
	) );
    ```
3. From here, you're nearly done! You will just need to use a few methods from the Class to include the Views necessary.
    - `$this->support->support_form()` outputs the Support Email Form (It automatically shows the different ones for Enabled and Disabled License Keys)
      - You can override the templates like so:
        - `./wp-content/plugins/your-plugin/rbp-support/sidebar-support.php` for when a License Key is active
        - `./wp-content/plugins/your-plugin/rbp-support/sidebar-support-disabled.php` for when there is no active License Key
    - `$this->support->licensing_fields()` outputs the Activate/Deactivate License Field. 
      - You can override the template like so:
        - `./wp-content/plugins/your-plugin/rbp-support/licensing-fields.php`
    - `$this->support->enqueue_all_scripts()` enqueues all the CSS/JS the submodule utitlizes
      - You can alternatively use `$this->support->enqueue_form_scripts()` or `$this->support->enqueue_licensing_scripts()` for more control.
4. Once your plugin has been released/updated, please add it to the [Wiki](https://github.com/realbigplugins/rbp-support/wiki/RBP-Support-Usage-List) so we can keep track of things. Do this each time you increment the version of RBP Support in your plugin so that the list can be kept as up-to-date as possible.

**That's it!** That's all it takes using this Submodule. Everything is preconfigured for you and you have the option to change things up using a few Filters and overriding Templates.

# Notes

* If you're migrating from another Licensing System to this one, it may be good to move over their License Key from the old place in the Database to the one this Submodule uses.
  - To do this, you'll need to know the "prefix" of your Plugin that this Submodule generates. You can find this by:
    - Taking the Text Domain of your Plugin and lowercasing it and swapping any hyphens for underscores
  - The License Key gets stored by this Submodule as `<prefix>_license_key` in `wp_options`.
  - Alternatively, you can use the `rbp_support_prefix` Filter if the only thing that doesn't match is the Prefix. **Just be sure to remove the Filter as soon as your RBP_Support Object is created!**
* Many 3rd Party Plugins have an expected "Setting" key to show Admin Notices. See [`settings_errors()`](https://codex.wordpress.org/Function_Reference/settings_errors).
  - EDD in particular only shows Admin Notices with the "Setting" `edd-notices` on its Settings Pages
  - You can override the "Setting" key using the Filter `<prefix>_settings_error`
* The Support Email Form is configured to submit via PHP, but it uses some JavaScript for validation.
  - This is because in many of the plugins we tie into, I've found that we are ultimately relegated to placing our things within another `<form>`. Because of this, if Required Fields were not filled out despite not intending to fill out the Support Form, it would not permit the Settings Page to be submitted.
  - I've worked around this by making the Support Form technically a `<div>` and toggling the `required` attributes using JavaScript.
  - You can restore normal `<form>` functionality by doing the following:
    ```
    add_filter( '<prefix>_support_form_tag', function() { return 'form'; }
    ```
* If another plugin includes RBP_Support before yours, the version of RBP_Support included in that other plugin will be the one that it used. See [#5](https://github.com/realbigplugins/rbp-support/issues/5). We will need to keep the submodule up-to-date across our plugins or else things may not work as expected.
  - Defining your own template files will always work, but any other functions within RBP_Support will be stuck at the version of the Plugin that loaded it. This includes where the "fallback" templates are loaded from.
