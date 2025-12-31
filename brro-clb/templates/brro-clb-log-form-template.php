<?php
/**
 * Template Name: Log Form Template
 * Custom template for displaying the compost logboek form
 * 
 * This template is loaded when ?logboek parameter is present in the URL
 * on a singular location post. It displays only the log form without the
 * theme header and footer - a "naked" template with minimal HTML structure.
 */

// Disable admin bar for this template
show_admin_bar(false);

// Get the current post (location post)
global $post;
$location_post_id = $post->ID;

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
            <h1>Compost logboek</h1>
            <?php
            // Check if logboek parameter is set to '1' (form should be shown) or just present (action form should be shown)
            $logboek_param = isset($_GET['logboek']) ? $_GET['logboek'] : '';
            $show_log_form = ($logboek_param === '1');
            $show_action_form = !$show_log_form;
            ?>
            <!-- form to choose the action -->
            <?php if ($show_action_form) : ?>
            <form id="log-action-form" method="post" action="">
                <h2>Welkom!</h2>
                <h3>Wat ga je doen?</h3>
                <div style="display: flex;justify-content: space-between;gap: 20px; flex-direction:row;width: 100%;">
                    <input type="radio" id="log" name="log-action" value="log" style="display: none;">
                    <label class="clb-label-btn wide-btn" for="log">
                        <img src="<?php echo plugins_url('img/noun-stopwatch-8218646.png', dirname(dirname(__FILE__)) . '/brro-clb.php'); ?>" alt="Compost activiteit loggen">
                        Compost activiteit loggen
                    </label>
                    <input type="radio" id="report" name="log-action" value="report" style="display: none;">
                    <label class="clb-label-btn wide-btn" for="report">
                        <img src="<?php echo plugins_url('img/noun-book-7958954.png', dirname(dirname(__FILE__)) . '/brro-clb.php'); ?>" alt="Mijn logboek opvragen">
                        Mijn logboek opvragen
                    </label>
                </div>
                <input type="submit" value="Doorgaan" style="display: block; margin-top: 20px;">
            </form>
            <script>
                jQuery(document).ready(function($) {
                    $('#log-action-form').on('submit', function(event) {
                        event.preventDefault();
                        var logAction = $('input[name="log-action"]:checked').val();
                        if (logAction === 'log') {
                            // Redirect to current URL with ?logboek=1 parameter
                            var currentUrl = window.location.href.split('?')[0];
                            var searchParams = new URLSearchParams(window.location.search);
                            searchParams.set('logboek', '1');
                            window.location.href = currentUrl + '?' + searchParams.toString();
                        } else if (logAction === 'report') {
                            window.location.href = '<?php echo esc_url(home_url('/?logboek-opvragen')); ?>';
                        }
                    });
                });
            </script>
            <?php endif; ?>
        </div>
        <?php
        // Display the log form using the reusable function
        // Only show when ?logboek=1 parameter is present
        if ($show_log_form) {
            if (function_exists('brro_clb_get_log_form')) {
                echo brro_clb_get_log_form($location_post_id);
            } else {
                // Function not available - show error message
                echo '<p>Fout: Het logboek formulier kan niet worden geladen.</p>';
            }
        }
        ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>