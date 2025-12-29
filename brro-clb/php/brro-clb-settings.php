<?php
if (!defined('ABSPATH')) exit;

// Output the settings page content
function brro_clb_settings_page() {
    // Title
    echo '<h1>Compost Logboek Instellingen</h1>';
    // Inline admin CSS for settings layout (units section etc.)
    ?>
    <style>
        .brro-clb-fieldset {
            margin-bottom: 24px;
            padding: 16px 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
        }

        .brro-clb-fieldset legend {
            display: contents;
        }

        .brro-clb-fieldset legend h2 {
            margin-top: 0;
            font-size: 20px;
            text-decoration: underline;
        }
        .brro-clb-fieldset p.description {
            color: black;
            max-width: 600px;
        }

        .brro-clb-unit-row {
            display: inline-block;
            vertical-align: top;
            width: 23%;
            box-sizing: border-box;
            margin: 0 0.5% 1.5em;
            margin-left: 0;
        }
        .brro-clb-unit-row input {
            width: 100%
        }

        .brro-clb-unit-row h4 {
            margin-top: 0;
        }

        .brro-clb-unit-row p {
            margin-bottom: 0.75em;
        }

        .brro-clb-unit-suffix {
            margin-left: 4px;
            font-weight: 600;
        }
    </style>
    <?php
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
        // Page select for form page (logboek formulier shortcode)
        $formPage = isset($options['brro_clb_formpage']) ? absint($options['brro_clb_formpage']) : 0;
        // Locations choice (how compost locations are stored on this site)
        $locationsChoice = isset($options['brro_clb_locations_choice']) ? sanitize_text_field($options['brro_clb_locations_choice']) : 'clb-l';
        // Selected custom post type for compost locations (when using own CPT)
        $locationsPostType = isset($options['brro_clb_locations']) ? sanitize_text_field($options['brro_clb_locations']) : '';
        ?>
        <fieldset class="brro-clb-fieldset">
            <legend><h2>Rapportage pagina</h2></legend>
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
                De geselecteerde pagina wordt gebruikt om de link naar de rapportage pagina aan te maken.
            </p>
        </fieldset>

        <fieldset class="brro-clb-fieldset">
            <legend><h2>Formulier pagina</h2></legend>
            <label for="brro_clb_formpage">Op welke pagina staat de logboek formulier shortcode?</label>
            <?php
            // Field name uses array notation: 'brro_clb_settings[brro_clb_formpage]'
            // This saves the value as $options['brro_clb_formpage'] in the 'brro_clb_settings' option
            wp_dropdown_pages(array(
                'name' => 'brro_clb_settings[brro_clb_formpage]',
                'id' => 'brro_clb_formpage',
                'selected' => $formPage,
                'show_option_none' => 'Selecteer',
                'option_none_value' => '',
                'class' => 'brro-clb-page-select',
                'echo' => 1,
            ));
            ?>
            <p class="description">
                De geselecteerde pagina wordt gebruikt om de link naar het logboek formulier aan te maken.
            </p>
        </fieldset>

        <fieldset class="brro-clb-fieldset">
            <legend><h2>Compostlocaties</h2></legend>
            <p>
                <strong>Kies hoe/waar de compostlocaties op deze site staan</strong>
            </p>
            <p>
                <label>
                    <input type="radio"
                           name="brro_clb_settings[brro_clb_locations_choice]"
                           value="clb-l"
                           <?php checked($locationsChoice, 'clb-l'); ?> />
                    Gebruik deze plugin om compostlocaties aan te maken
                </label>
            </p>
            <p>
                <label>
                    <input type="radio"
                           name="brro_clb_settings[brro_clb_locations_choice]"
                           value="own"
                           <?php checked($locationsChoice, 'own'); ?> />
                    Ik heb al een custom post type voor mijn compostlocaties op deze site
                </label>
            </p>
            <p class="description">
                Deze keuze bepaalt of de plugin eigen compostlocaties beheert of aansluit op een bestaand custom post type.
            </p>

        <?php if ($locationsChoice === 'own') : ?>
            <h2 style="margin-top:40px;">Posttype voor compostlocaties</h2>
            <label for="brro_clb_locations">Kies het posttype met je compostlocaties</label>
            <?php
            // Get all public post types (including custom)
            $post_types = get_post_types(
                array(
                    'public' => true,
                ),
                'objects'
            );
            ?>
            <select name="brro_clb_settings[brro_clb_locations]" id="brro_clb_locations" class="brro-clb-posttype-select">
                <option value=""><?php esc_html_e('Selecteer', 'brro-clb'); ?></option>
                <?php
                if (!empty($post_types)) {
                    foreach ($post_types as $post_type) {
                        // $post_type is a WP_Post_Type object
                        $value = $post_type->name;
                        $label = !empty($post_type->labels->singular_name) ? $post_type->labels->singular_name : $post_type->label;
                        ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($locationsPostType, $value); ?>>
                            <?php echo esc_html($label . ' (' . $value . ')'); ?>
                        </option>
                        <?php
                    }
                }
                ?>
            </select>
            <p class="description">
                De berichten van dit posttype worden gebruikt als compostlocaties. Voor elke locatie kan een unieke url worden gemaakt voor het logboek formulier, en voor de rapportage pagina.
            </p>
        <?php endif; ?>
        </fieldset>

        <fieldset class="brro-clb-fieldset">
            <legend><h2>Eenheden</h2></legend>
            <p class="description">
                Deze instellingen worden gebruikt om de gewichten van de eenheden te berekenen voor de rapportage. Gebruikers kunnen in het formulier standaard kiezen uit een exacte hoeveelheid (in kg) of een volume (in liter). Daarnaast kun je voor zowel groenafval als voor compost maximaal vier unieke eenheden aanmaken <sup>(Denk aan bijvoorbeeld: beker, emmer, kruiwagen etc..)</sup>. Deze eenheden worden alleen in het formulier gebruikt als alle 3 velden zijn ingevuld. Laat het dus leeg als ze niet, of niet alle vier wilt gebruiken.
                <br><br>
                Om voor de rapportage te kunnen berekenen hoeveel groenafval er is toegevoegd en/of hoeveelcompost er wordt geoogst, moeten hier ook de gemiddelde gewichten van de eenheden worden ingevuld.
            </p>
            <?php
            // Global average weights per liter for input (green waste) and output (compost)
            $input_volweight_key  = 'brro_clb_input_volweight';
            $output_volweight_key = 'brro_clb_output_volweight';

            $input_volweight_val  = isset($options[$input_volweight_key]) ? floatval($options[$input_volweight_key]) : '';
            $output_volweight_val = isset($options[$output_volweight_key]) ? floatval($options[$output_volweight_key]) : '';

            // Maximum input amount in front-end form for kilo and liter
            $max_unit_amount_key = 'brro_clb_max_unit_amount';
            $max_unit_amount_val = isset($options[$max_unit_amount_key]) ? floatval($options[$max_unit_amount_key]) : 100;
            ?>
            <h3>Volume (per liter) in gewicht</h3>
            <p>
                <label for="brro_clb_input_volweight">1 liter groenafval weegt gemiddeld:</label><br />
                <input type="number"
                       id="brro_clb_input_volweight"
                       name="brro_clb_settings[<?php echo esc_attr($input_volweight_key); ?>]"
                       value="<?php echo esc_attr($input_volweight_val); ?>"
                       class="small-text"
                       step="0.25"
                       min="0" />
                <span class="brro-clb-unit-suffix">kg</span>
            </p>
            <p>
                <label for="brro_clb_output_volweight">1 liter compost weegt gemiddeld:</label><br />
                <input type="number"
                       id="brro_clb_output_volweight"
                       name="brro_clb_settings[<?php echo esc_attr($output_volweight_key); ?>]"
                       value="<?php echo esc_attr($output_volweight_val); ?>"
                       class="small-text"
                       step="0.25"
                       min="0" />
                <span class="brro-clb-unit-suffix">kg</span>
            </p>
            <p>
                <label for="brro_clb_max_unit_amount">Maximum input hoeveelheid in het logformulier voor 'kilo' en 'liter'</label><br />
                <input type="number"
                       id="brro_clb_max_unit_amount"
                       name="brro_clb_settings[<?php echo esc_attr($max_unit_amount_key); ?>]"
                       value="<?php echo esc_attr($max_unit_amount_val); ?>"
                       class="small-text"
                       step="0.5"
                       min="0.5" />
            </p>

            <h3>Groenafval 'input' eenheden</h3>
            <?php
            // Render 4 input units (title, icon URL, average weight)
            for ($i = 1; $i <= 4; $i++) {
                $title_key  = 'brro_clb_inputunit_' . $i . '_title';
                $icon_key   = 'brro_clb_inputunit_' . $i . '_icon';
                $weight_key = 'brro_clb_inputunit_' . $i . '_weight';

                $title_val  = isset($options[$title_key]) ? sanitize_text_field($options[$title_key]) : '';
                $icon_val   = isset($options[$icon_key]) ? esc_url($options[$icon_key]) : '';
                $weight_val = isset($options[$weight_key]) ? floatval($options[$weight_key]) : '';
                ?>
                <div class="brro-clb-unit-row brro-clb-inputunit-row">
                    <h4><?php echo sprintf(esc_html__('Invoer eenheid %d', 'brro-clb'), $i); ?></h4>
                    <p>
                        <label for="brro_clb_inputunit_<?php echo (int) $i; ?>_title">Naam eenheid</label><br />
                        <input type="text"
                               id="brro_clb_inputunit_<?php echo (int) $i; ?>_title"
                               name="brro_clb_settings[<?php echo esc_attr($title_key); ?>]"
                               value="<?php echo esc_attr($title_val); ?>"
                               class="regular-text" />
                    </p>
                    <p>
                        <label for="brro_clb_inputunit_<?php echo (int) $i; ?>_icon">Icoon (URL naar mediabestand)</label><br />
                        <input type="url"
                               id="brro_clb_inputunit_<?php echo (int) $i; ?>_icon"
                               name="brro_clb_settings[<?php echo esc_attr($icon_key); ?>]"
                               value="<?php echo esc_attr($icon_val); ?>"
                               class="regular-text" />
                    </p>
                    <p>
                        <label for="brro_clb_inputunit_<?php echo (int) $i; ?>_weight">Deze eenheid weegt gemiddeld:</label><br />
                        <input type="number"
                               id="brro_clb_inputunit_<?php echo (int) $i; ?>_weight"
                               name="brro_clb_settings[<?php echo esc_attr($weight_key); ?>]"
                               value="<?php echo esc_attr($weight_val); ?>"
                               class="small-text"
                               step="0.25"
                               min="0" />
                        <span class="brro-clb-unit-suffix">kg</span>
                    </p>
                </div>
                <?php
            }
            ?>

            <h3>Compost-oogst eenheden</h3>
            <?php
            // Render 4 output units (title, icon URL, average weight)
            for ($i = 1; $i <= 4; $i++) {
                $title_key  = 'brro_clb_outputunit_' . $i . '_title';
                $icon_key   = 'brro_clb_outputunit_' . $i . '_icon';
                $weight_key = 'brro_clb_outputunit_' . $i . '_weight';

                $title_val  = isset($options[$title_key]) ? sanitize_text_field($options[$title_key]) : '';
                $icon_val   = isset($options[$icon_key]) ? esc_url($options[$icon_key]) : '';
                $weight_val = isset($options[$weight_key]) ? floatval($options[$weight_key]) : '';
                ?>
                <div class="brro-clb-unit-row brro-clb-outputunit-row">
                    <h4><?php echo sprintf(esc_html__('Uitvoer eenheid %d', 'brro-clb'), $i); ?></h4>
                    <p>
                        <label for="brro_clb_outputunit_<?php echo (int) $i; ?>_title">Naam eenheid</label><br />
                        <input type="text"
                               id="brro_clb_outputunit_<?php echo (int) $i; ?>_title"
                               name="brro_clb_settings[<?php echo esc_attr($title_key); ?>]"
                               value="<?php echo esc_attr($title_val); ?>"
                               class="regular-text" />
                    </p>
                    <p>
                        <label for="brro_clb_outputunit_<?php echo (int) $i; ?>_icon">Icoon (URL naar mediabestand)</label><br />
                        <input type="url"
                               id="brro_clb_outputunit_<?php echo (int) $i; ?>_icon"
                               name="brro_clb_settings[<?php echo esc_attr($icon_key); ?>]"
                               value="<?php echo esc_attr($icon_val); ?>"
                               class="regular-text" />
                    </p>
                    <p>
                        <label for="brro_clb_outputunit_<?php echo (int) $i; ?>_weight">Deze eenheid weegt gemiddeld:</label><br />
                        <input type="number"
                               id="brro_clb_outputunit_<?php echo (int) $i; ?>_weight"
                               name="brro_clb_settings[<?php echo esc_attr($weight_key); ?>]"
                               value="<?php echo esc_attr($weight_val); ?>"
                               class="small-text"
                               step="0.25"
                               min="0" />
                        <span class="brro-clb-unit-suffix">kg</span>
                    </p>
                </div>
                <?php
            }
            ?>
        </fieldset>

        <fieldset class="brro-clb-fieldset">
            <legend><h2>Stijl en weergave</h2></legend>
            <?php
            // Get current settings
            $logo_url = isset($options['brro_clb_logo_url']) ? esc_url($options['brro_clb_logo_url']) : '';
            $form_bg_color = isset($options['brro_clb_form_bg_color']) ? esc_attr($options['brro_clb_form_bg_color']) : '#ffffff';
            $form_title_color = isset($options['brro_clb_form_title_color']) ? esc_attr($options['brro_clb_form_title_color']) : '#000000';
            ?>
            <p>
                <label for="brro_clb_logo_url">Logo afbeelding url</label><br />
                <input type="url"
                       id="brro_clb_logo_url"
                       name="brro_clb_settings[brro_clb_logo_url]"
                       value="<?php echo esc_attr($logo_url); ?>"
                       class="regular-text"
                       placeholder="https://example.com/path/to/logo.png" />
                <p class="description">
                    URL naar het logo dat wordt weergegeven op de logboekformulier pagina.
                </p>
            </p>
            <p>
                <label for="brro_clb_form_bg_color">Achtergrondkleur logboekformulier pagina</label><br />
                <input type="text"
                       id="brro_clb_form_bg_color"
                       name="brro_clb_settings[brro_clb_form_bg_color]"
                       value="<?php echo esc_attr($form_bg_color); ?>"
                       class="brro-clb-color-picker" />
                <p class="description">
                    Kies de achtergrondkleur voor de logboekformulier pagina.
                </p>
            </p>
            <p>
                <label for="brro_clb_form_title_color">Kleur titel logboekformulier pagina</label><br />
                <input type="text"
                       id="brro_clb_form_title_color"
                       name="brro_clb_settings[brro_clb_form_title_color]"
                       value="<?php echo esc_attr($form_title_color); ?>"
                       class="brro-clb-color-picker" />
                <p class="description">
                    Kies de kleur voor de titel op de logboekformulier pagina.
                </p>
            </p>
        </fieldset>

        <?php
        submit_button();
        ?>
    </form>
    <?php
}

