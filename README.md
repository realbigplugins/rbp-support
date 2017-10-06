# rbp-support
Support Email Module to be included in our Premium Plugins

Using this submodule is super easy! Just follow the following steps and you'll be all set.

1. Add this submodule as a Git Submodule to your Plugin or otherwise download the ZIP from the Releases Tab.
    - To add it as a Submodule, run:
      - `git submodule add https://github.com/realbigplugins/rbp-support.git ./whatever/path/you/want`
      - `cd ./whatever/path/you/want && git submodule init && git submodule update`
    - This ensures that it pulls in its own dependency as well, which is the [EDD-License-handler](https://github.com/easydigitaldownloads/EDD-License-handler) library.
2. Next, instantiate the Class like so:
    ```
    require_once __DIR__ . '/whatever/path/you/want/rbp-support.php';
    $this->support = new RBP_Support( <PATH_TO_PLUGIN_FILE> );
    ```
3. From here, you're nearly done! You will just need to use a few methods from the Class to include the Views necessary.
    - `$this->support->support_form()` outputs the Support Email Form (It automatically shows the different ones for Enabled and Disabled License Keys)
      - You can override the templates like so:
        - `./wp-content/plugins/your-plugin/rbp-support/sidebar-support.php` for when a License Key is active
        - `./wp-content/plugins/your-plugin/rbp-support/sidebar-support-disabled.php` for when there is no active License Key
    - `$this->support->licensing_fields()` outputs the Activate/Deactivate License Field. 
      - You can override the template like so:
        - `./wp-content/plugins/your-plugin/rbp-support/licensing-fields.php` for when a License Key is active
    - `$this->support->enqueue_all_scripts()` enqueues all the CSS/JS the submodule utitlizes
      - You can alternatively use `$this->support->enqueue_form_scripts()` or `$this->support->enqueue_licensing_scripts()` for more control.

**That's it!** That's all it takes using this Submodule. Everything is preconfigured for you and you have the option to change things up using a few Filters and overriding Templates.

# Notes

* If you're migrating from another Licensing System to this one, it may be good to move over their License Key from the old place in the Database to the one this Submodule uses.
  - To do this, you'll need to know the "prefix" of your Plugin that this Submodule generates. You can find this by:
    - Taking the Text Domain of your Plugin and lowercasing it and swapping any hyphens for underscores
  - The License Key gets stored by this Submodule as `<prefix>_license_key` in `wp_options`.
* I've found some 3rd party plugins are very picky about Admin Notices when you're tying into them. I've included a Filter to allow the "Setting" for the Admin Notice to be changed to match what they're expecting.
  - EDD in particular only shows Admin Notices with the "Setting" `edd-notices` on its Settings Pages
  - You can override the "Setting" key using the Filter `<prefix>_settings_error`
* The Support Email Form is configured to submit via PHP, but it uses some JavaScript for validation.
  - This is because in many of the plugins we tie into, I've found that we are ultimately relegated to placing our things within another `<form>`. Because of this, if Required Fields were not filled out despite not intending to fill out the Support Form, it would not permit the Settings Page to be submitted.
  - I've worked around this by making the Support Form technically a `<div>` and toggling the `required` attributes using JavaScript.
  - You can restore normal `<form>` functionality by doing the following:
    ```
    add_filter( '<prefix>_support_form_tag', function() { return 'form'; }
    ```