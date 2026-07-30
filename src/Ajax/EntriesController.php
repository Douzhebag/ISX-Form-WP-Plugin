<?php

namespace ISXF\Ajax;

use ISXF\Repository\EntryRepository;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Entry-management endpoints (Phase 2.4 — see docs/ROADMAP.md).
 *
 * Actions: isxf_update_entry_status, isxf_update_entry_note — inline edits
 * from the entries list page.
 * Code moved verbatim out of the former ISXF\AjaxHandler — no behavior change.
 */
class EntriesController extends AbstractAjaxController {

    /**
     * Entries table repository.
     *
     * @var EntryRepository
     */
    private $repo;

    public function __construct() {
        $this->repo = new EntryRepository();
        add_action( 'wp_ajax_isxf_update_entry_status', [ $this, 'handle_update_status' ] );
        add_action( 'wp_ajax_isxf_update_entry_note', [ $this, 'handle_update_note' ] );
    }

    public function handle_update_status() {
        check_ajax_referer( 'isxf_entry_action_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'insightx-form' ) ] );
        }

        $entry_id = intval( $_POST['entry_id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $allowed = [ 'new', 'in_progress', 'done', 'junk' ];

        if ( ! $entry_id || ! in_array( $status, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data', 'insightx-form' ) ] );
        }

        $this->repo->update_status( $entry_id, $status );

        $labels = [
            'new'         => __( 'New', 'insightx-form' ),
            'in_progress' => __( 'In progress', 'insightx-form' ),
            'done'        => __( 'Done', 'insightx-form' ),
            'junk'        => __( 'Junk', 'insightx-form' ),
        ];
        /* translators: %s: status label. */
        wp_send_json_success( [ 'message' => sprintf( __( 'Status updated to "%s"', 'insightx-form' ), $labels[$status] ), 'status' => $status ] );
    }

    public function handle_update_note() {
        check_ajax_referer( 'isxf_entry_action_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'insightx-form' ) ] );
        }

        $entry_id = intval( $_POST['entry_id'] ?? 0 );
        $note = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid data', 'insightx-form' ) ] );
        }

        $this->repo->update_note( $entry_id, $note );

        wp_send_json_success( [ 'message' => __( 'Note saved', 'insightx-form' ) ] );
    }
}
