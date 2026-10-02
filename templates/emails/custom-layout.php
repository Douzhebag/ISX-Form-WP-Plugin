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
<?php
echo \ISXF\Template::get( 'emails/layout', [
    'site_name'    => $site_name,
    'title'        => $form_title,
    'icon'         => 'check',
    'content'      => $content, // kses'd custom body with merge tags resolved (escaped values)
    'header_style' => $header_style,
] );
