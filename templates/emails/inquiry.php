<?php
/**
 * Email template: general inquiry confirmation (sent to the customer).
 *
 * Extracted from ISXF\AjaxHandler::get_general_inquiry_email_template()
 * (Phase 2.2 view split); receives:
 *
 * @var array  $entry_data   Submitted field label => value pairs.
 * @var string $site_name    Site name (get_bloginfo('name')).
 * @var string $header_style Shared email <style> block.
 * @var bool   $body_only    When true, render only the inner (editable) body
 *                           markup with merge tags ({all_fields}) instead of
 *                           the full HTML document — used by the form builder's
 *                           "edit from this template" tool so the copied content
 *                           can be wrapped by emails/custom-layout.php without
 *                           double-wrapping the email chrome.
 */
$body_only = ! empty( $body_only );
?>
<?php if ( $body_only ) : ?>
                            <h2 class="heading-primary"><?php esc_html_e( 'We Have Received Your Message', 'insightx-form' ); ?></h2>
                            <p class="text-body"><?php esc_html_e( 'Thank you for contacting us. Our team will review your information and get back to you as soon as possible.', 'insightx-form' ); ?><br><?php esc_html_e( 'The details you submitted are as follows:', 'insightx-form' ); ?></p>
                            {all_fields}
<?php else : ?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php echo $header_style; ?></head>
            <body style="margin:0; padding:0; background-color:#F3F4F6;">
                <br>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr><td align="center"><div class="email-container">
                        <div class="header-bg">
                            <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                            <p class="sub-title"><?php esc_html_e( 'General Inquiry', 'insightx-form' ); ?></p>
                        </div>
                        <div class="content-body">
                            <h2 class="heading-primary"><?php esc_html_e( 'We Have Received Your Message', 'insightx-form' ); ?></h2>
                            <p class="text-body"><?php esc_html_e( 'Thank you for contacting us. Our team will review your information and get back to you as soon as possible.', 'insightx-form' ); ?><br><?php esc_html_e( 'The details you submitted are as follows:', 'insightx-form' ); ?></p>
                            <table class="data-table">
                                <?php foreach ( $entry_data as $label => $value ) : ?>
                                    <tr><td class="data-cell label-cell"><?php echo esc_html($label); ?></td><td class="data-cell value-cell"><?php echo nl2br(esc_html($value)); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <div class="footer"><p class="footer-text"><strong><?php echo esc_html($site_name); ?></strong><br><?php esc_html_e( 'This is an automated message from the website.', 'insightx-form' ); ?><br>&copy; <?php echo wp_date('Y'); ?> <?php esc_html_e( 'All rights reserved.', 'insightx-form' ); ?></p></div>
                    </div></td></tr>
                </table><br>
            </body>
            </html>
<?php endif; ?>