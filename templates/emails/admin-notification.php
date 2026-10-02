<?php
/**
 * Email template: admin notification of a new submission.
 *
 * Extracted from ISXF\AjaxHandler::get_admin_notification_template()
 * (Phase 2.2 view split); receives:
 *
 * @var array  $entry_data Submitted field label => value pairs.
 * @var string $site_name  Site name (get_bloginfo('name')).
 * @var string $form_title Form post title.
 * @var string $user_ip    Submitter IP address.
 */
?>
<?php
ob_start();
?>
                            <p style="margin:0 0 20px;"><?php
                                /* translators: %s: site name wrapped in <strong> tags. */
                                echo sprintf( __( 'A new submission has arrived from the website %s', 'insightx-form' ), '<strong>' . esc_html( $site_name ) . '</strong>' );
                            ?></p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="width:100%; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; border-spacing:0;">
                                <?php foreach ( $entry_data as $label => $value ) : ?>
                                    <tr><td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:35%; color:#5a6881; font-weight:600; font-size:14px; vertical-align:top;"><?php echo esc_html( $label ); ?></td><td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:65%; color:#0f172a; font-size:15px; vertical-align:top;"><?php echo nl2br( esc_html( $value ) ); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                            <p style="margin:18px 0 0; font-size:13px; color:#94a3b8;">
                                <strong><?php esc_html_e( 'Date:', 'insightx-form' ); ?></strong> <?php echo esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?>
                                &nbsp;&middot;&nbsp;
                                <strong><?php esc_html_e( 'IP Address:', 'insightx-form' ); ?></strong> <?php echo esc_html( $user_ip ); ?>
                            </p>
<?php
echo \ISXF\Template::get( 'emails/layout', [
    'site_name'   => $site_name,
    /* translators: %s: form title. */
    'title'       => sprintf( __( 'New Submission: %s', 'insightx-form' ), $form_title ),
    'icon'        => 'bell',
    'content'     => ob_get_clean(),
    'cta'         => [
        'url'   => admin_url( 'edit.php?post_type=isxf_form&page=isxf-entries' ),
        'label' => __( 'Log in to manage submissions', 'insightx-form' ),
    ],
] );
