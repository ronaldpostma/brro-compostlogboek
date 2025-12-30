# Brro Compost Logboek

A WordPress plugin for tracking compost activities (adding green-waste and harvesting compost) at appointed locations. The plugin allows users to log their composting activities through a frontend form, stores this data in custom database tables, and provides reporting functionality for administrators and users.

## Overview

The plugin enables compost location management and activity logging. Users can log activities (adding green waste or harvesting compost) at specific locations through a customizable form. The plugin stores all logs in a dedicated database table with privacy protection (encrypted email addresses). Administrators can generate reports filtered by location, date range, and taxonomy terms. Users can also request their personal log history by entering their email address in a front end form.

The plugin supports two location management modes: using the plugin's built-in custom post type for compost locations, or integrating with an existing custom post type already configured on the site.

## Main Features

- **Activity Logging**: Frontend form for logging compost activities (groenafval toevoegen / compost oogsten) with flexible unit measurement (kg, liter, or custom units)
- **Location Management**: Built-in custom post type for compost locations, or integration with existing post types
- **Privacy Protection**: Email addresses are encrypted before database storage using AES-256-CBC encryption
- **Reporting**: Generate comprehensive reports filtered by location, date range, and taxonomy terms
- **User Reports**: Users can request their personal log history via email address
- **Customizable Units**: Configure custom measurement units (e.g., emmer, kruiwagen) with average weights for accurate reporting
- **Admin Interface**: WordPress admin pages for viewing logs, managing reports, and configuring settings

## File Structure

### Core Plugin File

**brro-clb.php**  
Main plugin file that initializes the plugin and loads all required components. Handles asset enqueuing (CSS and JavaScript) conditionally based on URL parameters. Creates the WordPress admin menu structure with submenu pages for logs, locations, reports, and settings. Implements template loading filters to serve custom templates for log forms, reports, and user request forms when specific URL parameters are present. Adds "logboek" action links to location post row actions in the admin.

---

### PHP Files (php/)

**brro-clb-database.php**  
Manages database table creation and maintenance for logs and reports. Defines the schema for two custom tables: `brro_clb_logs` (stores all activity entries) and `brro_clb_reports` (stores saved report configurations). Handles database version tracking and automatic schema migrations when the plugin is updated. Ensures tables exist on plugin activation and during admin initialization.

**brro-clb-settings.php**  
Provides the settings page interface in the WordPress admin. Allows configuration of location management mode (plugin CPT or existing CPT), custom units (input and output units with icons and weights), volume-to-weight conversions, and visual styling options (logo URL, background colors, title colors). Handles settings validation and sanitization before saving to the database.

**brro-clb-locations.php**  
Conditionally registers the compost locations custom post type based on plugin settings. Supports two modes: active mode (when using plugin's built-in locations) and legacy/disabled mode (when using existing CPT, but keeping legacy posts addressable). Implements redirects for legacy mode to prevent access to old CPT endpoints. Handles detection of existing posts to determine registration behavior.

**brro-clb-email.php**  
Email security and retrieval functions used by both logging and reports modules. Provides email encryption (AES-256-CBC) and decryption functions for privacy protection, email hashing (SHA256) for efficient indexed database searches, email masking for privacy-safe display, and a function to retrieve logs by email address. All email functions use WordPress authentication salt for encryption keys, ensuring unique keys per WordPress installation.

**brro-clb-logging-admin.php**  
Admin interface for viewing logs. Displays all log entries in a table format with masked email addresses for privacy. Shows log ID, date, time, location, activity type, weight, masked email, and device ID. Uses email decryption and masking functions from the email module.

**brro-clb-logging-form.php**  
Frontend form generation and submission handling. Processes form POST data, validates required fields, encrypts and hashes email addresses before saving, and stores log entries in the database. Generates the reusable log form HTML that supports various unit types (kg, liter, custom units) and calculates weights dynamically based on configured settings. Handles success/error messages and redirects to prevent duplicate submissions.

**brro-clb-reports-admin.php**  
Provides the admin interface for creating and managing reports. Includes helper functions to get the post type and taxonomy for locations based on plugin settings. Contains the form for generating new reports with filters for locations (all, specific locations, or taxonomy terms) and date ranges (all time or specific period). Handles report form submission, validates input, and saves report configurations to the database. Displays saved reports in a table format with links to view them on the frontend.

**brro-clb-reports-data.php**  
Handles data retrieval, processing, and statistics calculation for reports. Builds device-email mappings to identify which device IDs belong to which email users for accurate user counting. Counts unique users from logs using email-priority logic. Retrieves logs from the database based on report criteria (date range, locations, taxonomy filters), calculates statistics (total activities, weights, unique users), and returns structured data for display.

**brro-clb-reports-display.php**  
Generates frontend report HTML output. Handles both report ID-based reports and email-based user reports. Groups logs by location or taxonomy categories, formats statistics and data for display, and renders complete HTML output including summary sections, per-category breakdowns, per-location breakdowns, and complete log tables. Includes the report header generation function that displays logo and site name.

---

### Template Files (templates/)

**brro-clb-log-form-template.php**  
Custom template loaded when `?logboek` parameter is present on a location post. Displays a minimal HTML structure (without theme header/footer) showing either a welcome screen with action selection (log activity or request logbook) or the full log form. Uses plugin settings for logo display and background/title color customization.

**brro-clb-log-reports-template.php**  
Custom template loaded when `?rapport` parameter is present on the front page. Displays the log reports in a minimal HTML structure (without theme header/footer). Includes a print/PDF button. Renders the report content generated by the reports functionality, showing statistics, grouped logs, and complete log tables.

**brro-clb-log-user-requestform-template.php**  
Custom template loaded when `?logboek-opvragen` parameter is present on the front page. Displays a form where users can enter their email address to request their personal log history. Uses plugin settings for logo display and styling. Generates a report link based on the submitted email address.

---

### Assets

**css/brro-clb-style.css**  
Stylesheet containing CSS for the log form, reports display, and template layouts. Provides styling for form elements, buttons, tables, and responsive design.

**js/brro-clb-script.js**  
JavaScript file handling form interactions, unit calculations (converting between kg, liter, and custom units), device ID generation/storage, email saving to local storage, and dynamic form behavior based on selected actions and units.

**img/**  
Image assets including icons for units (kg, liter) and activity types (trash for groenafval, compost icon for harvesting).

