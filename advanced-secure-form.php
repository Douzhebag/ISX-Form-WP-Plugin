<?php
/**
 * Plugin Name:       InsightX Form
 * Plugin URI:        https://insightx.in.th/
 * Version:           0.8.0
 * Author:            InsightX
 * Author URI:        https://www.insightx.in.th
 * Text Domain:       insightx-form
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Description: ระบบฟอร์มและจัดการข้อมูลลูกค้าสำหรับธุรกิจ — สร้างฟอร์มง่าย ส่งอีเมลอัตโนมัติ จัดการข้อมูลครบจบในที่เดียว โดย InsightX
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ISXF_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ISXF_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ISXF_PLUGIN_VERSION', '0.8.0' );
define( 'ISXF_DB_VERSION', '1.1' );

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
    $table_name = \ISXF\Repository\EntryRepository::table_name();
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
        PRIMARY KEY  (id),
        KEY form_id (form_id),
        KEY entry_status (entry_status),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    update_option( 'isxf_db_version', ISXF_DB_VERSION );
}

/**
 * Auto-upgrade database when ISXF_DB_VERSION changes.
 * dbDelta() handles ALTER TABLE for adding new columns/indexes safely.
 */
function isxf_maybe_upgrade_db() {
    $current = get_option( 'isxf_db_version', '0' );
    if ( version_compare( $current, ISXF_DB_VERSION, '<' ) ) {
        isxf_create_db_table();
    }

    // Migration legacy acf→isxf แบบ one-shot — รันครั้งเดียวเท่านั้น
    // (เดิมรันทุก admin page load และ REPLACE แบบ prefix อาจชน meta ของปลั๊กอินอื่น)
    if ( ! get_option( 'isxf_legacy_acf_migration_done' ) ) {
        isxf_run_legacy_acf_migration();
        update_option( 'isxf_legacy_acf_migration_done', 1 );
    }
}
add_action( 'admin_init', 'isxf_maybe_upgrade_db' );

/**
 * One-shot legacy migration from the old "acf" naming to "isxf".
 * Idempotent — safe to run on sites where migration already happened.
 */
function isxf_run_legacy_acf_migration() {
    global $wpdb;
    $old_table = $wpdb->prefix . 'acf_form_entries';
    $new_table = \ISXF\Repository\EntryRepository::table_name();

    // Migration: Copy data from old acf_form_entries if applicable
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_table'" ) === $old_table ) {
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_table'" ) === $new_table ) {
            $new_count = $wpdb->get_var( "SELECT COUNT(*) FROM $new_table" );
            if ( $new_count == 0 ) {
                $wpdb->query( "INSERT INTO $new_table (form_id, form_title, entry_data, user_ip, entry_status, admin_note, created_at) SELECT form_id, form_title, entry_data, user_ip, entry_status, admin_note, created_at FROM $old_table" );
                \ISXF\Repository\EntryRepository::bump_cache_version();
            }
        }
    }

    // Migration: Update Custom Post Type from acf_form to isxf_form
    $wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'isxf_form' WHERE post_type = 'acf_form'" );

    // Migration: เปลี่ยนชื่อเฉพาะ meta key ที่ปลั๊กอินนี้ใช้จริง (whitelist)
    // ห้าม REPLACE แบบ prefix '_acf_' เปล่าๆ เพราะอาจชน meta ของปลั๊กอินอื่น
    $meta_key_map = [
        '_acf_form_fields'        => '_isxf_form_fields',
        '_acf_form_email_type'    => '_isxf_form_email_type',
        '_acf_form_email_subject' => '_isxf_form_email_subject',
        '_acf_form_email_body'    => '_isxf_form_email_body',
    ];
    foreach ( $meta_key_map as $old_key => $new_key ) {
        $wpdb->update( $wpdb->postmeta, [ 'meta_key' => $new_key ], [ 'meta_key' => $old_key ] );
    }

    // Migration: Migrate Options from acf_ to isxf_ (SMTP Settings, CAPTCHA, DB version)
    $wpdb->query( "UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, 'acf_', 'isxf_') WHERE option_name LIKE 'acf_smtp_%' OR option_name LIKE 'acf_recaptcha_%' OR option_name LIKE 'acf_turnstile_%' OR option_name = 'acf_captcha_service' OR option_name = 'acf_admin_notify_enable' OR option_name = 'acf_admin_notify_email' OR option_name = 'acf_db_version'" );
}

register_uninstall_hook( __FILE__, 'isxf_plugin_uninstall' );

/**
 * Load plugin textdomain for translation.
 */
