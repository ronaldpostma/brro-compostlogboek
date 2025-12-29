<?php
if (!defined('ABSPATH')) exit;

// Output the settings page content
function brro_clb_settings_page() {
    // Title
    echo '<h1>Compost Logboek Instellingen</h1>';
    // Settings form    
    ?>
    <form method="post" action="options.php">
        <?php
        // Output hidden fields for the settings group (nonces, etc.)
        // Must match the group name used in register_setting()
        settings_fields('brro_clb_settings_group');
        // Output any registered settings sections (currently none, but kept for future use)
        do_settings_sections('brro_clb_settings_group');
        
        // Page select for reports url generation
        // Get current settings from the 'brro_clb_settings' option (registered below)
        $options = get_option('brro_clb_settings', array());
        $reportPage = isset($options['brro_clb_reportpage']) ? absint($options['brro_clb_reportpage']) : 0;
        ?>
        <fieldset class="brro-clb-fieldset">
            <legend>Rapportage pagina</legend>
            <label for="brro_clb_reportpage">Op welke pagina staat de rapportage shortcode?</label>
            <?php
            // Field name uses array notation: 'brro_clb_settings[brro_clb_reportpage]'
            // This saves the value as $options['brro_clb_reportpage'] in the 'brro_clb_settings' option
            wp_dropdown_pages(array(
                'name' => 'brro_clb_settings[brro_clb_reportpage]',
                'id' => 'brro_clb_reportpage',
                'selected' => $reportPage,
                'show_option_none' => 'Selecteer',
                'option_none_value' => '',
                'class' => 'brro-clb-page-select',
                'echo' => 1,
            ));
            ?>
            <p class="description">
                De geselecteerde pagina wordt gebruikt om rapportage-links op te bouwen.
            </p>
        </fieldset>
        
        <?php
        submit_button();
        ?>
    </form>
    <?php
}

// Register and add settings
add_action('admin_init', 'brro_clb_register_settings');
function brro_clb_register_settings() {
    // Register the main settings option group
    // - 'brro_clb_settings_group': The settings group name (used in settings_fields())
    // - 'brro_clb_settings': The option name stored in wp_options table
    // - 'brro_clb_settings_validate': Validation/sanitization callback function
    // All fields with name="brro_clb_settings[fieldname]" will be saved to this option
    register_setting('brro_clb_settings_group', 'brro_clb_settings', 'brro_clb_settings_validate');
}

// Validate and sanitize settings before saving
// $input contains all fields from the form with name="brro_clb_settings[...]"
function brro_clb_settings_validate($input) {
    $output = array();
    // Sanitize brro_clb_reportpage: ensure it's a positive integer (page ID)
    if (isset($input['brro_clb_reportpage'])) {
        $output['brro_clb_reportpage'] = absint($input['brro_clb_reportpage']);
    }
    return $output;
}
