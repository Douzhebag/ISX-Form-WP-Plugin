<?php

namespace ISXF;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Minimal view loader for the plugin's templates/ directory.
 *
 * Deliberately NOT a template engine: a template is a plain PHP file that
 * is include()d with the given $args extract()ed into local scope. Escaping
 * stays in the templates, exactly where it was before the view split
 * (Phase 2.2 — see docs/ROADMAP.md).
 *
 * Template names are relative paths without extension, e.g.
 * 'admin/settings-page' resolves to templates/admin/settings-page.php.
 */
class Template {

    /**
     * Resolve a template name to an absolute path inside templates/.
     *
     * @param string $template Template name (no '.php' suffix; '/' as separator).
     * @return string Absolute file path, or '' when invalid/missing.
     */
    private static function locate( $template ) {
        $name = str_replace( '\\', '/', (string) $template );

        // Path traversal guard: no '..', no null bytes, no leading separators.
        if ( $name === '' || strpos( $name, "\0" ) !== false || strpos( $name, '..' ) !== false ) {
            return '';
        }

        $path = ISXF_PLUGIN_DIR . 'templates/' . ltrim( $name, '/' ) . '.php';
        return file_exists( $path ) ? $path : '';
    }

    /**
     * Render a template and return the output.
     *
     * @param string $template Template name (see locate()).
     * @param array  $args     Variables made available to the template.
     * @return string Rendered output ('' when the template cannot be resolved).
     */
    public static function get( $template, $args = [] ) {
        $path = self::locate( $template );
        if ( $path === '' ) {
            isxf_log_error( 'Template not found or invalid: ' . $template );
            return '';
        }

        if ( is_array( $args ) && ! empty( $args ) ) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- A template loader exists precisely to extract $args into template scope (mirrors wp-includes/load_template()); EXTR_SKIP prevents overwriting $template/$args/$path.
            extract( $args, EXTR_SKIP );
        }

        ob_start();
        include $path;
        return ob_get_clean();
    }

    /**
     * Render a template and echo the output.
     *
     * @param string $template Template name (see locate()).
     * @param array  $args     Variables made available to the template.
     */
    public static function render( $template, $args = [] ) {
        echo self::get( $template, $args );
    }
}
