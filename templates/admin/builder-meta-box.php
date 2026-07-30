<?php
/**
 * Template: form-builder meta box (CPT isxf_form edit screen).
 *
 * Extracted from ISXF\Admin::render_builder_meta_box() (Phase 2.2 view
 * split). The nonce field is still output by the render method itself.
 *
 * @var array  $fields         Field definitions for the builder table.
 * @var string $email_type     Selected email template type.
 * @var string $custom_subject Custom email subject.
 * @var string $custom_body    Custom email body.
 * @var string $button_color   Submit button color (hex, '' = theme default).
 * @var string $btn_text_color  Button text color (hex, '' = default white).
 * @var string $btn_hover_color Button hover color (hex, '' = auto shade).
 * @var string $btn_radius      Button border radius in px ('' = default 6).
 * @var string $btn_font_size   Button font size in px ('' = default 16).
 * @var string $btn_custom_css  Advanced custom CSS for the button (sanitized).
 */
?>

            <div class="isxf-settings-panel" style="margin-bottom: 10px;">
                <label style="font-weight:bold; font-size:14px; display:block; margin-bottom:10px;"><?php esc_html_e( '🎨 Submit Button Style', 'insightx-form' ); ?></label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                    <div>
                        <label for="isxf-button-color" style="display:block; font-size:12px; margin-bottom:4px;"><?php esc_html_e( 'Background', 'insightx-form' ); ?></label>
                        <input type="text" name="isxf_form_button_color" id="isxf-button-color" value="<?php echo esc_attr( $button_color ); ?>" class="isxf-color-picker" data-default-color="#0f172a">
                    </div>
                    <div>
                        <label for="isxf-button-text-color" style="display:block; font-size:12px; margin-bottom:4px;"><?php esc_html_e( 'Text', 'insightx-form' ); ?></label>
                        <input type="text" name="isxf_form_button_text_color" id="isxf-button-text-color" value="<?php echo esc_attr( $btn_text_color ); ?>" class="isxf-color-picker" data-default-color="#ffffff">
                    </div>
                    <div>
                        <label for="isxf-button-hover-color" style="display:block; font-size:12px; margin-bottom:4px;"><?php esc_html_e( 'Hover', 'insightx-form' ); ?></label>
                        <input type="text" name="isxf_form_button_hover_color" id="isxf-button-hover-color" value="<?php echo esc_attr( $btn_hover_color ); ?>" class="isxf-color-picker" data-default-color="#1e293b">
                    </div>
                    <div>
                        <label for="isxf-button-radius" style="display:block; font-size:12px; margin-bottom:4px;"><?php esc_html_e( 'Radius (px)', 'insightx-form' ); ?></label>
                        <input type="number" name="isxf_form_button_radius" id="isxf-button-radius" value="<?php echo esc_attr( $btn_radius ); ?>" min="0" max="100" placeholder="6" style="width:80px;">
                    </div>
                    <div>
                        <label for="isxf-button-font-size" style="display:block; font-size:12px; margin-bottom:4px;"><?php esc_html_e( 'Font size (px)', 'insightx-form' ); ?></label>
                        <input type="number" name="isxf_form_button_font_size" id="isxf-button-font-size" value="<?php echo esc_attr( $btn_font_size ); ?>" min="10" max="100" placeholder="16" style="width:80px;">
                    </div>
                </div>
                <p class="description" style="margin-top:8px;"><?php esc_html_e( 'Leave any field empty to use the default. Applies to this form only.', 'insightx-form' ); ?></p>
                <details style="margin-top:10px;">
                    <summary style="cursor:pointer; font-size:13px;"><?php esc_html_e( '⚙️ Advanced CSS (for the submit button)', 'insightx-form' ); ?></summary>
                    <textarea name="isxf_form_button_css" rows="4" class="large-text code" style="margin-top:8px;" placeholder="<?php esc_attr_e( 'e.g. box-shadow: 0 4px 12px rgba(0,0,0,.25); letter-spacing: 1px;', 'insightx-form' ); ?>"><?php echo esc_textarea( $btn_custom_css ); ?></textarea>
                    <p class="description" style="margin-top:4px;"><?php esc_html_e( 'CSS declarations only (no selector needed) — they are applied to this form’s submit button, scoped and sanitized automatically.', 'insightx-form' ); ?></p>
                </details>
            </div>

            <div class="isxf-settings-panel">
                <label style="font-weight:bold; font-size:14px; margin-right:10px;"><?php esc_html_e( '✉️ Customer Reply Email Template:', 'insightx-form' ); ?></label>
                <select name="isxf_form_email_type" id="isxf-email-type-select" style="min-width: 280px;">
                    <option value="booking" <?php selected($email_type, 'booking'); ?>><?php esc_html_e( '🏨 Booking Confirmation', 'insightx-form' ); ?></option>
                    <option value="inquiry" <?php selected($email_type, 'inquiry'); ?>><?php esc_html_e( '📩 General Inquiry', 'insightx-form' ); ?></option>
                    <option value="custom" <?php selected($email_type, 'custom'); ?>><?php esc_html_e( '✏️ Custom Template', 'insightx-form' ); ?></option>
                </select>
                <p class="description" style="margin-top:5px;"><?php esc_html_e( 'Select the email content format to send back to the customer when this form is submitted', 'insightx-form' ); ?></p>

                <div class="isxf-email-tools" style="margin-top: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="button" id="isxf-email-preview-btn"><?php esc_html_e( '👁️ Preview', 'insightx-form' ); ?></button>
                    <button type="button" class="button" id="isxf-email-copy-template-btn"<?php echo $email_type === 'custom' ? ' style="display:none;"' : ''; ?>><?php esc_html_e( '✏️ Edit from this template', 'insightx-form' ); ?></button>
                    <button type="button" class="button" id="isxf-email-test-send-btn"><?php esc_html_e( '🧪 Send a test email to me', 'insightx-form' ); ?></button>
                    <span id="isxf-email-tools-result" style="display:none; padding: 4px 10px; border-radius: 4px; font-size: 13px;"></span>
                </div>

                <div id="isxf-custom-email-panel" class="isxf-custom-email-panel <?php echo $email_type === 'custom' ? 'active' : ''; ?>">
                    <div style="margin-bottom: 12px;">
                        <label><?php esc_html_e( '📌 Email Subject:', 'insightx-form' ); ?></label>
                        <input type="text" name="isxf_form_email_subject" value="<?php echo esc_attr($custom_subject); ?>" placeholder="<?php esc_attr_e( 'e.g. Thank you for contacting us - {site_name}', 'insightx-form' ); ?>">
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label><?php esc_html_e( '📝 Email Body:', 'insightx-form' ); ?></label>
                        <textarea name="isxf_form_email_body" placeholder="<?php esc_attr_e( 'Type email content, HTML supported...', 'insightx-form' ); ?>"><?php echo esc_textarea($custom_body); ?></textarea>
                        <p class="description" style="margin-top:5px;"><?php esc_html_e( 'Supports both plain text and HTML — the system will automatically wrap it in a beautiful layout', 'insightx-form' ); ?></p>
                    </div>

                    <div style="background: #f0f7ff; border: 1px solid #cce5ff; padding: 12px 15px; border-radius: 4px;">
                        <strong style="color: #004085; font-size: 13px;"><?php esc_html_e( '🏷️ Available Merge Tags (click to insert):', 'insightx-form' ); ?></strong>
                        <div class="isxf-merge-tags" style="margin-top: 8px;">
                            <span class="isxf-merge-tag" data-tag="{site_name}">{site_name}</span>
                            <span class="isxf-merge-tag" data-tag="{form_title}">{form_title}</span>
                            <span class="isxf-merge-tag" data-tag="{all_fields}">{all_fields}</span>
                            <?php foreach ( $fields as $f ) : if ( !isset($f['type']) || $f['type'] === 'heading' ) continue; ?>
                                <span class="isxf-merge-tag" data-tag="{field:<?php echo esc_attr($f['label']); ?>}">{field:<?php echo esc_html($f['label']); ?>}</span>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin: 8px 0 0; font-size: 12px; color: #666;"><?php esc_html_e( 'Click a tag to insert it into the email body field', 'insightx-form' ); ?> • <code>{all_fields}</code> = <?php esc_html_e( 'table of all data', 'insightx-form' ); ?> • <code>{field:<?php esc_html_e( 'name', 'insightx-form' ); ?>}</code> = <?php esc_html_e( 'value of that field', 'insightx-form' ); ?></p>
                    </div>
                </div>
            </div>

            <div id="field-list-wrapper" style="margin-top: 10px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;"><?php esc_html_e( 'Move', 'insightx-form' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Label (text)', 'insightx-form' ); ?></th>
                            <th style="width: 15%;">Field Name</th>
                            <th style="width: 12%;">Type</th>
                            <th style="width: 15%;"><?php esc_html_e( 'Placeholder (text only)', 'insightx-form' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Options (separated by commas ",")', 'insightx-form' ); ?></th>
                            <th style="width: 8%;">Width</th>
                            <th style="width: 5%;"><?php esc_html_e( 'Required?', 'insightx-form' ); ?></th>
                            <th style="width: 5%; text-align: center;"><?php esc_html_e( 'Actions', 'insightx-form' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="field-list">
                        <?php foreach ( $fields as $index => $field ) : ?>
                            <tr class="field-row">
                                <td class="drag-handle" title="<?php esc_attr_e( 'Click and hold to drag to reorder', 'insightx-form' ); ?>">☰</td>
                                <td><input type="text" name="isxf_fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" required style="width:100%;"></td>
                                <td><input type="text" name="isxf_fields[<?php echo $index; ?>][name]" value="<?php echo esc_attr( $field['name'] ); ?>" style="width:100%;"></td>
                                <td>
                                    <select name="isxf_fields[<?php echo $index; ?>][type]" class="field-type-select" style="width:100%;">
                                        <option value="text" <?php selected($field['type'], 'text'); ?>><?php esc_html_e( 'Text (short text)', 'insightx-form' ); ?></option>
                                        <option value="textarea" <?php selected($field['type'], 'textarea'); ?>><?php esc_html_e( 'Textarea (long text)', 'insightx-form' ); ?></option>
                                        <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                        <option value="tel" <?php selected($field['type'], 'tel'); ?>>Telephone</option>
                                        <option value="number" <?php selected($field['type'], 'number'); ?>><?php esc_html_e( 'Number', 'insightx-form' ); ?></option>
                                        <option value="date" <?php selected($field['type'], 'date'); ?>><?php esc_html_e( 'Date (standard calendar)', 'insightx-form' ); ?></option>
                                        <option value="check_in" <?php selected($field['type'], 'check_in'); ?>><?php esc_html_e( 'Check-in Date', 'insightx-form' ); ?></option>
                                        <option value="check_out" <?php selected($field['type'], 'check_out'); ?>><?php esc_html_e( 'Check-out Date', 'insightx-form' ); ?></option>
                                        <option value="select" <?php selected($field['type'], 'select'); ?>>Select (Dropdown)</option>
                                        <option value="radio" <?php selected($field['type'], 'radio'); ?>><?php esc_html_e( 'Radio (choose 1 option)', 'insightx-form' ); ?></option>
                                        <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>><?php esc_html_e( 'Checkbox (choose multiple)', 'insightx-form' ); ?></option>
                                        <option value="heading" <?php selected($field['type'], 'heading'); ?>><?php esc_html_e( 'Heading (form heading)', 'insightx-form' ); ?></option>
                                    </select>
                                </td>
                                <td><input type="text" name="isxf_fields[<?php echo $index; ?>][placeholder]" class="field-placeholder-input" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Type sample text', 'insightx-form' ); ?>" style="width:100%;"></td>
                                <td><input type="text" name="isxf_fields[<?php echo $index; ?>][options]" class="field-options-input" value="<?php echo esc_attr( $field['options'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Red,Green,Blue', 'insightx-form' ); ?>" style="width:100%;"></td>
                                <td><select name="isxf_fields[<?php echo $index; ?>][width]" style="width:100%"><option value="100" <?php selected($field['width'] ?? '100', '100'); ?>>100%</option><option value="50" <?php selected($field['width'] ?? '100', '50'); ?>>50%</option></select></td>
                                <td><select name="isxf_fields[<?php echo $index; ?>][required]" class="field-required-select"><option value="yes" <?php selected($field['required'], 'yes'); ?>>Yes</option><option value="no" <?php selected($field['required'], 'no'); ?>>No</option></select></td>
                                <td style="text-align: center;"><button type="button" class="button remove-row" style="color:red;" title="<?php esc_attr_e( 'Delete this field', 'insightx-form' ); ?>">❌</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br><button type="button" class="button button-primary" id="add-row"><?php esc_html_e( '+ Add New Field', 'insightx-form' ); ?></button>
            </div>

            <div id="isxf-email-preview-modal" class="isxf-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="isxf-email-preview-title">
                <div class="isxf-modal-backdrop" id="isxf-email-preview-backdrop"></div>
                <div class="isxf-modal-dialog">
                    <div class="isxf-modal-header">
                        <strong id="isxf-email-preview-title"><?php esc_html_e( 'Email Preview', 'insightx-form' ); ?></strong>
                        <span id="isxf-email-preview-subject" class="isxf-modal-subject"></span>
                        <button type="button" class="isxf-modal-close" id="isxf-email-preview-close" aria-label="<?php esc_attr_e( 'Close preview', 'insightx-form' ); ?>">&times;</button>
                    </div>
                    <iframe id="isxf-email-preview-frame" title="<?php esc_attr_e( 'Email Preview', 'insightx-form' ); ?>"></iframe>
                </div>
            </div>
            

            
