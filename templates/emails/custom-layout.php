<?php
/**
 * Email template: layout wrapper for custom (per-form) email bodies.
 *
 * Extracted from ISXF\AjaxHandler::wrap_in_email_layout()
 * (Phase 2.2 view split); receives:
 *
 * @var string $content      Processed email body (merge tags done, nl2br applied).
 * @var string $site_name    Site name (get_bloginfo('name')).
 * @var string $form_title   Form post title.
 * @var string $header_style Shared email <style> block.
 */
?>
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php echo $header_style; ?></head>
            <body style="margin:0; padding:0; background-color:#F3F4F6;">
                <br>
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr><td align="center"><div class="email-container">
                        <div class="header-bg">
                            <h1 class="site-title"><?php echo esc_html($site_name); ?></h1>
                            <p class="sub-title"><?php echo esc_html($form_title); ?></p>
                        </div>
                        <div class="content-body">
                            <?php echo $content; ?>
                        </div>
                        <div class="footer"><p class="footer-text"><strong><?php echo esc_html($site_name); ?></strong><br>&copy; <?php echo wp_date('Y'); ?> <?php esc_html_e( 'All rights reserved.', 'insightx-form' ); ?></p></div>
                    </div></td></tr>
                </table><br>
            </body>
            </html>
            