<?php
/**
 * Email template: shared card layout for every email the plugin sends.
 *
 * Centered white card with an accent bar on top, a round icon, title and
 * accent subtitle, the body, an optional call-to-action button and a quiet
 * footer. Table-based with inline styles so it holds up in Gmail / Outlook
 * (no SVG, no flex — the icon is a character in a coloured circle).
 *
 * @var string $site_name   Site name (get_bloginfo('name')).
 * @var string $title       Main heading (plain text).
 * @var string $subtitle    Accent line under the heading (plain text, optional).
 * @var string $icon        'check' (customer emails) | 'bell' (admin notification).
 * @var string $content     Body HTML — already escaped by the calling template.
 * @var array  $cta         Optional [ 'url' => ..., 'label' => ... ] button.
 * @var string $footer_note Optional plain-text line under the logo in the footer.
 *
 * Footer logo: the theme's Custom Logo (Appearance → Customize → Site Identity),
 * else the Site Icon, else the site name as text. Filter: isxf_email_logo_url.
 * @var string $header_style Shared <style> block (classes used by custom bodies).
 */
$subtitle    = isset( $subtitle ) ? $subtitle : '';
$cta         = isset( $cta ) && is_array( $cta ) ? $cta : [];
$footer_note = isset( $footer_note ) ? $footer_note : '';
$header_style = isset( $header_style ) ? $header_style : '';
$icon_char   = ( isset( $icon ) && $icon === 'bell' ) ? '&#128276;' : '&#10003;';
$font        = "'Noto Sans Thai', 'Sarabun', 'Prompt', 'Helvetica Neue', Helvetica, Arial, sans-serif";

// Footer brand: the theme's custom logo, else the site icon, else the name.
$logo_url = '';
$logo_id  = (int) get_theme_mod( 'custom_logo' );
if ( $logo_id ) {
    $logo_url = (string) wp_get_attachment_image_url( $logo_id, 'medium' );
}
if ( $logo_url === '' ) {
    $logo_url = (string) get_site_icon_url( 128 );
}
$logo_url = apply_filters( 'isxf_email_logo_url', $logo_url );
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php echo $header_style; ?>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; width:100% !important; font-family:<?php echo esc_attr( $font ); ?>;">
    <div style="background-color:#f8fafc; padding:40px 12px; width:100%; box-sizing:border-box;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
            <tr>
                <td align="center">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="max-width:560px; background-color:#ffffff; border-top:4px solid #0079ff; border-radius:8px; box-shadow:0 4px 12px rgba(15, 23, 42, 0.08); overflow:hidden;">
                        <tr>
                            <td style="padding:40px 30px 32px; font-family:<?php echo esc_attr( $font ); ?>;">

                                <table border="0" cellpadding="0" cellspacing="0" align="center" role="presentation" style="margin:0 auto 22px;">
                                    <tr>
                                        <td align="center" valign="middle" width="56" height="56" bgcolor="#e6f2ff" style="width:56px; height:56px; border-radius:50%; background-color:#e6f2ff; color:#0079ff; font-size:28px; font-weight:bold; line-height:56px; text-align:center;"><?php echo $icon_char; ?></td>
                                    </tr>
                                </table>

                                <h1 style="margin:0 0 <?php echo $subtitle !== '' ? 12 : 26; ?>px; color:#0f172a; font-size:28px; font-weight:700; line-height:1.35; text-align:center;"><?php echo esc_html( $title ); ?></h1>
                                <?php if ( $subtitle !== '' ) : ?>
                                    <p style="margin:0 0 26px; color:#0079ff; font-size:15px; font-weight:700; text-align:center;"><?php echo esc_html( $subtitle ); ?></p>
                                <?php endif; ?>

                                <div style="color:#5a6881; font-size:15px; line-height:1.6; text-align:left;">
                                    <?php echo $content; // Escaped by the calling template. ?>
                                </div>

                                <?php if ( ! empty( $cta['url'] ) ) : ?>
                                    <table border="0" cellpadding="0" cellspacing="0" align="center" role="presentation" style="margin:30px auto 0;">
                                        <tr>
                                            <td align="center" bgcolor="#0079ff" style="border-radius:8px; background-color:#0079ff;">
                                                <a href="<?php echo esc_url( $cta['url'] ); ?>" target="_blank" style="display:inline-block; padding:12px 32px; color:#ffffff; font-size:16px; font-weight:700; text-decoration:none; letter-spacing:0.3px;"><?php echo esc_html( $cta['label'] ); ?></a>
                                            </td>
                                        </tr>
                                    </table>
                                <?php endif; ?>

                                <div style="margin-top:36px; padding-top:24px; border-top:1px solid #eeeeee; text-align:center; font-size:14px; color:#94a3b8;">
                                    <?php if ( $logo_url ) : ?>
                                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="40" style="display:inline-block; height:40px; width:auto; max-width:200px; margin:0 0 6px; border:0; outline:none; text-decoration:none;">
                                    <?php else : ?>
                                        <p style="margin:0 0 4px; color:#5a6881; font-weight:700;"><?php echo esc_html( $site_name ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( $footer_note !== '' ) : ?>
                                        <p style="margin:0;"><?php echo esc_html( $footer_note ); ?></p>
                                    <?php endif; ?>
                                    <p style="margin:14px 0 0; font-size:12px; color:#cbd5e1; line-height:1.5;">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?> &middot; <?php esc_html_e( 'All rights reserved.', 'insightx-form' ); ?></p>
                                </div>

                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
