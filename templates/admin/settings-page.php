<?php
/**
 * Template: Global settings page (wp-admin → แบบฟอร์ม → ⚙️ ตั้งค่าระบบ).
 *
 * Extracted from ISXF\Admin::render_settings_page() (Phase 2.2 view split).
 * Data gathering stays in the render method; this file receives:
 *
 * @var array  $opt                Option-name map (key => wp option name).
 * @var array  $v                  Option-value map (same keys where applicable).
 * @var string $service            Active captcha service ('google'|'cloudflare').
 * @var bool   $captcha_configured Whether the active captcha service has keys.
 * @var string $oauth_notice       Sanitized ?isxf_oauth= query arg ('' when absent).
 * @var string $oauth_error_msg    Escaped OAuth error message (or default text).
 * @var string $admin_email        Site admin email.
 * @var string $auth_method        SMTP auth method option value.
 * @var bool   $is_oauth           Whether an OAuth2 auth method is selected.
 * @var string $redirect_uri       OAuth redirect URI.
 * @var string $connected_email    Connected OAuth account email ('' if none).
 * @var bool   $is_connected       Whether an OAuth account is connected.
 * @var string $connect_url        Nonce'd OAuth connect URL.
 * @var string $disconnect_url     Nonce'd OAuth disconnect URL.
 */
