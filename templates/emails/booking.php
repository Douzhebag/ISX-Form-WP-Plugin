<?php
/**
 * Email template: hotel booking confirmation (sent to the customer).
 *
 * Extracted from ISXF\AjaxHandler::get_hotel_booking_email_template()
 * (Phase 2.2 view split); receives:
 *
 * @var array  $entry_data   Submitted field label => value pairs.
 * @var string $site_name    Site name (get_bloginfo('name')).
 * @var string $header_style Shared email <style> block (kept for custom bodies).
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
                            <h2 class="heading-primary"><?php esc_html_e( 'Room Booking Confirmed', 'insightx-form' ); ?></h2>
                            <p class="text-body"><?php esc_html_e( 'Thank you for choosing to book with us.', 'insightx-form' ); ?><br><?php esc_html_e( 'We have received your room booking request. Details are as follows:', 'insightx-form' ); ?></p>
                            {all_fields}
                            <div style="margin-top: 22px; padding: 14px 16px; background-color: #e6f2ff; border-left: 4px solid #0079ff; border-radius: 6px;">
                                <p style="margin:0; color:#0f172a; font-size:14px;"><strong><?php esc_html_e( 'Note:', 'insightx-form' ); ?></strong> <?php esc_html_e( 'This booking is only a preliminary request. Our staff will check room availability and contact you again to confirm.', 'insightx-form' ); ?></p>
                            </div>
<?php else : ?>
<?php
ob_start();
?>
                            <p style="margin:0 0 20px;"><?php esc_html_e( 'Thank you for choosing to book with us.', 'insightx-form' ); ?><br><?php esc_html_e( 'We have received your room booking request. Details are as follows:', 'insightx-form' ); ?></p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="width:100%; background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; border-collapse:separate; border-spacing:0;">
                                <?php foreach ( $entry_data as $label => $value ) : ?>
                                    <tr><td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:35%; color:#5a6881; font-weight:600; font-size:14px; vertical-align:top;"><?php echo esc_html( $label ); ?></td><td style="padding:12px 15px; border-bottom:1px solid #e2e8f0; width:65%; color:#0f172a; font-size:15px; vertical-align:top;"><?php echo nl2br( esc_html( $value ) ); ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                            <div style="margin-top:22px; padding:14px 16px; background-color:#e6f2ff; border-left:4px solid #0079ff; border-radius:6px;">
                                <p style="margin:0; color:#0f172a; font-size:14px;"><strong><?php esc_html_e( 'Note:', 'insightx-form' ); ?></strong> <?php esc_html_e( 'This booking is only a preliminary request. Our staff will check room availability and contact you again to confirm.', 'insightx-form' ); ?></p>
                            </div>
<?php
echo \ISXF\Template::get( 'emails/layout', [
    'site_name'    => $site_name,
    'title'        => __( 'Room Booking Confirmed', 'insightx-form' ),
    'subtitle'     => __( 'Booking Confirmation', 'insightx-form' ),
    'icon'         => 'check',
    'content'      => ob_get_clean(),
    'footer_note'  => __( 'If you have any questions, please contact our staff.', 'insightx-form' ),
    'header_style' => $header_style,
] );
?>
<?php endif; ?>