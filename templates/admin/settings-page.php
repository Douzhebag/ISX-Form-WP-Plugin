<?php
/**
 * Template: Global settings page (wp-admin → InsightX Form → ⚙️ ตั้งค่าระบบ).
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
            <div class="wrap isxf-settings">
                <div class="isxf-card">
                <h1 class="isxf-title"><?php esc_html_e( '⚙️ Global System Settings', 'insightx-form' ); ?></h1>
                <?php settings_errors(); ?>
                <?php if ( $oauth_notice === 'connected' ) : ?>
                    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( '✅ OAuth account connected successfully', 'insightx-form' ); ?></p></div>
                <?php elseif ( $oauth_notice === 'disconnected' ) : ?>
                    <div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'ℹ️ OAuth account disconnected', 'insightx-form' ); ?></p></div>
                <?php elseif ( $oauth_notice === 'error' ) : ?>
                    <div class="notice notice-error is-dismissible"><p>❌ <?php esc_html_e( 'OAuth connection failed:', 'insightx-form' ); ?> <?php echo $oauth_error_msg; ?></p></div>
                <?php endif; ?>
                <?php if ( ! $captcha_configured ) : ?>
                    <div class="notice notice-warning">
                        <p>⚠️ <strong><?php esc_html_e( 'CAPTCHA is not configured', 'insightx-form' ); ?></strong> — <?php esc_html_e( 'All forms will have no bot protection. Please configure the Site Key and Secret Key below to enable it.', 'insightx-form' ); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php settings_fields( 'isxf_global_group' ); ?>
                    <div class="isxf-settings-save-status" role="status" aria-live="polite"></div>

                    <!-- ===== Admin notification ===== -->
                    <div class="isxf-section">
                        <h2 class="isxf-section-title"><?php esc_html_e( '🔔 Admin Notification', 'insightx-form' ); ?></h2>
                        <label class="isxf-check">
                            <input type="checkbox" name="<?php echo $opt['admin_notify_enable']; ?>" value="yes" <?php checked($v['admin_notify_enable'], 'yes'); ?>>
                            <span><?php esc_html_e( 'Enable admin email notifications', 'insightx-form' ); ?></span>
                        </label>
                        <div class="isxf-grid">
                            <div class="isxf-field isxf-field-wide">
                                <input type="text" name="<?php echo $opt['admin_notify_email']; ?>" value="<?php echo esc_attr($v['admin_notify_email']); ?>" placeholder="<?php esc_attr_e( 'e.g. admin@example.com', 'insightx-form' ); ?>">
                                <p class="isxf-hint"><?php
                                    /* translators: %s: fallback admin email address wrapped in <code> tags. */
                                    printf( esc_html__( 'Recipient email (if left empty, emails will be sent to: %s)', 'insightx-form' ), '<code>' . $admin_email . '</code>' );
                                ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- ===== SMTP ===== -->
                    <div class="isxf-section">
                        <?php /* OAuth URLs/state are gathered in Admin::render_settings_page(). */ ?>
                        <h2 class="isxf-section-title"><?php esc_html_e( '📧 Email Settings (SMTP)', 'insightx-form' ); ?></h2>
                        <label class="isxf-check">
                            <input type="checkbox" name="<?php echo $opt['smtp_enable']; ?>" value="yes" <?php checked($v['smtp_enable'], 'yes'); ?>>
                            <span><?php esc_html_e( 'Enable SMTP', 'insightx-form' ); ?></span>
                        </label>

                        <?php
                        // Which provider card starts selected: OAuth wins, otherwise
                        // match the saved host against the presets, else "Custom".
                        $smtp_host_now = strtolower( trim( (string) $v['smtp_host'] ) );
                        if ( $is_oauth ) {
                            $provider_card = 'oauth_google';
                        } elseif ( $smtp_host_now === 'smtp.resend.com' ) {
                            $provider_card = 'resend';
                        } elseif ( $smtp_host_now === 'smtp.mx.cloudflare.net' ) {
                            $provider_card = 'cloudflare';
                        } else {
                            $provider_card = 'custom';
                        }
                        // Static icon markup (no user input).
                        $provider_cards = [
                            'oauth_google' => [ 'label' => 'Google', 'auth' => 'oauth_google', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.09a6.6 6.6 0 0 1 0-4.18V7.07H2.18a11 11 0 0 0 0 9.86z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0 0 2.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>' ],
                            'resend'       => [ 'label' => 'Resend', 'auth' => 'password', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#000"/><path fill="#fff" d="M8 6h5.2c2.4 0 4 1.4 4 3.6 0 1.7-1 2.9-2.6 3.4L18 18h-2.7l-3.1-4.7H10.4V18H8zm2.4 2.1v3.1h2.7c1.1 0 1.8-.6 1.8-1.6s-.7-1.5-1.8-1.5z"/></svg>' ],
                            'cloudflare'   => [ 'label' => 'Cloudflare', 'auth' => 'password', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#F38020" d="M16.5 16.6l.3-.9c.3-1 .2-2-.3-2.6-.5-.6-1.2-.9-2-1l-8.5-.1a.2.2 0 0 1-.1-.3.2.2 0 0 1 .2-.1l8.6-.1c1-.1 2.1-.9 2.5-1.9l.5-1.3.1-.3A5.6 5.6 0 0 0 7 7.4a2.5 2.5 0 0 0-3.9 2.6A3.6 3.6 0 0 0 .1 13.6c0 .2 0 .4.1.5 0 .1.1.2.2.2h15.7c.1 0 .3-.1.3-.2z"/><path fill="#FAAE40" d="M19.3 10.8h-.2l-.1.2-.3 1.1c-.3 1-.2 2 .3 2.6.5.6 1.2.9 2 1l1.8.1.2.1v.3c0 .1-.1.1-.2.1l-1.9.1c-1 .1-2.1.9-2.5 1.9l-.1.4.1.1H23c.1 0 .2-.1.2-.2.2-.5.3-1 .3-1.6a4.3 4.3 0 0 0-4.2-4.2z"/></svg>' ],
                            'custom'       => [ 'label' => __( 'Custom', 'insightx-form' ), 'auth' => 'password', 'icon' => '<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>' ],
                        ];
                        ?>
                        <p class="isxf-field-title"><?php esc_html_e( 'Authentication Method', 'insightx-form' ); ?></p>
                        <div class="isxf-provider-cards" role="group" aria-label="<?php esc_attr_e( 'Authentication Method', 'insightx-form' ); ?>">
                            <?php foreach ( $provider_cards as $card_slug => $card ) : ?>
                                <button type="button" class="isxf-provider-card<?php echo $card_slug === $provider_card ? ' is-selected' : ''; ?>" data-auth="<?php echo esc_attr( $card['auth'] ); ?>" data-preset="<?php echo esc_attr( $card_slug ); ?>" aria-pressed="<?php echo $card_slug === $provider_card ? 'true' : 'false'; ?>">
                                    <span class="isxf-provider-card-icon"><?php echo $card['icon']; ?></span>
                                    <span class="isxf-provider-card-label"><?php echo esc_html( $card['label'] ); ?></span>
                                    <span class="isxf-provider-card-check" aria-hidden="true"></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?php /* The saved value still comes from this select; the cards above drive it (isxf-admin.js). */ ?>
                        <select name="<?php echo $opt['smtp_auth_method']; ?>" id="isxf_auth_method" class="screen-reader-text" tabindex="-1" aria-hidden="true">
                            <option value="password" <?php selected($auth_method, 'password'); ?>><?php esc_html_e( 'App Password / Password (Basic Auth)', 'insightx-form' ); ?></option>
                            <option value="oauth_google" <?php selected($auth_method, 'oauth_google'); ?>>Google OAuth2 (Gmail / Workspace)</option>
                        </select>

                        <!-- ===== Basic Auth (username + password) ===== -->
                        <div id="isxf_auth_password" style="<?php echo $is_oauth ? 'display:none;' : ''; ?>">
                            <div class="isxf-grid isxf-grid-gap">
                                <div class="isxf-field">
                                    <label>Host</label>
                                    <input type="text" name="<?php echo $opt['smtp_host']; ?>" value="<?php echo esc_attr($v['smtp_host']); ?>" placeholder="smtp.example.com">
                                </div>
                                <div class="isxf-field">
                                    <label>Port</label>
                                    <input type="number" name="<?php echo $opt['smtp_port']; ?>" value="<?php echo esc_attr($v['smtp_port']); ?>" placeholder="587">
                                </div>
                                <div class="isxf-field">
                                    <label>Username</label>
                                    <input type="text" name="<?php echo $opt['smtp_user']; ?>" value="<?php echo esc_attr($v['smtp_user']); ?>" placeholder="you@example.com">
                                </div>
                                <div class="isxf-field isxf-field-wide">
                                    <label>Password</label>
                                    <input type="password" class="isxf-smtp-password" name="<?php echo $opt['smtp_pass']; ?>" value="" autocomplete="new-password" placeholder="<?php echo $v['smtp_pass'] ? '••••••••••••••••' : ''; ?>">
                                    <p class="isxf-hint"><?php esc_html_e( '🔒 Password is encrypted before saving — leave empty to keep it unchanged.', 'insightx-form' ); ?></p>
                                </div>
                            </div>

                            <div class="isxf-smtp-connection-test">
                                <button type="button" id="isxf-test-smtp-connection-btn" class="button button-primary isxf-btn">
                                    <?php esc_html_e( 'Connect', 'insightx-form' ); ?>
                                </button>
                                <div id="isxf-test-smtp-connection-result" class="isxf-smtp-status" role="status" aria-live="polite" hidden></div>
                            </div>

                            <?php /* Only the note for the selected provider card is shown (isxf-admin.js toggles it). */ ?>
                            <div class="isxf-callout" id="isxf-provider-notes"<?php echo in_array( $provider_card, [ 'resend', 'cloudflare' ], true ) ? '' : ' hidden'; ?>>
                                <p data-provider-note="resend"<?php echo $provider_card === 'resend' ? '' : ' hidden'; ?>>✉️ <strong>Resend:</strong> <?php
                                    /* translators: 1: username "resend" in <code> tags, 2: link to the Resend API keys page. */
                                    printf( esc_html__( 'Username is %1$s and Password is your API key from %2$s. The From Email domain must be verified in Resend.', 'insightx-form' ), '<code>resend</code>', '<a href="https://resend.com/api-keys" target="_blank" rel="noopener noreferrer">resend.com/api-keys</a>' );
                                ?></p>
                                <p data-provider-note="cloudflare"<?php echo $provider_card === 'cloudflare' ? '' : ' hidden'; ?>>☁️ <strong>Cloudflare:</strong> <?php
                                    /* translators: 1: username "api_token" in <code> tags, 2: "Email Sending: Edit" permission name in <code> tags. */
                                    printf( esc_html__( 'Username is %1$s and Password is a Cloudflare API token with the %2$s permission. The From Email domain must be onboarded under Email Service → Email Sending in Cloudflare.', 'insightx-form' ), '<code>api_token</code>', '<code>Email Sending: Edit</code>' );
                                ?></p>
                            </div>
                        </div>

                        <!-- ===== OAuth2 (XOAUTH2) ===== -->
                        <div id="isxf_auth_oauth" style="<?php echo $is_oauth ? '' : 'display:none;'; ?>">
                            <?php if ( $is_connected ) : ?>
                                <div class="isxf-status is-ok">
                                    ✅ <strong><?php esc_html_e( 'Connected', 'insightx-form' ); ?></strong><?php echo $connected_email && is_email($connected_email) ? ' — ' . esc_html($connected_email) : ''; ?>
                                    <a href="<?php echo esc_url($disconnect_url); ?>" class="button isxf-btn-secondary"><?php esc_html_e( 'Disconnect', 'insightx-form' ); ?></a>
                                </div>
                            <?php else : ?>
                                <div class="isxf-status is-error">
                                    ⚠️ <strong><?php esc_html_e( 'Not connected', 'insightx-form' ); ?></strong> — <?php
                                        esc_html_e( 'Enter your Client ID / Secret. Changes are saved automatically before you connect.', 'insightx-form' );
                                    ?>
                                </div>
                            <?php endif; ?>

                            <div class="isxf-grid isxf-grid-gap">
                                <div class="isxf-field">
                                    <label>Client ID</label>
                                    <input type="text" name="<?php echo $opt['oauth_client_id']; ?>" value="<?php echo esc_attr($v['oauth_client_id']); ?>">
                                </div>
                                <div class="isxf-field">
                                    <label>Client Secret</label>
                                    <input type="password" name="<?php echo $opt['oauth_client_secret']; ?>" value="" autocomplete="new-password" placeholder="<?php echo $v['oauth_client_secret'] ? '••••••••••••••••' : ''; ?>">
                                    <p class="isxf-hint"><?php esc_html_e( '🔒 Encrypted before saving — leave empty to keep it unchanged.', 'insightx-form' ); ?></p>
                                </div>
                                <div class="isxf-field isxf-field-wide">
                                    <label>Redirect URI</label>
                                    <input type="text" readonly value="<?php echo esc_attr($redirect_uri); ?>" onclick="this.select();">
                                    <p class="isxf-hint"><?php esc_html_e( 'Copy this value into Google Cloud Console', 'insightx-form' ); ?></p>
                                </div>
                            </div>

                            <div class="isxf-actions">
                                <a href="<?php echo esc_url($connect_url); ?>" class="button button-primary isxf-btn"><?php echo $is_connected ? esc_html__( 'Reconnect', 'insightx-form' ) : esc_html__( 'Connect Account', 'insightx-form' ); ?></a>
                            </div>

                            <div class="isxf-callout">
                                <strong><?php esc_html_e( '💡 See the "User Guide" page for OAuth2 setup instructions', 'insightx-form' ); ?></strong>
                                <span><?php esc_html_e( 'You need to register an OAuth app in Google Cloud Console (Gmail), then configure the Redirect URI above to match.', 'insightx-form' ); ?></span>
                            </div>
                        </div>

                        <!-- ===== Shared: SSL verification ===== -->
                        <div class="isxf-warn">
                            <label class="isxf-check">
                                <input type="checkbox" name="<?php echo $opt['smtp_ssl_verify_off']; ?>" value="yes" <?php checked($v['smtp_ssl_verify_off'], 'yes'); ?>>
                                <span><strong><?php esc_html_e( '⚠️ Disable SSL Certificate Verification', 'insightx-form' ); ?></strong> <em><?php esc_html_e( '(Development only)', 'insightx-form' ); ?></em></span>
                            </label>
                            <p class="isxf-hint"><?php
                                /* translators: %s: "Not recommended for Production" label wrapped in <strong> tags. */
                                printf( esc_html__( 'Enable only when the server does not have a valid SSL certificate — %s', 'insightx-form' ), '<strong>' . esc_html__( 'Not recommended for Production', 'insightx-form' ) . '</strong>' );
                            ?></p>
                        </div>
                    </div>

                    <!-- ===== CAPTCHA ===== -->
                    <div class="isxf-section">
                        <h2 class="isxf-section-title"><?php esc_html_e( '🛡️ Security Settings (Captcha)', 'insightx-form' ); ?></h2>
                        <div class="isxf-grid">
                            <div class="isxf-field">
                                <select name="<?php echo $opt['captcha_service']; ?>" id="captcha_select">
                                    <option value="google" <?php selected($service, 'google'); ?>>Google reCAPTCHA v3</option>
                                    <option value="cloudflare" <?php selected($service, 'cloudflare'); ?>>Cloudflare Turnstile</option>
                                </select>
                            </div>
                        </div>
                        <div id="settings_google" style="<?php echo $service !== 'google' ? 'display:none;' : ''; ?>">
                            <div class="isxf-grid isxf-grid-gap">
                                <div class="isxf-field">
                                    <label>Site Key</label>
                                    <input type="text" name="<?php echo $opt['recaptcha_site']; ?>" value="<?php echo esc_attr($v['recaptcha_site']); ?>">
                                </div>
                                <div class="isxf-field">
                                    <label>Secret Key</label>
                                    <input type="password" name="<?php echo $opt['recaptcha_secret']; ?>" value="<?php echo esc_attr($v['recaptcha_secret']); ?>" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                        <div id="settings_cloudflare" style="<?php echo $service !== 'cloudflare' ? 'display:none;' : ''; ?>">
                            <div class="isxf-grid isxf-grid-gap">
                                <div class="isxf-field">
                                    <label>Site Key</label>
                                    <input type="text" name="<?php echo $opt['turnstile_site']; ?>" value="<?php echo esc_attr($v['turnstile_site']); ?>">
                                </div>
                                <div class="isxf-field">
                                    <label>Secret Key</label>
                                    <input type="password" name="<?php echo $opt['turnstile_secret']; ?>" value="<?php echo esc_attr($v['turnstile_secret']); ?>" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                        <label class="isxf-check">
                            <input type="checkbox" name="<?php echo $opt['captcha_required']; ?>" value="yes" <?php checked($v['captcha_required'], 'yes'); ?>>
                            <span><?php esc_html_e( 'Block form submissions when CAPTCHA is not configured (recommended)', 'insightx-form' ); ?></span>
                        </label>
                        <p class="isxf-hint"><?php esc_html_e( 'When enabled, if the selected CAPTCHA service has no Secret Key, all form submissions will be rejected instead of allowed through — disable this only if you intentionally use forms without CAPTCHA.', 'insightx-form' ); ?></p>
                    </div>

                    <!-- ===== Trusted proxies ===== -->
                    <div class="isxf-section">
                        <h2 class="isxf-section-title"><?php esc_html_e( '🌐 Trusted Proxies (for sites behind a Reverse Proxy / CDN)', 'insightx-form' ); ?></h2>
                        <div class="isxf-grid">
                            <div class="isxf-field isxf-field-wide">
                                <textarea name="<?php echo $opt['trusted_proxies']; ?>" rows="4" class="code" placeholder="173.245.48.0/20&#10;103.21.244.0/22"><?php echo esc_textarea($v['trusted_proxies']); ?></textarea>
                                <p class="isxf-hint"><?php
                                    /* translators: %1$s: CF-Connecting-IP header name wrapped in <code> tags, %2$s: X-Forwarded-For header name wrapped in <code> tags. */
                                    printf( esc_html__( 'Enter the IPs or CIDRs of trusted proxies (e.g. Cloudflare, Nginx reverse proxy), one per line — the real user IP is only read from the %1$s / %2$s header when the request comes from these proxies.', 'insightx-form' ), '<code>CF-Connecting-IP</code>', '<code>X-Forwarded-For</code>' );
                                ?> <strong><?php esc_html_e( 'Leave empty = trust REMOTE_ADDR only (default, safest)', 'insightx-form' ); ?></strong></p>
                            </div>
                        </div>
                    </div>

                </form>

                <!-- ===== SMTP test ===== -->
                <div class="isxf-section">
                    <h2 class="isxf-section-title"><?php esc_html_e( '📨 Test Email Sending (SMTP Test)', 'insightx-form' ); ?></h2>
                    <p class="isxf-hint"><?php esc_html_e( 'Send a test email to the specified address to verify that your SMTP settings are correct.', 'insightx-form' ); ?></p>
                    <div class="isxf-test-row">
                        <div class="isxf-field">
                            <label for="isxf-test-email-to"><?php esc_html_e( 'Recipient email:', 'insightx-form' ); ?></label>
                            <input type="email" id="isxf-test-email-to" value="<?php echo esc_attr( $admin_email ); ?>" placeholder="your@email.com">
                        </div>
                        <button type="button" id="isxf-test-email-btn" class="button button-primary isxf-btn">
                            <?php esc_html_e( '📨 Send Test Email', 'insightx-form' ); ?>
                        </button>
                    </div>
                    <div id="isxf-test-email-result" style="margin-top:12px; display:none; padding:12px 16px; border-radius:8px; font-size:13px; line-height:1.5;"></div>
                </div>
                </div>
            </div>
