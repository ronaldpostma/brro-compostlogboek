<?php
/**
 * Template Name: Log Reports Template
 * Custom template for displaying the compost logboek reports
 * 
 * This template is loaded when ?rapport parameter is present in the URL. 
 * It displays only the log reports without the theme header and footer.
 * theme header and footer - a "naked" template with minimal HTML structure.
 */

// Disable admin bar for this template
show_admin_bar(false);

// Get language attribute for html tag
$language = get_bloginfo('language');

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?> logboek rapportage</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('brro-clb-reports'); ?>>
    <div id="fixed-print-button" onclick="window.print()" role="button">Print/PDF</div>
    <div class="brro-clb-template-wrapper">
        <?php
        // Display the report header using the helper function
        if (function_exists('brro_clb_get_report_header')) {
            echo brro_clb_get_report_header();
        }
        // Display the log reports using the reusable function
        if (function_exists('brro_clb_get_rapport')) {
            echo brro_clb_get_rapport();
        } else {
            // Function not available - show error message
            echo '<p>Fout: Het logboek rapport kan niet worden geladen.</p>';
        }
        ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

