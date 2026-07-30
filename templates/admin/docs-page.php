<?php
/**
 * Template: Documentation page (wp-admin → แบบฟอร์ม → 📖 คู่มือการใช้งาน).
 *
 * Extracted from ISXF\Admin::render_docs_page() (Phase 2.2 view split).
 * Static markup; uses the ISXF_PLUGIN_VERSION constant directly.
 */
?>
            <style>
                .isxf-docs-wrap { max-width:900px; }
                .isxf-docs-wrap h1 { display:flex; align-items:center; gap:10px; }
                .isxf-docs-ver { font-size:12px; background:#2271b1; color:#fff; padding:2px 10px; border-radius:12px; font-weight:400; }
                .isxf-docs-section { background:#fff; border:1px solid #ccd0d4; border-radius:6px; margin-bottom:12px; overflow:hidden; }
                .isxf-docs-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; cursor:pointer; user-select:none; transition:background 0.2s; font-weight:600; font-size:14px; color:#1d2327; }
                .isxf-docs-header:hover { background:#f6f7f7; }
                .isxf-docs-arrow { transition:transform 0.3s; font-size:12px; color:#999; }
                .isxf-docs-section.open .isxf-docs-arrow { transform:rotate(180deg); }
                .isxf-docs-body { display:none; padding:0 20px 20px; color:#50575e; font-size:13px; line-height:1.8; }
                .isxf-docs-section.open .isxf-docs-body { display:block; }
                .isxf-docs-body h4 { color:#1d2327; margin:18px 0 8px; font-size:14px; }
                .isxf-docs-body table { width:100%; border-collapse:collapse; margin:10px 0; font-size:13px; }
                .isxf-docs-body th { background:#f0f0f1; text-align:left; padding:8px 12px; border:1px solid #ddd; font-weight:600; }
                .isxf-docs-body td { padding:8px 12px; border:1px solid #eee; }
                .isxf-docs-body code { background:#f0f0f1; padding:2px 6px; border-radius:3px; font-size:12px; }
                .isxf-docs-body .step { display:flex; gap:12px; margin:8px 0; }
                .isxf-docs-body .step-num { flex-shrink:0; width:24px; height:24px; background:#2271b1; color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; margin-top:1px; }
                .isxf-docs-body .step-text { flex:1; }
                .isxf-docs-body .tip-box { background:#f0f7ff; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; font-size:12px; }
                .isxf-docs-body .warn-box { background:#fff8e5; border-left:4px solid #dba617; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; font-size:12px; }
            </style>

            <div class="wrap isxf-docs-wrap">
                <h1><?php esc_html_e( '📖 User Guide', 'insightx-form' ); ?> <span class="isxf-docs-ver">v<?php echo ISXF_PLUGIN_VERSION; ?></span></h1>
                <p style="color:#50575e; margin-bottom:20px;"><?php esc_html_e( 'InsightX Form — A form and customer data management system for businesses', 'insightx-form' ); ?></p>

                <div class="isxf-docs-section open">
                    <div class="isxf-docs-header"><?php esc_html_e( '📝 Creating a Form', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>Forms → Create New Form</strong>', 'insightx-form' ); ?></span></div>
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
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '🖥️ Displaying a Form on Your Site', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>Forms → All Forms</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Copy the shortcode, e.g. <code>[advanced_form id="123"]</code>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php esc_html_e( 'Paste it into the page or post where you want it to appear', 'insightx-form' ); ?></span></div>
                        <div class="tip-box"><?php esc_html_e( '💡 The same shortcode can be placed on multiple pages', 'insightx-form' ); ?></div>
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '📧 SMTP Settings & Email Testing', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <strong>Forms → ⚙️ System Settings</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Check <strong>Enable SMTP</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php esc_html_e( 'Enter the Host, Port, Username, and Password', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php echo __( 'Click <strong>Save</strong>, then scroll down and click <strong>"📨 Send Test Email"</strong>', 'insightx-form' ); ?></span></div>

                        <h4><?php esc_html_e( 'Example Values for Gmail', 'insightx-form' ); ?></h4>
                        <table>
                            <tr><th><?php esc_html_e( 'Field', 'insightx-form' ); ?></th><th><?php esc_html_e( 'Value', 'insightx-form' ); ?></th></tr>
                            <tr><td>Host</td><td><code>smtp.gmail.com</code></td></tr>
                            <tr><td>Port</td><td><code>587</code></td></tr>
                            <tr><td>Username</td><td><?php esc_html_e( 'Your Gmail address', 'insightx-form' ); ?></td></tr>
                            <tr><td>Password</td><td><?php esc_html_e( '16-character App Password', 'insightx-form' ); ?></td></tr>
                        </table>
                        <div class="warn-box"><?php esc_html_e( '⚠️ You must enable 2-Step Verification in your Google Account and create an App Password before it will work', 'insightx-form' ); ?></div>

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
                    <div class="isxf-docs-header"><?php esc_html_e( '🔐 Connect SMTP via OAuth2 (Google / Microsoft 365)', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php echo __( 'OAuth2 is more secure than Basic Auth and <strong>required for Microsoft 365</strong> because Microsoft has disabled Basic Auth (SMTP AUTH) for Exchange Online. In the settings page, set "Authentication Method" to Google OAuth2 or Microsoft 365 OAuth2', 'insightx-form' ); ?></p>

                        <div class="tip-box"><?php echo __( '💡 The settings page has a <strong>Redirect URI</strong> field — copy it into the steps below. It must match 100%', 'insightx-form' ); ?></div>

                        <h4><?php esc_html_e( '🟦 Google (Gmail / Workspace)', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console → Credentials</a> and create a project (if you do not have one yet)', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Configure the <strong>OAuth consent screen</strong> and add the scope <code>https://mail.google.com/</code>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Create an <strong>OAuth client ID</strong> of type <em>Web application</em>, then enter the <strong>Redirect URI</strong> from the settings page', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php echo __( 'Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the settings page → click <strong>Save</strong> → click <strong>Connect Account</strong>', 'insightx-form' ); ?></span></div>

                        <h4><?php esc_html_e( '🟧 Microsoft 365 (Outlook / Exchange Online)', 'insightx-form' ); ?></h4>
                        <div class="step"><span class="step-num">1</span><span class="step-text"><?php echo __( 'Go to <a href="https://entra.microsoft.com/" target="_blank">Microsoft Entra (Azure AD) → App registrations</a> → New registration', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">2</span><span class="step-text"><?php echo __( 'Set a <em>Web</em> Redirect URI to the value from the settings page', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">3</span><span class="step-text"><?php echo __( 'Go to <strong>API permissions</strong> → add <code>https://outlook.office.com/SMTP.Send</code> and <code>offline_access</code>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">4</span><span class="step-text"><?php echo __( 'Go to <strong>Certificates &amp; secrets</strong> → create a <strong>Client secret</strong>', 'insightx-form' ); ?></span></div>
                        <div class="step"><span class="step-num">5</span><span class="step-text"><?php echo __( 'Enter the <strong>Client ID</strong>, <strong>Client Secret</strong>, and <strong>Tenant ID</strong> (Directory ID) on the settings page → click <strong>Save</strong> → click <strong>Connect Account</strong>', 'insightx-form' ); ?></span></div>

                        <div class="warn-box"><?php echo __( '⚠️ An Exchange administrator must also grant the <code>SMTP.Send</code> permission to the mailbox used to send email', 'insightx-form' ); ?></div>
                        <div class="tip-box"><?php esc_html_e( '🔒 The Client Secret and Refresh Token are encrypted with AES-256-CBC before being stored in the database', 'insightx-form' ); ?></div>
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
                    </div>
                </div>

                <div class="isxf-docs-section">
                    <div class="isxf-docs-header"><?php esc_html_e( '✉️ Custom Email Template (New)', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <p><?php esc_html_e( 'Since v0.3.0 you can customize the customer auto-reply email content from the form editor', 'insightx-form' ); ?></p>

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
                        <p><?php echo __( 'Go to <strong>Forms → 📥 Entries</strong>', 'insightx-form' ); ?></p>

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
                    <div class="isxf-docs-header"><?php esc_html_e( '❓ Frequently Asked Questions (FAQ)', 'insightx-form' ); ?> <span class="isxf-docs-arrow">▼</span></div>
                    <div class="isxf-docs-body">
                        <h4><?php esc_html_e( 'The form is not sending email — what should I do?', 'insightx-form' ); ?></h4>
                        <p><?php esc_html_e( 'Check your SMTP settings → use the "Send Test Email" button to see the error message', 'insightx-form' ); ?></p>

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
            
