<?php
/**
 * Template Name: Log User Request Form Template
 * Custom template for displaying the compost logboek user request form
 * 
 * This template is loaded when ?logboek-opvragen parameter is present in the URL
 * on the front page. It displays only the user request form without the
 * theme header and footer - a "naked" template with minimal HTML structure.
 */

// Disable admin bar for this template
show_admin_bar(false);

// Get settings for logo, background color, and title color
$options = get_option('brro_clb_settings', array());
$logo_url = isset($options['brro_clb_logo_url']) ? esc_url($options['brro_clb_logo_url']) : '';
$form_bg_color = isset($options['brro_clb_form_bg_color']) ? esc_attr($options['brro_clb_form_bg_color']) : '#ffffff';
$form_title_color = isset($options['brro_clb_form_title_color']) ? esc_attr($options['brro_clb_form_title_color']) : '#000000';

// Get language attribute for html tag
$language = get_bloginfo('language');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            background-color: <?php echo esc_attr($form_bg_color); ?>!important;
        }
        h1 {
            color: <?php echo esc_attr($form_title_color); ?>!important;
        }
    </style>
</head>
<body <?php body_class(); ?>>
    <div class="brro-clb-template-wrapper">
        <div class="brro-clb-template-header">
            <?php
            // Display logo from plugin settings
            if (!empty($logo_url)) {
                echo '<img class="brro-clb-logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . ' Logo">';
            }
            ?>
            <h1>Compost logboek opvragen</h1>
        </div>
        <?php
        // Display a simple form with a email input field and a submit button
        echo '<form method="post" action="">';
        echo '<h2>Logboek opvragen</h2>';
        echo '<p class="clb-description">Vul je emailadres in om je logboek op te vragen.</p>';
        echo '<div class="clb-form-title">Je emailadres</div>';
        echo '<input type="email" id="email" name="email" required>';
        echo '<div style="width:100%;height:24px;"></div>';
        echo '<input type="submit" value="Mijn logboek opvragen">';
        echo '</form>';
        // on submit, redirect to [home url]/?rapport=[email]
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('form').on('submit', function(event) {
                event.preventDefault();
                var email = $('input[name="email"]').val();
                if (email) {
                    window.location.href = '<?php echo esc_url(home_url('/?rapport=')); ?>' + encodeURIComponent(email);
                }
            });
        });
        </script>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

