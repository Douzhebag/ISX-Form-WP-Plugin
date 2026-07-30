<?php
/**
 * Regression test: Entries must not translate anything during construction.
 *
 * The plugin bootstraps on plugins_loaded (before init). Translating in a
 * constructor makes WordPress's just-in-time loader cache a NOOP translation
 * for the whole 'insightx-form' domain before the textdomain path is
 * registered at init — the entire plugin then renders English even on
 * Thai-locale sites (bug reported on staging, fixed by lazy status_map).
 *
 * @package InsightX_Form\Tests
 */

// Translation stubs return the msgid unchanged (English source). Must be
// declared in the plugin's own namespace: Entries calls __() unqualified
// from namespace ISXF.
namespace ISXF {
    if ( ! function_exists( 'ISXF\__' ) ) {
        // phpcs:ignore WordPress.WP.I18n
        function __( $text, $domain = 'default' ) {
            return $text;
        }
    }
}

namespace ISXF\Tests\Unit {

use ISXF\Entries;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ISXF\Entries
 */
class EntriesLazyStatusMapTest extends TestCase {

    public function test_constructor_does_not_build_status_map(): void {
        $entries = new Entries();

        $prop = new \ReflectionProperty( Entries::class, 'status_map' );

        $this->assertNull(
            $prop->getValue( $entries ),
            'status_map must stay null after construction — building it in the constructor translates before init and breaks the whole textdomain'
        );
    }

    public function test_status_map_is_built_lazily_with_all_statuses(): void {
        $entries = new Entries();

        $method = new \ReflectionMethod( Entries::class, 'get_status_map' );
        $map = $method->invoke( $entries );

        $this->assertSame( [ 'new', 'in_progress', 'done', 'junk' ], array_keys( $map ) );
        foreach ( $map as $status ) {
            $this->assertArrayHasKey( 'label', $status );
            $this->assertArrayHasKey( 'color', $status );
            $this->assertArrayHasKey( 'bg', $status );
            $this->assertArrayHasKey( 'icon', $status );
        }

        // Second call returns the memoized map.
        $this->assertSame( $map, $method->invoke( $entries ) );
    }
}

}
