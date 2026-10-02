<?php
/**
 * Integration tests for the entries AJAX endpoints
 * (isxf_update_entry_status / isxf_update_entry_note).
 *
 * Covers: nonce and manage_options gates, the status allow-list (a value
 * outside it never reaches the database), and that a note is stored
 * sanitized.
 *
 * Requires the WordPress test suite + MySQL (skipped otherwise).
 *
 * @package InsightX_Form\Tests\Integration
 */

use ISXF\Repository\EntryRepository;

if ( ! class_exists( 'WP_Ajax_UnitTestCase' ) ) {
    return; // WP test suite not loaded — see tests/bootstrap.php.
}

/**
 * @group ajax
 * @group entries
 */
class EntriesAjaxTest extends WP_Ajax_UnitTestCase {

    /** @var int */
    private $entry_id;

    public function set_up() {
        parent::set_up();
        isxf_create_db_table();

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

        $form_id = self::factory()->post->create( [ 'post_type' => 'isxf_form', 'post_status' => 'publish' ] );
        ( new EntryRepository() )->insert_submission( [ 'Name' => 'Tester' ], $form_id, '192.0.2.10' );
        global $wpdb;
        $this->entry_id = (int) $wpdb->insert_id;
    }

    public function tear_down() {
        $_POST    = [];
        $_REQUEST = [];
        parent::tear_down();
    }

    private function handle_ajax( string $action ): array {
        try {
            $this->_handleAjax( $action );
        } catch ( WPAjaxDieContinueException $e ) {
            // wp_send_json_success()
        } catch ( WPAjaxDieStopException $e ) {
            // wp_send_json_error()
        }
        $response = json_decode( $this->_last_response, true );
        $this->assertIsArray( $response, 'Response should be JSON: ' . $this->_last_response );
        return $response;
    }

    private function row(): object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EntryRepository::table_name() . ' WHERE id = %d', $this->entry_id ) );
    }

    public function test_status_update_requires_nonce(): void {
        $_POST = [ 'entry_id' => $this->entry_id, 'status' => 'done' ];

        $caught = null;
        try {
            $this->_handleAjax( 'isxf_update_entry_status' );
        } catch ( WPAjaxDieStopException $e ) {
            $caught = $e;
        }

        $this->assertNotNull( $caught, 'check_ajax_referer must die without a nonce' );
        $this->assertSame( 'new', $this->row()->entry_status );
    }

    public function test_status_update_rejects_non_admin(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_entry_action_nonce' ), 'entry_id' => $this->entry_id, 'status' => 'done' ];

        $response = $this->handle_ajax( 'isxf_update_entry_status' );

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'new', $this->row()->entry_status, 'An editor must not change entry status' );
    }

    public function test_status_outside_allow_list_is_rejected(): void {
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_entry_action_nonce' ), 'entry_id' => $this->entry_id, 'status' => '<img src=x onerror=alert(1)>' ];

        $response = $this->handle_ajax( 'isxf_update_entry_status' );

        $this->assertFalse( $response['success'] );
        $this->assertSame( 'new', $this->row()->entry_status );
    }

    public function test_status_update_is_saved(): void {
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_entry_action_nonce' ), 'entry_id' => $this->entry_id, 'status' => 'in_progress' ];

        $response = $this->handle_ajax( 'isxf_update_entry_status' );

        $this->assertTrue( $response['success'] );
        $this->assertSame( 'in_progress', $response['data']['status'] );
        $this->assertSame( 'in_progress', $this->row()->entry_status );
    }

    public function test_note_requires_admin(): void {
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_entry_action_nonce' ), 'entry_id' => $this->entry_id, 'note' => 'hello' ];

        $response = $this->handle_ajax( 'isxf_update_entry_note' );

        $this->assertFalse( $response['success'] );
        $this->assertSame( '', $this->row()->admin_note );
    }

    public function test_note_is_saved_sanitized(): void {
        $_POST = [
            'nonce'    => wp_create_nonce( 'isxf_entry_action_nonce' ),
            'entry_id' => $this->entry_id,
            'note'     => "Called the customer <script>alert(1)</script>\nline two",
        ];

        $response = $this->handle_ajax( 'isxf_update_entry_note' );

        $this->assertTrue( $response['success'] );
        $note = $this->row()->admin_note;
        $this->assertStringNotContainsString( '<script>', $note );
        $this->assertStringContainsString( 'Called the customer', $note );
        $this->assertStringContainsString( "\n", $note, 'Line breaks are kept (sanitize_textarea_field)' );
    }

    public function test_note_without_entry_id_is_rejected(): void {
        $_POST = [ 'nonce' => wp_create_nonce( 'isxf_entry_action_nonce' ), 'note' => 'orphan' ];

        $response = $this->handle_ajax( 'isxf_update_entry_note' );

        $this->assertFalse( $response['success'] );
    }
}
