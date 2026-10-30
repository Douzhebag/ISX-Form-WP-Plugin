<?php
/**
 * Template: Documentation page (wp-admin → InsightX Form → 📖 คู่มือการใช้งาน).
 *
 * Extracted from ISXF\Admin::render_docs_page() (Phase 2.2 view split).
 * Static markup; uses the ISXF_PLUGIN_VERSION constant directly.
 */
?>
            <style>
                /* Same card/section look as the Global Settings page (InsightX Backup style). */
                .isxf-docs-wrap { max-width:1280px; margin:18px auto 0; background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.06); padding:26px 28px; }
                .isxf-docs-wrap h1 { display:flex; align-items:center; gap:10px; font-size:22px; font-weight:700; letter-spacing:-0.01em; color:#0f172a; padding:0; margin:0 0 6px; }
                .isxf-docs-ver { font-size:12px; background:#e6f2ff; color:#0062d1; border:1px solid #bfe0ff; padding:2px 10px; border-radius:100px; font-weight:600; letter-spacing:0; }
                .isxf-docs-intro { color:#5a6881; margin:0 0 4px; }
                .isxf-docs-section { background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04), 0 2px 8px rgba(15,23,42,.05); margin-top:14px; overflow:hidden; }
                .isxf-docs-header { display:flex; align-items:center; justify-content:flex-start; gap:10px; padding:16px 22px; cursor:pointer; user-select:none; transition:background .2s; font-weight:700; font-size:15px; color:#0f172a; text-align:left; }
                .isxf-docs-header img.emoji { margin:0 !important; }
                .isxf-docs-header:hover { background:#f8fafc; }
                .isxf-docs-arrow { margin-left:auto; transition:transform .3s; font-size:12px; color:#94a3b8; }
                .isxf-docs-section.open { border-color:#bfe0ff; }
                .isxf-docs-section.open .isxf-docs-header { color:#0062d1; }
                .isxf-docs-section.open .isxf-docs-arrow { transform:rotate(180deg); color:#0079ff; }
                .isxf-docs-body { display:none; padding:4px 22px 22px; color:#5a6881; font-size:13px; line-height:1.8; border-top:1px solid #e2e8f0; }
                .isxf-docs-section.open .isxf-docs-body { display:block; }
                .isxf-docs-body h4 { color:#0f172a; margin:18px 0 8px; font-size:14px; }
                .isxf-docs-body table { width:100%; border-collapse:separate; border-spacing:0; margin:10px 0; font-size:13px; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
                .isxf-docs-body th { background:#f8fafc; color:#0f172a; text-align:left; padding:9px 12px; border-bottom:1px solid #e2e8f0; font-weight:600; }
                .isxf-docs-body td { padding:9px 12px; border-bottom:1px solid #e2e8f0; }
                .isxf-docs-body tr:last-child td { border-bottom:0; }
                .isxf-docs-body code { background:#f1f5f9; color:#0f172a; padding:2px 6px; border-radius:6px; font-size:12px; }
                .isxf-docs-body .step { display:flex; gap:12px; margin:8px 0; }
                .isxf-docs-body .step-num { flex-shrink:0; width:24px; height:24px; background:#0079ff; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; margin-top:1px; }
                .isxf-docs-body .step-text { flex:1; }
                .isxf-docs-body .tip-box { background:#e6f2ff; border:1px solid #bfe0ff; color:#0f172a; padding:12px 16px; border-radius:10px; margin:12px 0; font-size:12px; }
                .isxf-docs-body .warn-box { background:#fff8e5; border:1px solid #ffe0b2; color:#0f172a; padding:12px 16px; border-radius:10px; margin:12px 0; font-size:12px; }
                @media (max-width:782px) { .isxf-docs-wrap { padding:18px 16px; } .isxf-docs-header { padding:14px 16px; } .isxf-docs-body { padding:4px 16px 18px; } }
            </style>

            <div class="wrap isxf-docs-wrap">
                <h1><?php esc_html_e( '📖 User Guide', 'insightx-form' ); ?> <span class="isxf-docs-ver">v<?php echo ISXF_PLUGIN_VERSION; ?></span></h1>
                <p class="isxf-docs-intro"><?php esc_html_e( 'InsightX Form — A form and customer data management system for businesses', 'insightx-form' ); ?></p>

                <div class="isxf-docs-section open">
                    <div class="isxf-docs-header"><?php esc_html_e( '📝 Creating a Form', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>InsightX Form → Create New Form</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Set a <strong>form name</strong> (e.g. "Room Booking Form")', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php esc_html_e( 'Add the fields you need from the table below', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php echo __( 'Choose an <strong>auto-reply email template</strong> (Booking / Inquiry / Custom)', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">5</span><span class="step-text"><?php echo __( 'Click <strong>Publish</strong>', 'insightx-form' ); ?></span></div>

                        <h4><?php esc_html_e( 'Supported Field Types', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th><?php esc_html_e( 'Type', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Description', 'insightx-form' ); ?></th></tr>
                            <tr><td>Text</td><td><?php esc_html_e( 'Short text (name, address)', 'insightx-form' ); ?></td></tr>
                            <tr><td>Textarea</td><td><?php esc_html_e( 'Long text', 'insightx-form' ); ?></td></tr>
                            <tr><td>Email</td><td><?php esc_html_e( 'Email — the system automatically sends a confirmation email to the customer', 'insightx-form' ); ?></td></tr>
                            <tr><td>Telephone</td><td><?php esc_html_e( 'Phone number (digits only)', 'insightx-form' ); ?></td></tr>
                            <tr><td>Number</td><td><?php esc_html_e( 'Numbers', 'insightx-form' ); ?></td></tr>
                            <tr><td>Date / Check-in / Check-out</td><td><?php esc_html_e( 'Date picker (Check-in/out are linked automatically)', 'insightx-form' ); ?></td></tr>
                            <tr><td>Select / Radio / Checkbox</td><td><?php esc_html_e( 'Options — enter them separated by commas, e.g.', 'insightx-form' ); ?> <code>ห้อง A, ห้อง B</code></td></tr>
                            <tr><td>Heading</td><td><?php esc_html_e( 'Group heading (not submitted)', 'insightx-form' ); ?></td></tr>
                        </table>
                        <div class="tip-box"><?php esc_html_e( '💡 Drag the ☰ icon to reorder fields. Set Width to 50% to place two fields side by side', 'insightx-form' ); ?></div>

                        <h4><?php esc_html_e( 'Submit Button Style', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'In the 🎨 panel, pick the background, text and hover colours, the corner radius and the font size. Leave a field empty to use the default. Advanced CSS applies to this form\'s button only.', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Auto-reply Email Tools', 'insightx-form' ); ?></h4>
                        <ul style="margin:8px 0; padding-left:20px;">
                            <li><?php echo __( '<strong>👁️ Preview</strong> — see the email with sample data, even before you save the form', 'insightx-form' ); ?></li>
                            <li><?php echo __( '<strong>✏️ Edit from this template</strong> — copy the Booking / Inquiry template into the custom editor to adjust it', 'insightx-form' ); ?></li>
                            <li><?php echo __( '<strong>🧪 Send a test email to me</strong> — send the sample email to your own address through your SMTP settings', 'insightx-form' ); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '🖥️ Displaying a Form on Your Site', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>InsightX Form → All Forms</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Copy the shortcode, e.g. <code>[isxf_form id="123"]</code>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php esc_html_e( 'Paste it into the page or post where you want it to appear', 'insightx-form' ); ?></span></div>
                        <div class="tip-box"><?php esc_html_e( '💡 The same shortcode can be placed on multiple pages', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '📧 SMTP Settings & Email Testing', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>InsightX Form → ⚙️ System Settings</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Check <strong>Enable SMTP</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Under <strong>Authentication Method</strong>, click the card for your email service: <strong>Google</strong>, <strong>Resend</strong>, <strong>Cloudflare</strong> or <strong>Custom</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php esc_html_e( 'Fill in the remaining fields — see "How to Connect Each Email Provider" below for the exact values', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">5</span><span class="step-text"><?php echo __( 'Click <strong>Save</strong>, then scroll down and click <strong>"📨 Send Test Email"</strong>', 'insightx-form' ); ?></span></div>

                        <h4><?php esc_html_e( '🔐 SMTP Security', 'insightx-form' ); ?></h4>
                        <ul style="margin:8px 0; padding-left:20px; font-size:13px;">
                            <li><?php echo __( '🔒 <strong>Password is encrypted with AES-256-CBC</strong> automatically before saving — leave it blank if you do not want to change it', 'insightx-form' ); ?></li>
                            <li><?php echo __( '🔒 <strong>SSL Verification</strong> is enabled by default — only check the "Disable SSL" checkbox for development', 'insightx-form' ); ?></li>
                        </ul>

                        <h4><?php esc_html_e( 'Admin Notifications', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Check "Enable admin notification email" and specify the recipient email — the admin will receive a notification email immediately every time someone submits the form', 'insightx-form' ); ?></p>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '🔌 How to Connect Each Email Provider', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php esc_html_e( 'Pick the card that matches your email service on the settings page. Each provider needs different values:', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( '🟦 Google (Gmail / Workspace)', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Connects with OAuth2, so no email password is stored on the site. Best for Google Workspace accounts.', 'insightx-form' ); ?></p>
                        <div class="tip-box"><?php echo __( '💡 The settings page has a <strong>Redirect URI</strong> field — copy it into the steps below. It must match 100%', 'insightx-form' ); ?></div>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console → Credentials</a> and create a project (if you do not have one yet)', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Configure the <strong>OAuth consent screen</strong> and add the scope <code>https://mail.google.com/</code>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Create an <strong>OAuth client ID</strong> of type <em>Web application</em>, then enter the <strong>Redirect URI</strong> from the settings page', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php echo __( 'Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the settings page → click <strong>Save</strong> → click <strong>Connect Account</strong>', 'insightx-form' ); ?></span></div>
                        <div class="tip-box"><?php esc_html_e( '🔒 The Client Secret and Refresh Token are encrypted with AES-256-CBC before being stored in the database', 'insightx-form' ); ?></div>

                        <h4><?php esc_html_e( '⬛ Resend', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Sign up at <a href="https://resend.com" target="_blank">resend.com</a>, add your domain under <strong>Domains</strong> and add the DNS records it shows until the domain is <strong>Verified</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Create an API key at <a href="https://resend.com/api-keys" target="_blank">resend.com/api-keys</a> (Sending access is enough)', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Click the <strong>Resend</strong> card — Host, Port and Username are filled in for you — then paste the API key into <strong>Password</strong>', 'insightx-form' ); ?></span></div>
                        <table>
                            <tr><th><?php esc_html_e( 'Field', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Value', 'insightx-form' ); ?></th></tr>
                            <tr><td>Host</td><td><code>smtp.resend.com</code></td></tr>
                            <tr><td>Port</td><td><code>587</code></td></tr>
                            <tr><td>Username</td><td><code>resend</code></td></tr>
                            <tr><td>Password</td><td><?php echo __( 'API key from <a href="https://resend.com/api-keys" target="_blank">resend.com/api-keys</a>', 'insightx-form' ); ?></td></tr>
                        </table>
                        <div class="warn-box"><?php esc_html_e( '⚠️ The From Email domain must be verified in Resend (Domains) before emails can be sent', 'insightx-form' ); ?></div>

                        <h4><?php esc_html_e( '🟧 Cloudflare', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'In the <a href="https://dash.cloudflare.com" target="_blank">Cloudflare Dashboard</a>, go to <strong>Email Service → Email Sending</strong> and onboard your domain', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Create an API token (<strong>My Profile → API Tokens</strong>) with the <code>Email Sending: Edit</code> permission', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Click the <strong>Cloudflare</strong> card — Host, Port and Username are filled in for you — then paste the API token into <strong>Password</strong>', 'insightx-form' ); ?></span></div>
                        <table>
                            <tr><th><?php esc_html_e( 'Field', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Value', 'insightx-form' ); ?></th></tr>
                            <tr><td>Host</td><td><code>smtp.mx.cloudflare.net</code></td></tr>
                            <tr><td>Port</td><td><code>465</code></td></tr>
                            <tr><td>Username</td><td><code>api_token</code></td></tr>
                            <tr><td>Password</td><td><?php echo __( 'API token with the <code>Email Sending: Edit</code> permission', 'insightx-form' ); ?></td></tr>
                        </table>
                        <div class="warn-box"><?php esc_html_e( '⚠️ Cloudflare only accepts port 465 (SSL). The From Email domain must be onboarded in Email Sending, and each email can go to at most 50 recipients', 'insightx-form' ); ?></div>

                        <h4><?php esc_html_e( '⚙️ Custom (any SMTP server)', 'insightx-form' ); ?></h4>
                        <p><?php echo __( 'For any other service (Gmail with an App Password, Microsoft 365, your hosting mail server, etc.), click <strong>Custom</strong> and enter the Host, Port, Username and Password from that provider. Port 465 uses SSL; other ports use TLS automatically.', 'insightx-form' ); ?></p>
                        <h4><?php esc_html_e( 'Example Values for Gmail', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th><?php esc_html_e( 'Field', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Value', 'insightx-form' ); ?></th></tr>
                            <tr><td>Host</td><td><code>smtp.gmail.com</code></td></tr>
                            <tr><td>Port</td><td><code>587</code></td></tr>
                            <tr><td>Username</td><td><?php esc_html_e( 'Your Gmail address', 'insightx-form' ); ?></td></tr>
                            <tr><td>Password</td><td><?php esc_html_e( '16-character App Password', 'insightx-form' ); ?></td></tr>
                        </table>
                        <div class="warn-box"><?php esc_html_e( '⚠️ You must enable 2-Step Verification in your Google Account and create an App Password before it will work', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '🛡️ Captcha Settings', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php esc_html_e( 'Two services are supported:', 'insightx-form' ); ?></p>
                        <table>
                            <tr><th><?php esc_html_e( 'Service', 'insightx-form' ); ?></th><th><?php esc_html_e( 'How to Get a Key', 'insightx-form' ); ?></th></tr>
                            <tr><td>Google reCAPTCHA v3</td><td><?php echo __( 'Create one at <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>', 'insightx-form' ); ?></td></tr>
                            <tr><td>Cloudflare Turnstile</td><td><?php echo __( 'Create one at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">Cloudflare Dashboard</a>', 'insightx-form' ); ?></td></tr>
                        </table>
                        <p><?php esc_html_e( 'Select a service from the dropdown → enter the Site Key + Secret Key → save', 'insightx-form' ); ?></p>
                        <div class="tip-box"><?php esc_html_e( '💡 The system automatically verifies the token on both the frontend and the server side', 'insightx-form' ); ?></div>
                        <div class="warn-box"><?php echo __( '⚠️ Keep <strong>"Block form submissions when CAPTCHA is not configured"</strong> enabled — if the Secret Key is missing, submissions are rejected instead of letting bots through', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '🌐 Trusted Proxies (Cloudflare / CDN)', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php esc_html_e( 'If your site sits behind Cloudflare, a CDN or a reverse proxy, every visitor appears to come from the proxy\'s IP, so the per-IP rate limit would block everyone at once.', 'insightx-form' ); ?></p>
                        <p><?php echo __( 'Enter the proxy IP ranges (one CIDR per line) under <strong>Trusted Proxies</strong> on the settings page. The real visitor IP is then read from the <code>CF-Connecting-IP</code> / <code>X-Forwarded-For</code> header, but only for requests that come from those ranges.', 'insightx-form' ); ?></p>
                        <div class="tip-box"><?php echo __( '💡 Cloudflare lists its IP ranges at <a href="https://www.cloudflare.com/ips/" target="_blank">cloudflare.com/ips</a>. Leave the box empty if the site is not behind a proxy', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '✉️ Custom Email Template', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php esc_html_e( 'Write your own subject and body for the customer auto-reply email in the form editor', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'How to Use', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Edit a form → choose <strong>"✏️ Custom (Custom Template)"</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Fill in the <strong>email subject</strong> and <strong>body</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Use <strong>Merge Tags</strong> to insert data automatically (click a tag to insert it)', 'insightx-form' ); ?></span></div>

                        <h4><?php esc_html_e( 'Supported Merge Tags', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th>Tag</th><th><?php esc_html_e( 'Output', 'insightx-form' ); ?></th></tr>
                            <tr><td><code>{site_name}</code></td><td><?php esc_html_e( 'Site name', 'insightx-form' ); ?></td></tr>
                            <tr><td><code>{form_title}</code></td><td><?php esc_html_e( 'Form title', 'insightx-form' ); ?></td></tr>
                            <tr><td><code>{all_fields}</code></td><td><?php esc_html_e( 'HTML table of all submitted data', 'insightx-form' ); ?></td></tr>
                            <tr><td><code>{field:ชื่อฟิลด์}</code></td><td><?php esc_html_e( 'Value of the specified field, e.g.', 'insightx-form' ); ?> <code>{field:ชื่อ}</code></td></tr>
                        </table>
                        <div class="tip-box"><?php esc_html_e( '💡 The system automatically wraps your content in a nice layout (header + footer)', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '📥 Managing Entries', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php echo __( 'Go to <strong>InsightX Form → 📥 Entries</strong>', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Filters', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th><?php esc_html_e( 'Filter', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Description', 'insightx-form' ); ?></th></tr>
                            <tr><td><?php echo esc_html_x( 'Form', 'table column header', 'insightx-form' ); ?></td><td><?php esc_html_e( 'View only the selected form (data shown in separate columns)', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( 'Date range', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Filter by submission date (from - to)', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( 'Search', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Type a name / phone / email / IP', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( 'Status bar', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Click the bar at the top to filter by status', 'insightx-form' ); ?></td></tr>
                        </table>

                        <h4><?php esc_html_e( 'Status System', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th><?php esc_html_e( 'Status', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Meaning', 'insightx-form' ); ?></th></tr>
                            <tr><td><?php esc_html_e( '🔵 New', 'insightx-form' ); ?></td><td><?php esc_html_e( 'New submission, not yet handled', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( '🟡 In Progress', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Being contacted / handled', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( '✅ Completed', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Handled completely', 'insightx-form' ); ?></td></tr>
                            <tr><td><?php esc_html_e( '🔴 Trash', 'insightx-form' ); ?></td><td><?php esc_html_e( 'Spam or irrelevant data', 'insightx-form' ); ?></td></tr>
                        </table>
                        <p><?php esc_html_e( 'Change the status from the dropdown in each row, or select multiple entries and use Bulk Actions', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Admin Notes', 'insightx-form' ); ?></h4>
                        <p><?php echo __( 'Click <strong>"✏️ + Add Note"</strong> to save a message, e.g. "Called the customer" — saved instantly without reloading', 'insightx-form' ); ?></p>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '📊 CSV Export & Dashboard', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <h4><?php esc_html_e( 'CSV Export', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php esc_html_e( 'Set the filters you want (form / date / status)', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Click the <strong>"📊 Export CSV"</strong> button at the top right', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php esc_html_e( 'The CSV file downloads automatically (Thai language supported)', 'insightx-form' ); ?></span></div>

                        <h4>Dashboard Widget</h4>
                        <p><?php echo __( 'When you open <strong>WP-Admin → Dashboard</strong> you will see the "📊 InsightX Form" widget showing:', 'insightx-form' ); ?></p>
                        <ul style="margin:8px 0; padding-left:20px;">
                            <li><?php esc_html_e( 'Statistics: Today / 7 days / 30 days / All time', 'insightx-form' ); ?></li>
                            <li><?php esc_html_e( 'Bar chart of status proportions', 'insightx-form' ); ?></li>
                            <li><?php esc_html_e( 'The 5 latest entries with a "View all" link', 'insightx-form' ); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '📈 Analytics', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php echo __( 'Go to <strong>InsightX Form → 📊 Analytics</strong>', 'insightx-form' ); ?></p>
                        <ul style="margin:8px 0; padding-left:20px;">
                            <li><?php esc_html_e( 'Stat cards: all entries, entries in the selected range (compared with the previous period), today and average per day', 'insightx-form' ); ?></li>
                            <li><?php esc_html_e( 'Daily submissions line chart', 'insightx-form' ); ?></li>
                            <li><?php esc_html_e( 'Status breakdown (doughnut chart)', 'insightx-form' ); ?></li>
                            <li><?php esc_html_e( 'Top forms and the latest entries', 'insightx-form' ); ?></li>
                        </ul>
                        <p><?php esc_html_e( 'Choose a range at the top right: 7 / 30 / 90 days, 1 year or a custom date range', 'insightx-form' ); ?></p>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '❓ Frequently Asked Questions (FAQ)', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <h4><?php esc_html_e( 'The form is not sending email — what should I do?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Check your SMTP settings → use the "Send Test Email" button to see the error message', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Which email provider should I choose?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Google Workspace → Google. Your own domain without a mail server → Resend or Cloudflare. A mail server from your host or another provider → Custom.', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Customer entered an email but did not receive the confirmation email?', 'insightx-form' ); ?></h4>
                        <p><?php echo __( 'Make sure the form has an <code>Email</code> field — the system automatically sends the confirmation email to that address', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Can I use the same form on multiple pages?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Yes — copy the same shortcode and paste it on as many pages as you like', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Will my data be lost after updating the plugin?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'No — data is stored in the WordPress database, separate from the plugin files', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Is the SMTP password secure?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Yes — the password is encrypted with AES-256-CBC using WordPress authentication salts as the key before being saved to the database', 'insightx-form' ); ?></p>

                        <h4><?php esc_html_e( 'Will data be deleted when the plugin is uninstalled?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Yes — uninstalling the plugin deletes all data tables and settings. If you want to keep your data, export a CSV first', 'insightx-form' ); ?></p>
                    </div>
                </div>

                <p style="text-align:center; color:#999; font-size:12px; margin-top:20px;">InsightX Form v<?php echo ISXF_PLUGIN_VERSION; ?> — Made by <a href="https://www.insightx.in.th" target="_blank">InsightX</a></p>
            </div>

            <script>
                document.querySelectorAll('.isxf-docs-header').forEach(function(h) {
                    h.addEventListener('click', function() {
                        this.parentElement.classList.toggle('open');
                    });
                });
            </script>
            
