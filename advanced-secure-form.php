<?php
/**
 * Plugin Name: InsightX Form
 * Plugin URI:  https://insightx.in.th/
 * Version:     0.5.1
 * Author:      InsightX
 * Author URI:  https://www.insightx.in.th
 * Text Domain: InsightX
 * Description: ระบบฟอร์มและจัดการข้อมูลลูกค้าสำหรับธุรกิจ — สร้างฟอร์มง่าย ส่งอีเมลอัตโนมัติ จัดการข้อมูลครบจบในที่เดียว โดย InsightX
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ISXF_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ISXF_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ISXF_PLUGIN_VERSION', '0.5.1' );
define( 'ISXF_DB_VERSION', '1.0' );

// === GitHub Plugin Update Checker ===
require_once ISXF_PLUGIN_DIR . 'libs/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$isxf_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Douzhebag/ISX-Form-WP-Plugin',
    __FILE__,
    'insightx-form'
);

// ใช้ GitHub Releases เป็นตัวกำหนด version ที่จะอัพเดท
$isxf_update_checker->getVcsApi()->enableReleaseAssets();

register_activation_hook( __FILE__, 'isxf_create_db_table' );

function isxf_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'isxf_form_entries';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        form_id bigint(20) NOT NULL,
        form_title text NOT NULL,
        entry_data longtext NOT NULL,
        user_ip varchar(100) DEFAULT '' NOT NULL,
        entry_status varchar(20) DEFAULT 'new' NOT NULL,
        admin_note text DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    update_option( 'isxf_db_version', ISXF_DB_VERSION );
}

/**
 * Auto-upgrade database when ISXF_DB_VERSION changes.
 * dbDelta() handles ALTER TABLE for adding new columns safely.
 */
function isxf_maybe_upgrade_db() {
    $current = get_option( 'isxf_db_version', '0' );
    if ( version_compare( $current, ISXF_DB_VERSION, '<' ) ) {
        isxf_create_db_table();
    }
    
    // Migration: Copy data from old acf_form_entries if applicable
    global $wpdb;
    $old_table = $wpdb->prefix . 'acf_form_entries';
    $new_table = $wpdb->prefix . 'isxf_form_entries';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_table'" ) === $old_table ) {
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_table'" ) === $new_table ) {
            $new_count = $wpdb->get_var( "SELECT COUNT(*) FROM $new_table" );
            if ( $new_count == 0 ) {
                $wpdb->query( "INSERT INTO $new_table (form_id, form_title, entry_data, user_ip, entry_status, admin_note, created_at) SELECT form_id, form_title, entry_data, user_ip, entry_status, admin_note, created_at FROM $old_table" );
            }
        }
    }
}
add_action( 'admin_init', 'isxf_maybe_upgrade_db' );

register_uninstall_hook( __FILE__, 'isxf_plugin_uninstall' );

/**
 * Load plugin textdomain for translation.
 */
function isxf_load_textdomain() {
    load_plugin_textdomain( 'insightx-form', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'isxf_load_textdomain' );

function isxf_plugin_uninstall() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'isxf_form_entries';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

    $options_to_delete = [
        'isxf_smtp_enable',
        'isxf_smtp_host',
        'isxf_smtp_port',
        'isxf_smtp_user',
        'isxf_smtp_pass',
        'isxf_smtp_secure',
        'isxf_smtp_from_email',
        'isxf_smtp_from_name',
        'isxf_smtp_disable_ssl_verify',
        'isxf_captcha_service',
        'isxf_recaptcha_site_key',
        'isxf_recaptcha_secret_key',
        'isxf_turnstile_site_key',
        'isxf_turnstile_secret_key',
        'isxf_admin_notify_enable',
        'isxf_admin_notify_email',
        'isxf_db_version'
    ];

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }
}

$files_to_load = [
    'includes/class-isxf-crypto.php',
    'includes/class-isxf-admin.php',
    'includes/class-isxf-frontend.php',
    'includes/class-isxf-ajax-handler.php',
    'includes/class-isxf-entries.php'
];

foreach ( $files_to_load as $file ) {
    if ( file_exists( ISXF_PLUGIN_DIR . $file ) ) {
        require_once ISXF_PLUGIN_DIR . $file;
    }
}

/**
 * Custom error logger for InsightX Form
 * 
 * @param mixed $message The message to log.
 */
if ( ! function_exists( 'isxf_log_error' ) ) {
    function isxf_log_error( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[InsightX Form Debug] ' . print_r( $message, true ) );
            } else {
                error_log( '[InsightX Form Debug] ' . $message );
            }
        }
    }
}

function run_advanced_contact_form() {
    if ( class_exists( 'ISXF_Admin' ) ) new ISXF_Admin();
    if ( class_exists( 'ISXF_Frontend' ) ) new ISXF_Frontend();
    if ( class_exists( 'ISXF_AJAX_Handler' ) ) new ISXF_AJAX_Handler();
    if ( class_exists( 'ISXF_Entries' ) ) new ISXF_Entries();
}
run_advanced_contact_form();