// Enqueue color picker scripts on settings page
add_action('admin_enqueue_scripts', 'brro_clb_enqueue_settings_scripts');
function brro_clb_enqueue_settings_scripts($hook) {
    // Only enqueue on our settings page
    // Check if we're on the settings page by hook or screen ID
    $screen = get_current_screen();
    $is_settings_page = false;
    
    if ($screen && strpos($screen->id, 'brro-clb-settings') !== false) {
        $is_settings_page = true;
    } elseif (strpos($hook, 'brro-clb-settings') !== false) {
        $is_settings_page = true;
    }
    
    if (!$is_settings_page) {
        return;
    }
    
    // Enqueue WordPress color picker scripts and styles
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    // Add inline script to initialize color picker
    wp_add_inline_script('wp-color-picker', '
        jQuery(document).ready(function($) {
            $(".brro-clb-color-picker").wpColorPicker();
        });
    ');
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
    // Get existing settings to preserve values for fields not in current submission
    // This is important for conditionally hidden fields (e.g., brro_clb_locations when choice is 'clb-l')
    $existing = get_option('brro_clb_settings', array());
    $output = $existing; // Start with existing values to preserve them
    // Sanitize brro_clb_reportpage: ensure it's a positive integer (page ID)
    if (isset($input['brro_clb_reportpage'])) {
        $output['brro_clb_reportpage'] = absint($input['brro_clb_reportpage']);
    }
    // Sanitize brro_clb_formpage: ensure it's a positive integer (page ID)
    if (isset($input['brro_clb_formpage'])) {
        $output['brro_clb_formpage'] = absint($input['brro_clb_formpage']);
    }
    // Sanitize brro_clb_locations_choice: allow only defined values, default to 'clb-l'
    if (isset($input['brro_clb_locations_choice'])) {
        $choice = sanitize_text_field($input['brro_clb_locations_choice']);
        if ($choice === 'clb-l' || $choice === 'own') {
            $output['brro_clb_locations_choice'] = $choice;
        } else {
            $output['brro_clb_locations_choice'] = 'clb-l';
        }
    }
    // Sanitize brro_clb_locations: only allow existing public post types, otherwise empty
    // This field is only submitted when brro_clb_locations_choice is 'own' (field is visible).
    // When brro_clb_locations_choice is 'clb-l', this field is hidden and not submitted,
    // so the existing value from $existing (set at line 339) is automatically preserved.
    if (isset($input['brro_clb_locations'])) {
        $post_type = sanitize_text_field($input['brro_clb_locations']);
        $public_types = get_post_types(
            array(
                'public' => true,
            ),
            'names'
        );
        if (in_array($post_type, $public_types, true)) {
            $output['brro_clb_locations'] = $post_type;
        } else {
            $output['brro_clb_locations'] = '';
        }
    }
    // Note: If the field is not in $input (when choice is 'clb-l'), the value from
    // $existing is already in $output (line 339), so no additional action is needed.

    // Sanitize global volume weights (per liter)
    $input_volweight_key  = 'brro_clb_input_volweight';
    $output_volweight_key = 'brro_clb_output_volweight';

    if (isset($input[$input_volweight_key])) {
        $weight = floatval($input[$input_volweight_key]);
        if ($weight < 0) {
            $weight = 0;
        }
        $weight = round($weight / 0.25) * 0.25;
        $output[$input_volweight_key] = $weight;
    }

    if (isset($input[$output_volweight_key])) {
        $weight = floatval($input[$output_volweight_key]);
        if ($weight < 0) {
            $weight = 0;
        }
        $weight = round($weight / 0.25) * 0.25;
        $output[$output_volweight_key] = $weight;
    }

    // Sanitize maximum input amount for kilo and liter in the log form
    $max_unit_amount_key = 'brro_clb_max_unit_amount';
    if (isset($input[$max_unit_amount_key])) {
        $max_amount = floatval($input[$max_unit_amount_key]);
        if ($max_amount < 0.5) {
            $max_amount = 100;
        }
        $output[$max_unit_amount_key] = $max_amount;
    }

    // Sanitize input units (1–4)
    for ($i = 1; $i <= 4; $i++) {
        $title_key  = 'brro_clb_inputunit_' . $i . '_title';
        $icon_key   = 'brro_clb_inputunit_' . $i . '_icon';
        $weight_key = 'brro_clb_inputunit_' . $i . '_weight';

        if (isset($input[$title_key])) {
            $output[$title_key] = sanitize_text_field($input[$title_key]);
        }

        if (isset($input[$icon_key])) {
            // Validate as URL to media file (basic URL validation)
            $url = esc_url_raw($input[$icon_key]);
            $output[$icon_key] = $url;
        }

        if (isset($input[$weight_key])) {
            $weight = floatval($input[$weight_key]);
            if ($weight < 0) {
                $weight = 0;
            }
            // Normalize to 0.25 steps
            $weight = round($weight / 0.25) * 0.25;
            $output[$weight_key] = $weight;
        }
    }

    // Sanitize output units (1–4)
    for ($i = 1; $i <= 4; $i++) {
        $title_key  = 'brro_clb_outputunit_' . $i . '_title';
        $icon_key   = 'brro_clb_outputunit_' . $i . '_icon';
        $weight_key = 'brro_clb_outputunit_' . $i . '_weight';

        if (isset($input[$title_key])) {
            $output[$title_key] = sanitize_text_field($input[$title_key]);
        }

        if (isset($input[$icon_key])) {
            // Validate as URL to media file (basic URL validation)
            $url = esc_url_raw($input[$icon_key]);
            $output[$icon_key] = $url;
        }

        if (isset($input[$weight_key])) {
            $weight = floatval($input[$weight_key]);
            if ($weight < 0) {
                $weight = 0;
            }
            // Normalize to 0.25 steps
            $weight = round($weight / 0.25) * 0.25;
            $output[$weight_key] = $weight;
        }
    }

    // Sanitize logo URL
    if (isset($input['brro_clb_logo_url'])) {
        $logo_url = esc_url_raw($input['brro_clb_logo_url']);
        $output['brro_clb_logo_url'] = $logo_url;
    }

    // Sanitize form background color (hex color code)
    if (isset($input['brro_clb_form_bg_color'])) {
        $bg_color = sanitize_text_field($input['brro_clb_form_bg_color']);
        // Validate hex color format (#ffffff or ffffff)
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $bg_color) || preg_match('/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $bg_color)) {
            // Ensure it starts with #
            if (substr($bg_color, 0, 1) !== '#') {
                $bg_color = '#' . $bg_color;
            }
            $output['brro_clb_form_bg_color'] = strtolower($bg_color);
        } else {
            // Invalid color, default to white
            $output['brro_clb_form_bg_color'] = '#ffffff';
        }
    }

    // Sanitize form title color (hex color code)
    if (isset($input['brro_clb_form_title_color'])) {
        $title_color = sanitize_text_field($input['brro_clb_form_title_color']);
        // Validate hex color format (#ffffff or ffffff)
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $title_color) || preg_match('/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $title_color)) {
            // Ensure it starts with #
            if (substr($title_color, 0, 1) !== '#') {
                $title_color = '#' . $title_color;
            }
            $output['brro_clb_form_title_color'] = strtolower($title_color);
        } else {
            // Invalid color, default to black
            $output['brro_clb_form_title_color'] = '#000000';
        }
    }

    return $output;
}
