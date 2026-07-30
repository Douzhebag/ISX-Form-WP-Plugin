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
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="margin:0; padding:20px; background-color:#f0f0f1; font-family:sans-serif;">
                <div style="max-width:600px; margin:0 auto; background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px;">
                    <h2 style="color:#23282d; border-bottom:1px solid #eee; padding-bottom:10px;">🔔 <?php
                        /* translators: %s: form title. */
                        echo sprintf( __( 'New Submission: %s', 'insightx-form' ), esc_html( $form_title ) );
                    ?></h2>
                    <p><?php
                        /* translators: %s: site name wrapped in <strong> tags. */
                        echo sprintf( __( 'A new submission has arrived from the website %s', 'insightx-form' ), '<strong>' . esc_html( $site_name ) . '</strong>' );
                    ?></p>
                    
                    <table style="width:100%; border-collapse:collapse; margin-top:15px;">
                        <?php foreach ( $entry_data as $label => $value ) : ?>
                            <tr>
                                <td style="padding:10px; border:1px solid #ddd; background:#f9f9f9; width:35%; font-weight:bold;"><?php echo esc_html($label); ?></td>
                                <td style="padding:10px; border:1px solid #ddd;"><?php echo nl2br(esc_html($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <div style="margin-top:20px; font-size:12px; color:#666; border-top:1px solid #eee; padding-top:10px;">
                        <p><strong><?php esc_html_e( 'System Info:', 'insightx-form' ); ?></strong></p>
                        <ul>
                            <li><strong><?php esc_html_e( 'Date:', 'insightx-form' ); ?></strong> <?php echo esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?></li>
                            <li><strong><?php esc_html_e( 'IP Address:', 'insightx-form' ); ?></strong> <?php echo esc_html($user_ip); ?></li>
                        </ul>
                        <p style="text-align:center; margin-top:20px;">
                            <a href="<?php echo admin_url('edit.php?post_type=isxf_form&page=isxf-entries'); ?>" style="background:#2271b1; color:#fff; padding:10px 20px; text-decoration:none; border-radius:3px;"><?php esc_html_e( 'Log in to manage submissions', 'insightx-form' ); ?></a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            