?>
            <div class="wrap">
                <h1><?php esc_html_e( '⚙️ Global System Settings', 'insightx-form' ); ?></h1>
                <?php settings_errors(); ?>
                <?php if ( $oauth_notice === 'connected' ) : ?>
                    <div class="notice notice-success is-dismissible" style="padding:12px 16px;"><p style="margin:0;"><?php esc_html_e( '✅ OAuth account connected successfully', 'insightx-form' ); ?></p></div>
                <?php elseif ( $oauth_notice === 'disconnected' ) : ?>
                    <div class="notice notice-info is-dismissible" style="padding:12px 16px;"><p style="margin:0;"><?php esc_html_e( 'ℹ️ OAuth account disconnected', 'insightx-form' ); ?></p></div>
                <?php elseif ( $oauth_notice === 'error' ) : ?>
                    <div class="notice notice-error is-dismissible" style="padding:12px 16px;"><p style="margin:0;">❌ <?php esc_html_e( 'OAuth connection failed:', 'insightx-form' ); ?> <?php echo $oauth_error_msg; ?></p></div>
                <?php endif; ?>
                <?php if ( ! $captcha_configured ) : ?>
                    <div class="notice notice-warning" style="border-left-color:#dba617; padding:12px 16px;">
                        <p style="margin:0; font-size:13px;">⚠️ <strong><?php esc_html_e( 'CAPTCHA is not configured', 'insightx-form' ); ?></strong> — <?php esc_html_e( 'All forms will have no bot protection. Please configure the Site Key and Secret Key below to enable it.', 'insightx-form' ); ?></p>
                    </div>
                <?php endif; ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'isxf_global_group' ); ?>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
                        
                        <div style="flex: 1; min-width: 400px; display: flex; flex-direction: column; gap: 20px;">
                            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px; border-left: 4px solid #2271b1;">
                                <h3><?php esc_html_e( '🔔 Admin Notification', 'insightx-form' ); ?></h3>
                                <label style="display:block; margin-bottom:10px;">
                                    <input type="checkbox" name="<?php echo $opt['admin_notify_enable']; ?>" value="yes" <?php checked($v['admin_notify_enable'], 'yes'); ?>> 
                                    <strong><?php esc_html_e( 'Enable admin email notifications', 'insightx-form' ); ?></strong>
                                </label>
                                <p class="description" style="margin-bottom:5px;"><?php
                                    /* translators: %s: fallback admin email address wrapped in <code> tags. */
                                    printf( esc_html__( 'Recipient email (if left empty, emails will be sent to: %s)', 'insightx-form' ), '<code>' . $admin_email . '</code>' );
                                ?></p>
                                <input type="text" name="<?php echo $opt['admin_notify_email']; ?>" value="<?php echo esc_attr($v['admin_notify_email']); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. admin@example.com', 'insightx-form' ); ?>">
                            </div>

                            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px;">
                                <h3><?php esc_html_e( '🛡️ Security Settings (Captcha)', 'insightx-form' ); ?></h3>
                                <select name="<?php echo $opt['captcha_service']; ?>" id="captcha_select" style="width: 100%; margin-bottom: 20px;">
                                    <option value="google" <?php selected($service, 'google'); ?>>Google reCAPTCHA v3</option>
                                    <option value="cloudflare" <?php selected($service, 'cloudflare'); ?>>Cloudflare Turnstile</option>
                                </select>
                                <div id="settings_google" style="<?php echo $service !== 'google' ? 'display:none;' : ''; ?>">
                                    <table class="form-table">
                                        <tr><th>Site Key</th><td><input type="text" name="<?php echo $opt['recaptcha_site']; ?>" value="<?php echo esc_attr($v['recaptcha_site']); ?>" class="regular-text"></td></tr>
                                        <tr><th>Secret Key</th><td><input type="password" name="<?php echo $opt['recaptcha_secret']; ?>" value="<?php echo esc_attr($v['recaptcha_secret']); ?>" class="regular-text"></td></tr>
                                    </table>
                                </div>
                                <div id="settings_cloudflare" style="<?php echo $service !== 'cloudflare' ? 'display:none;' : ''; ?>">
                                    <table class="form-table">
                                        <tr><th>Site Key</th><td><input type="text" name="<?php echo $opt['turnstile_site']; ?>" value="<?php echo esc_attr($v['turnstile_site']); ?>" class="regular-text"></td></tr>
                                        <tr><th>Secret Key</th><td><input type="password" name="<?php echo $opt['turnstile_secret']; ?>" value="<?php echo esc_attr($v['turnstile_secret']); ?>" class="regular-text"></td></tr>
                                    </table>
                                </div>
                                <label style="display:block; margin-top:15px;">
                                    <input type="checkbox" name="<?php echo $opt['captcha_required']; ?>" value="yes" <?php checked($v['captcha_required'], 'yes'); ?>>
                                    <strong><?php esc_html_e( 'Block form submissions when CAPTCHA is not configured (recommended)', 'insightx-form' ); ?></strong>
                                </label>
                                <p class="description" style="margin:5px 0 0;"><?php esc_html_e( 'When enabled, if the selected CAPTCHA service has no Secret Key, all form submissions will be rejected instead of allowed through — disable this only if you intentionally use forms without CAPTCHA.', 'insightx-form' ); ?></p>
                            </div>

                            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px;">
                                <h3><?php esc_html_e( '🌐 Trusted Proxies (for sites behind a Reverse Proxy / CDN)', 'insightx-form' ); ?></h3>
                                <p class="description" style="margin-bottom:10px;"><?php
                                    /* translators: %1$s: CF-Connecting-IP header name wrapped in <code> tags, %2$s: X-Forwarded-For header name wrapped in <code> tags. */
                                    printf( esc_html__( 'Enter the IPs or CIDRs of trusted proxies (e.g. Cloudflare, Nginx reverse proxy), one per line — the real user IP is only read from the %1$s / %2$s header when the request comes from these proxies.', 'insightx-form' ), '<code>CF-Connecting-IP</code>', '<code>X-Forwarded-For</code>' );
                                ?> <strong><?php esc_html_e( 'Leave empty = trust REMOTE_ADDR only (default, safest)', 'insightx-form' ); ?></strong></p>
                                <textarea name="<?php echo $opt['trusted_proxies']; ?>" rows="4" class="large-text code" placeholder="173.245.48.0/20&#10;103.21.244.0/22"><?php echo esc_textarea($v['trusted_proxies']); ?></textarea>
                            </div>
                        </div>

                        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px;">
                            <?php /* OAuth URLs/state are gathered in Admin::render_settings_page(). */ ?>
                            <h3><?php esc_html_e( '📧 Email Settings (SMTP)', 'insightx-form' ); ?></h3>
                            <label><input type="checkbox" name="<?php echo $opt['smtp_enable']; ?>" value="yes" <?php checked($v['smtp_enable'], 'yes'); ?>> <?php esc_html_e( 'Enable SMTP', 'insightx-form' ); ?></label>

                            <table class="form-table">
                                <tr><th><?php esc_html_e( 'Authentication Method', 'insightx-form' ); ?></th><td>
                                    <select name="<?php echo $opt['smtp_auth_method']; ?>" id="isxf_auth_method" style="width:100%; max-width:360px;">
                                        <option value="password" <?php selected($auth_method, 'password'); ?>><?php esc_html_e( 'App Password / Password (Basic Auth)', 'insightx-form' ); ?></option>
                                        <option value="oauth_google" <?php selected($auth_method, 'oauth_google'); ?>>Google OAuth2 (Gmail / Workspace)</option>
                                        <option value="oauth_microsoft" <?php selected($auth_method, 'oauth_microsoft'); ?>>Microsoft 365 OAuth2 (Outlook / Exchange)</option>
                                    </select>
                                </td></tr>
                            </table>

                            <!-- ===== Basic Auth (username + password) ===== -->
                            <div id="isxf_auth_password" style="<?php echo $is_oauth ? 'display:none;' : ''; ?>">
                                <table class="form-table">
                                    <tr><th><?php esc_html_e( 'Provider (Preset)', 'insightx-form' ); ?></th><td>
                                        <select id="isxf_smtp_preset" style="width:100%; max-width:360px;">
                                            <option value=""><?php esc_html_e( '— Select to auto-fill Host/Port —', 'insightx-form' ); ?></option>
                                            <option value="gmail">Gmail (smtp.gmail.com:587)</option>
                                            <option value="m365">Microsoft 365 (smtp.office365.com:587)</option>
                                            <option value="custom"><?php esc_html_e( 'Custom', 'insightx-form' ); ?></option>
                                        </select>
                                    </td></tr>
                                    <tr><th>Host</th><td><input type="text" name="<?php echo $opt['smtp_host']; ?>" value="<?php echo esc_attr($v['smtp_host']); ?>" class="regular-text" placeholder="smtp.gmail.com"></td></tr>
                                    <tr><th>Port</th><td><input type="number" name="<?php echo $opt['smtp_port']; ?>" value="<?php echo esc_attr($v['smtp_port']); ?>" class="small-text" placeholder="587"></td></tr>
                                    <tr><th>Username</th><td><input type="text" name="<?php echo $opt['smtp_user']; ?>" value="<?php echo esc_attr($v['smtp_user']); ?>" class="regular-text" placeholder="your-email@gmail.com"></td></tr>
                                    <tr><th>Password</th><td><input type="password" name="<?php echo $opt['smtp_pass']; ?>" value="" class="regular-text" placeholder="<?php echo $v['smtp_pass'] ? '••••••••••••••••' : esc_attr__( '16-character app password', 'insightx-form' ); ?>"><p class="description" style="margin-top:5px;"><?php esc_html_e( '🔒 Password is encrypted before saving — leave empty to keep it unchanged.', 'insightx-form' ); ?></p></td></tr>
                                </table>

                                <div style="background: #f0f7ff; border: 1px solid #cce5ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                                    <strong style="color: #004085; display:block; margin-bottom:5px;"><?php esc_html_e( '💡 How to get an App Password for Gmail:', 'insightx-form' ); ?></strong>
                                    <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #333; line-height: 1.6;">
                                        <li><?php
                                            /* translators: %s: link to the Google Account security settings page. */
                                            printf( esc_html__( 'Go to %s (Security)', 'insightx-form' ), '<a href="https://myaccount.google.com/security" target="_blank" style="text-decoration:none;">Google Account > Security</a>' );
                                        ?></li>
                                        <li><?php
                                            /* translators: %s: "2-Step Verification" label wrapped in <strong> tags. */
                                            printf( esc_html__( 'Enable %s (2-step verification)', 'insightx-form' ), '<strong>2-Step Verification</strong>' );
                                        ?></li>
                                        <li><?php
                                            /* translators: %s: "App passwords" menu label wrapped in <strong> tags. */
                                            printf( esc_html__( 'Search for %s (app passwords)', 'insightx-form' ), '<strong>"App passwords"</strong>' );
                                        ?></li>
                                        <li><?php esc_html_e( 'Name it (e.g. "Website SMTP") and click Create', 'insightx-form' ); ?></li>
                                        <li><?php
                                            /* translators: %s: "Password" field label wrapped in <strong> tags. */
                                            printf( esc_html__( 'Copy the 16-character code into the %s field above (no spaces needed)', 'insightx-form' ), '<strong>Password</strong>' );
                                        ?></li>
                                    </ol>
                                    <p style="margin:10px 0 0; font-size:12px; color:#664d03; background:#fff8e5; border:1px solid #ffe0b2; padding:8px 10px; border-radius:4px;">⚠️ <strong>Microsoft 365:</strong> <?php
                                        /* translators: %s: "Microsoft 365 OAuth2" label wrapped in <strong> tags. */
                                        printf( esc_html__( 'Basic Auth only works for tenants that still have SMTP AUTH enabled — many tenants have disabled it. We recommend using %s instead.', 'insightx-form' ), '<strong>Microsoft 365 OAuth2</strong>' );
                                    ?></p>
                                </div>
                            </div>

                            <!-- ===== OAuth2 (XOAUTH2) ===== -->
                            <div id="isxf_auth_oauth" style="<?php echo $is_oauth ? '' : 'display:none;'; ?>">
                                <?php if ( $is_connected ) : ?>
                                    <div style="background:#edfaef; border:1px solid #46b450; padding:12px 15px; border-radius:4px; margin:12px 0;">
                                        ✅ <strong><?php esc_html_e( 'Connected', 'insightx-form' ); ?></strong><?php echo $connected_email && is_email($connected_email) ? ' — ' . esc_html($connected_email) : ''; ?>
                                        <a href="<?php echo esc_url($disconnect_url); ?>" class="button" style="margin-left:10px;"><?php esc_html_e( 'Disconnect', 'insightx-form' ); ?></a>
                                    </div>
                                <?php else : ?>
                                    <div style="background:#fef0f0; border:1px solid #f0b8b8; padding:12px 15px; border-radius:4px; margin:12px 0;">
                                        ⚠️ <strong><?php esc_html_e( 'Not connected', 'insightx-form' ); ?></strong> — <?php
                                            /* translators: %s: "save the settings" label wrapped in <strong> tags. */
                                            printf( esc_html__( 'Enter your Client ID / Secret and %s first, then click the "Connect" button below', 'insightx-form' ), '<strong>' . esc_html__( 'save the settings', 'insightx-form' ) . '</strong>' );
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <table class="form-table">
                                    <tr><th>Client ID</th><td><input type="text" name="<?php echo $opt['oauth_client_id']; ?>" value="<?php echo esc_attr($v['oauth_client_id']); ?>" class="regular-text"></td></tr>
                                    <tr><th>Client Secret</th><td><input type="password" name="<?php echo $opt['oauth_client_secret']; ?>" value="" class="regular-text" placeholder="<?php echo $v['oauth_client_secret'] ? '••••••••••••••••' : ''; ?>"><p class="description" style="margin-top:5px;"><?php esc_html_e( '🔒 Encrypted before saving — leave empty to keep it unchanged.', 'insightx-form' ); ?></p></td></tr>
                                    <tr id="isxf_oauth_tenant_row" style="<?php echo $auth_method === 'oauth_microsoft' ? '' : 'display:none;'; ?>"><th>Tenant ID</th><td><input type="text" name="<?php echo $opt['oauth_tenant']; ?>" value="<?php echo esc_attr($v['oauth_tenant']); ?>" class="regular-text" placeholder="common"><p class="description" style="margin-top:5px;"><?php
                                        /* translators: %s: the literal value "common" wrapped in <code> tags. */
                                        printf( esc_html__( 'Enter your Azure Tenant ID (Directory ID) or leave it as %s', 'insightx-form' ), '<code>common</code>' );
                                    ?></p></td></tr>
                                    <tr><th>Redirect URI</th><td><input type="text" readonly value="<?php echo esc_attr($redirect_uri); ?>" class="regular-text" onclick="this.select();" style="background:#f0f0f1;"><p class="description" style="margin-top:5px;"><?php esc_html_e( 'Copy this value into Google Cloud Console / Azure App registration', 'insightx-form' ); ?></p></td></tr>
                                </table>

                                <p>
                                    <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary">🔗 <?php echo $is_connected ? esc_html__( 'Reconnect', 'insightx-form' ) : esc_html__( 'Connect Account', 'insightx-form' ); ?></a>
                                </p>

                                <div style="background: #f0f7ff; border: 1px solid #cce5ff; padding: 15px; border-radius: 4px; margin: 15px 0; font-size:13px; line-height:1.6; color:#333;">
                                    <strong style="color:#004085; display:block; margin-bottom:5px;"><?php esc_html_e( '💡 See the "User Guide" page for OAuth2 setup instructions', 'insightx-form' ); ?></strong>
                                    <span><?php esc_html_e( 'You need to register an OAuth app in Google Cloud Console (Gmail) or Azure App registration (Microsoft 365), then configure the Redirect URI above to match.', 'insightx-form' ); ?></span>
                                </div>
                            </div>

                            <!-- ===== Shared: SSL verification ===== -->
                            <div style="margin-top:10px; padding:10px 15px; background:#fff8e5; border:1px solid #ffe0b2; border-radius:4px;">
                                <label>
                                    <input type="checkbox" name="<?php echo $opt['smtp_ssl_verify_off']; ?>" value="yes" <?php checked($v['smtp_ssl_verify_off'], 'yes'); ?>>
                                    <strong><?php esc_html_e( '⚠️ Disable SSL Certificate Verification', 'insightx-form' ); ?></strong> <span style="color:#996800; font-size:12px;"><?php esc_html_e( '(Development only)', 'insightx-form' ); ?></span>
                                </label>
                                <p class="description" style="margin:5px 0 0; font-size:12px; color:#666;"><?php
                                    /* translators: %s: "Not recommended for Production" label wrapped in <strong> tags. */
                                    printf( esc_html__( 'Enable only when the server does not have a valid SSL certificate — %s', 'insightx-form' ), '<strong>' . esc_html__( 'Not recommended for Production', 'insightx-form' ) . '</strong>' );
                                ?></p>
                            </div>
                        </div>
                    </div>
                    <?php submit_button( __( 'Save All Settings', 'insightx-form' ) ); ?>
                </form>

                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 6px; margin-top: 20px; border-left: 4px solid #dba617;">
                    <h3 style="margin-top:0;"><?php esc_html_e( '📨 Test Email Sending (SMTP Test)', 'insightx-form' ); ?></h3>
                    <p style="color:#50575e; margin-bottom:15px;"><?php esc_html_e( 'Send a test email to the specified address to verify that your SMTP settings are correct.', 'insightx-form' ); ?></p>
                    <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#1d2327;"><?php esc_html_e( 'Recipient email:', 'insightx-form' ); ?></label>
                            <input type="email" id="isxf-test-email-to" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text" style="width:100%;" placeholder="your@email.com">
                        </div>
                        <button type="button" id="isxf-test-email-btn" class="button button-primary" style="height:36px; min-width:160px;">
                            <?php esc_html_e( '📨 Send Test Email', 'insightx-form' ); ?>
                        </button>
                    </div>
                    <div id="isxf-test-email-result" style="margin-top:12px; display:none; padding:12px 16px; border-radius:4px; font-size:13px; line-height:1.5; transition: all 0.3s ease;"></div>
                </div>
            </div>
            