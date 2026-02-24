<?php
/**
 * Plugin Name: InsightX Form
 * Plugin URI:  https://insightx.in.th/
 * Version:     0.2.1
 * Author:      InsightX
 * Author URI:  https://www.insightx.in.th
 * Text Domain: InsightX
 * Description: ระบบฟอร์มและจัดการข้อมูลลูกค้าสำหรับธุรกิจ — สร้างฟอร์มง่าย ส่งอีเมลอัตโนมัติ จัดการข้อมูลครบจบในที่เดียว โดย InsightX
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ACF_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ACF_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ACF_PLUGIN_VERSION', '0.2.1' );

// === GitHub Plugin Update Checker ===
require_once ACF_PLUGIN_DIR . 'libs/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$acf_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Douzhebag/ISX-Form-WP-Plugin',
    __FILE__,
    'insightx-form'
);

// ใช้ GitHub Releases เป็นตัวกำหนด version ที่จะอัพเดท
$acf_update_checker->getVcsApi()->enableReleaseAssets();

register_activation_hook( __FILE__, 'acf_create_db_table' );

function acf_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'acf_form_entries';
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
}

register_uninstall_hook( __FILE__, 'acf_plugin_uninstall' );

function acf_plugin_uninstall() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'acf_form_entries';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );

    $options_to_delete = [
        'acf_smtp_enable',
        'acf_smtp_host',
        'acf_smtp_port',
        'acf_smtp_user',
        'acf_smtp_pass',
        'acf_smtp_secure',
        'acf_smtp_from_email',
        'acf_smtp_from_name',
        'acf_captcha_service',
        'acf_recaptcha_site_key',
        'acf_recaptcha_secret_key',
        'acf_turnstile_site_key',
        'acf_turnstile_secret_key'
    ];

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }
}

$files_to_load = [
    'includes/class-acf-admin.php',
    'includes/class-acf-frontend.php',
    'includes/class-acf-ajax-handler.php',
    'includes/class-acf-entries.php'
];

foreach ( $files_to_load as $file ) {
    if ( file_exists( ACF_PLUGIN_DIR . $file ) ) {
        require_once ACF_PLUGIN_DIR . $file;
    }
}

function run_advanced_contact_form() {
    if ( class_exists( 'ACF_Admin' ) ) new ACF_Admin();
    if ( class_exists( 'ACF_Frontend' ) ) new ACF_Frontend();
    if ( class_exists( 'ACF_AJAX_Handler' ) ) new ACF_AJAX_Handler();
    if ( class_exists( 'ACF_Entries' ) ) new ACF_Entries();
}
run_advanced_contact_form();