function isxf_load_textdomain() {
    load_plugin_textdomain( 'insightx-form', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // plugin_basename() only works when the plugin lives under
    // wp-content/plugins. When it is loaded from elsewhere (test suites,
    // symlinked/standalone checkouts) the path above resolves wrong, so
    // point the textdomain registry at the real languages dir directly.
    global $wp_textdomain_registry;
    if ( $wp_textdomain_registry instanceof WP_Textdomain_Registry && method_exists( $wp_textdomain_registry, 'set_custom_path' ) ) {
        $wp_textdomain_registry->set_custom_path( 'insightx-form', ISXF_PLUGIN_DIR . 'languages' );
    }
}
add_action( 'init', 'isxf_load_textdomain' );

function isxf_plugin_uninstall() {
    global $wpdb;

    $table_name = \ISXF\Repository\EntryRepository::table_name();
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
        'isxf_smtp_auth_method',
        'isxf_smtp_oauth_client_id',
        'isxf_smtp_oauth_client_secret',
        'isxf_smtp_oauth_refresh_token',
        'isxf_smtp_oauth_tenant',
        'isxf_smtp_oauth_connected',
        'isxf_captcha_service',
        'isxf_captcha_required',
        'isxf_recaptcha_site_key',
        'isxf_recaptcha_secret_key',
        'isxf_turnstile_site_key',
        'isxf_turnstile_secret_key',
        'isxf_trusted_proxies',
        'isxf_admin_notify_enable',
        'isxf_admin_notify_email',
        'isxf_db_version',
        'isxf_legacy_acf_migration_done'
    ];

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }

    // ลบโพสต์ CPT isxf_form ทั้งหมด (รวม revision/trash)
    $form_posts = get_posts( [
        'post_type'      => 'isxf_form',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );
    foreach ( $form_posts as $form_post_id ) {
        wp_delete_post( $form_post_id, true );
    }

    // ลบ post meta ของปลั๊กอินที่อาจหลงเหลือ
    $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '" . $wpdb->esc_like( '_isxf_' ) . "%'" );

    // ลบ transients ของปลั๊กอิน (rate limit + OAuth)
    delete_transient( 'isxf_oauth_access_token' );
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like( '_transient_isxf_limit_' ) . "%'" .
        " OR option_name LIKE '" . $wpdb->esc_like( '_transient_timeout_isxf_limit_' ) . "%'" .
        " OR option_name LIKE '" . $wpdb->esc_like( '_transient_isxf_oauth_state_' ) . "%'" .
        " OR option_name LIKE '" . $wpdb->esc_like( '_transient_timeout_isxf_oauth_state_' ) . "%'"
    );

    // ลบ user meta ของการ dismiss admin notice (legacy deprecation notice)
    delete_metadata( 'user', 0, 'isxf_dismissed_legacy_deprecation_notice', '', true );
}

/**
 * PSR-4 autoloader for the ISXF\ namespace (src/).
 *
 * The plugin is deployed as a raw git checkout via plugin-update-checker
 * (no composer install on production), so this must NOT depend on
 * vendor/autoload.php. Maps ISXF\Foo → src/Foo.php and
 * ISXF\Sub\Foo → src/Sub/Foo.php.
 */
spl_autoload_register( function ( $class_name ) {
    if ( strpos( $class_name, 'ISXF\\' ) !== 0 ) return;
    $path = ISXF_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, 5 ) ) . '.php';
    if ( file_exists( $path ) ) require_once $path;
} );

// Backward compatibility: keep the old global class names working for any
// third-party/custom code referencing them.
class_alias( \ISXF\Crypto::class, 'ISXF_Crypto' );
class_alias( \ISXF\OAuth::class, 'ISXF_OAuth' );
class_alias( \ISXF\Admin::class, 'ISXF_Admin' );
class_alias( \ISXF\Frontend::class, 'ISXF_Frontend' );
class_alias( \ISXF\AjaxHandler::class, 'ISXF_AJAX_Handler' );
class_alias( \ISXF\Entries::class, 'ISXF_Entries' );
// Only defined when PHPMailer's OAuthTokenProvider interface is available.
if ( class_exists( \ISXF\OAuthTokenProvider::class ) ) {
    class_alias( \ISXF\OAuthTokenProvider::class, 'ISXF_OAuth_Token_Provider' );
}

/**
 * Convert a WP-local date/datetime string (e.g. the entries table's
 * created_at column, stored via current_datetime()->format('Y-m-d H:i:s'))
 * to a Unix timestamp, interpreting the string in the site timezone.
 *
 * @param string $datetime Local date/datetime string ('Y-m-d' or 'Y-m-d H:i:s').
 * @return int Unix timestamp.
 */
if ( ! function_exists( 'isxf_local_datetime_to_timestamp' ) ) {
    function isxf_local_datetime_to_timestamp( $datetime ) {
        return ( new DateTimeImmutable( $datetime, wp_timezone() ) )->getTimestamp();
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

/**
 * Bootstrap the plugin: instantiate the feature classes.
 *
 * Hooked on plugins_loaded (priority 10) instead of running at file load,
 * so classes are only created once WordPress is ready.
 */
function isxf_bootstrap() {
    new \ISXF\OAuth();
    new \ISXF\Admin();
    new \ISXF\Frontend();
    new \ISXF\AjaxHandler();
    new \ISXF\Entries();
}
add_action( 'plugins_loaded', 'isxf_bootstrap', 10 );