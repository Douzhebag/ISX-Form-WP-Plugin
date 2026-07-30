/**
 * InsightX Form — Admin Scripts
 * Settings page (captcha toggle, SMTP test) + Form builder (sortable, field state)
 */
(function () {
    'use strict';

    // === Form builder: submit-button color picker (wp-color-picker, jQuery) ===
    if ( window.jQuery && jQuery.fn.wpColorPicker ) {
        jQuery( '.isxf-color-picker' ).wpColorPicker();
    }

    // === Settings Page: Captcha service toggle ===
    var captchaSelect = document.getElementById('captcha_select');
    if (captchaSelect) {
        captchaSelect.onchange = function () {
            document.getElementById('settings_google').style.display = (this.value === 'google') ? 'block' : 'none';
            document.getElementById('settings_cloudflare').style.display = (this.value === 'cloudflare') ? 'block' : 'none';
        };
    }

    // === Settings Page: SMTP auth method toggle (Basic vs OAuth2) ===
    var authSelect = document.getElementById('isxf_auth_method');
    if (authSelect) {
        var toggleAuth = function () {
            var v = authSelect.value;
            var isOauth = (v === 'oauth_google' || v === 'oauth_microsoft');
            var basic = document.getElementById('isxf_auth_password');
            var oauth = document.getElementById('isxf_auth_oauth');
            var tenantRow = document.getElementById('isxf_oauth_tenant_row');
            if (basic) basic.style.display = isOauth ? 'none' : 'block';
            if (oauth) oauth.style.display = isOauth ? 'block' : 'none';
            if (tenantRow) tenantRow.style.display = (v === 'oauth_microsoft') ? 'table-row' : 'none';
        };
        authSelect.onchange = toggleAuth;
        toggleAuth();
    }

    // === Settings Page: SMTP basic-auth preset autofill ===
    var presetSelect = document.getElementById('isxf_smtp_preset');
    if (presetSelect) {
        presetSelect.onchange = function () {
            var host = document.querySelector('input[name="isxf_smtp_host"]');
            var port = document.querySelector('input[name="isxf_smtp_port"]');
            if (this.value === 'gmail') {
                if (host) host.value = 'smtp.gmail.com';
                if (port) port.value = '587';
            } else if (this.value === 'm365') {
                if (host) host.value = 'smtp.office365.com';
                if (port) port.value = '587';
            }
        };
    }

    // === Settings Page: SMTP test email ===
    var testBtn = document.getElementById('isxf-test-email-btn');
    if (testBtn) {
        var testInput = document.getElementById('isxf-test-email-to');
        var testResult = document.getElementById('isxf-test-email-result');

        testBtn.addEventListener('click', function () {
            var email = testInput.value.trim();
            if (!email) { testInput.focus(); return; }

            testBtn.disabled = true;
            testBtn.textContent = isxf_admin_env.i18n.sending;
            testResult.style.display = 'none';

            var fd = new FormData();
            fd.append('action', 'isxf_send_test_email');
            fd.append('nonce', isxf_admin_env.test_email_nonce);
            fd.append('test_email', email);

            fetch(isxf_admin_env.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    testResult.style.display = 'block';
                    if (data.success) {
                        testResult.style.background = '#edfaef';
                        testResult.style.border = '1px solid #46b450';
                        testResult.style.color = '#2e7d32';
                        testResult.textContent = '✅ ' + data.data.message;
                    } else {
                        testResult.style.background = '#fef0f0';
                        testResult.style.border = '1px solid #dc3232';
                        testResult.style.color = '#a00';
                        testResult.textContent = '❌ ' + data.data.message;
                    }
                })
                .catch(function () {
                    testResult.style.display = 'block';
                    testResult.style.background = '#fef0f0';
                    testResult.style.border = '1px solid #dc3232';
                    testResult.style.color = '#a00';
                    testResult.textContent = isxf_admin_env.i18n.conn_error;
                })
                .finally(function () {
                    testBtn.disabled = false;
                    testBtn.textContent = isxf_admin_env.i18n.send_test_email;
                });
        });
    }

    // === Form Builder: Sortable, field state, add/remove rows ===
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function ($) {
            var fieldList = $('#field-list');
            if (!fieldList.length) return;

            fieldList.sortable({
                handle: '.drag-handle', axis: 'y', cursor: 'grabbing', opacity: 0.8,
                helper: function (e, ui) { ui.children().each(function () { $(this).width($(this).width()); }); return ui; }
            });

            function updateFieldState(selectElement) {
                var tr = $(selectElement).closest('tr');
                var type = $(selectElement).val();
                var optionsInput = tr.find('.field-options-input');
                var placeholderInput = tr.find('.field-placeholder-input');
                var requiredSelect = tr.find('.field-required-select');

                if (['select', 'radio', 'checkbox'].indexOf(type) !== -1) {
                    optionsInput.prop('disabled', false).prop('placeholder', isxf_admin_env.i18n.options_example).css('opacity', '1');
                } else {
                    optionsInput.prop('disabled', true).prop('placeholder', isxf_admin_env.i18n.options_none).val('').css('opacity', '0.3');
                }

                if (['text', 'textarea', 'email', 'tel', 'number', 'date', 'check_in', 'check_out'].indexOf(type) !== -1) {
                    placeholderInput.prop('disabled', false).prop('placeholder', isxf_admin_env.i18n.placeholder_sample).css('opacity', '1');
                } else {
                    placeholderInput.prop('disabled', true).prop('placeholder', isxf_admin_env.i18n.placeholder_none).val('').css('opacity', '0.3');
                }

                if (type === 'heading') {
                    requiredSelect.val('no').prop('disabled', true).css('opacity', '0.3');
                } else {
                    requiredSelect.prop('disabled', false).css('opacity', '1');
                }
            }

            $('.field-type-select').each(function () { updateFieldState(this); });
            fieldList.on('change', '.field-type-select', function () { updateFieldState(this); });

            var r = (typeof isxf_admin_env !== 'undefined' && isxf_admin_env.field_count) ? parseInt(isxf_admin_env.field_count) + 100 : 200;
            $('#add-row').on('click', function () {
                var tr = '<tr class="field-row">' +
                    '<td class="drag-handle" title="' + isxf_admin_env.i18n.drag_title + '">☰</td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][label]" required style="width:100%;"></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][name]" style="width:100%;"></td>' +
                    '<td><select name="isxf_fields[' + r + '][type]" class="field-type-select" style="width:100%">' +
                    '<option value="text">' + isxf_admin_env.i18n.type_text + '</option>' +
                    '<option value="textarea">' + isxf_admin_env.i18n.type_textarea + '</option>' +
                    '<option value="email">Email</option>' +
                    '<option value="tel">Telephone</option>' +
                    '<option value="number">' + isxf_admin_env.i18n.type_number + '</option>' +
                    '<option value="date">' + isxf_admin_env.i18n.type_date + '</option>' +
                    '<option value="check_in">📅 ' + isxf_admin_env.i18n.type_check_in + '</option>' +
                    '<option value="check_out">📅 ' + isxf_admin_env.i18n.type_check_out + '</option>' +
                    '<option value="select">Select (Dropdown)</option>' +
                    '<option value="radio">' + isxf_admin_env.i18n.type_radio + '</option>' +
                    '<option value="checkbox">' + isxf_admin_env.i18n.type_checkbox + '</option>' +
                    '<option value="heading">' + isxf_admin_env.i18n.type_heading + '</option>' +
                    '</select></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][placeholder]" class="field-placeholder-input" placeholder="' + isxf_admin_env.i18n.placeholder_sample + '" style="width:100%;"></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][options]" class="field-options-input" placeholder="' + isxf_admin_env.i18n.options_none + '" disabled style="width:100%; opacity:0.3;"></td>' +
                    '<td><select name="isxf_fields[' + r + '][width]" style="width:100%"><option value="100">100%</option><option value="50">50%</option></select></td>' +
                    '<td><select name="isxf_fields[' + r + '][required]" class="field-required-select"><option value="yes">Yes</option><option value="no">No</option></select></td>' +
                    '<td style="text-align: center;"><button type="button" class="button remove-row" style="color:red;" title="' + isxf_admin_env.i18n.remove_title + '">❌</button></td>' +
                    '</tr>';
                fieldList.append(tr);
                updateFieldState(fieldList.find('tr:last .field-type-select'));
                r++;
            });

            // Email type toggle
            var emailSelect = document.getElementById('isxf-email-type-select');
            var customPanel = document.getElementById('isxf-custom-email-panel');
            var copyTplBtn = document.getElementById('isxf-email-copy-template-btn');
            if (emailSelect && customPanel) {
                emailSelect.addEventListener('change', function () {
                    customPanel.classList.toggle('active', this.value === 'custom');
                    if (copyTplBtn) copyTplBtn.style.display = (this.value === 'custom') ? 'none' : '';
                });
            }

            // === Email template tools: preview / edit-from-template / test send ===
            var previewBtn = document.getElementById('isxf-email-preview-btn');
            var testSendBtn = document.getElementById('isxf-email-test-send-btn');
            if (previewBtn && emailSelect) {
                var toolsResult = document.getElementById('isxf-email-tools-result');
                var modal = document.getElementById('isxf-email-preview-modal');
                var previewFrame = document.getElementById('isxf-email-preview-frame');
                var previewSubject = document.getElementById('isxf-email-preview-subject');
                var formIdInput = document.getElementById('post_ID');

                var collectFields = function () {
                    var fields = [];
                    $('#field-list tr.field-row').each(function () {
                        var $tr = $(this);
                        var label = $tr.find('input[name$="[label]"]').val();
                        if (!label) return;
                        fields.push({
                            label: label,
                            name: $tr.find('input[name$="[name]"]').val() || '',
                            type: $tr.find('select[name$="[type]"]').val() || 'text',
                            options: $tr.find('input[name$="[options]"]').val() || ''
                        });
                    });
                    return fields;
                };

                var buildRequest = function (action) {
                    var fd = new FormData();
                    fd.append('action', action);
                    fd.append('nonce', isxf_admin_env.email_tools_nonce);
                    fd.append('template', emailSelect.value);
                    fd.append('form_id', formIdInput ? formIdInput.value : '0');
                    if (emailSelect.value === 'custom') {
                        fd.append('subject', $('input[name="isxf_form_email_subject"]').val() || '');
                        fd.append('body', $('textarea[name="isxf_form_email_body"]').val() || '');
                    }
                    collectFields().forEach(function (f, i) {
                        fd.append('fields[' + i + '][label]', f.label);
                        fd.append('fields[' + i + '][name]', f.name);
                        fd.append('fields[' + i + '][type]', f.type);
                        fd.append('fields[' + i + '][options]', f.options);
                    });
                    return fd;
                };

                var postEmailTools = function (action) {
                    return fetch(isxf_admin_env.ajax_url, { method: 'POST', body: buildRequest(action) })
                        .then(function (r) { return r.json(); });
                };

                var showToolsResult = function (message, ok) {
                    toolsResult.style.display = 'inline-block';
                    toolsResult.style.background = ok ? '#edfaef' : '#fef0f0';
                    toolsResult.style.border = ok ? '1px solid #46b450' : '1px solid #dc3232';
                    toolsResult.style.color = ok ? '#2e7d32' : '#a00';
                    toolsResult.textContent = message;
                };

                var openModal = function () { modal.style.display = 'flex'; };
                var closeModal = function () { modal.style.display = 'none'; previewFrame.srcdoc = ''; };
                document.getElementById('isxf-email-preview-close').addEventListener('click', closeModal);
                document.getElementById('isxf-email-preview-backdrop').addEventListener('click', closeModal);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
                });

                // 👁️ Preview — render the selected template with sample data in a modal iframe
                previewBtn.addEventListener('click', function () {
                    var label = previewBtn.textContent;
                    previewBtn.disabled = true;
                    previewBtn.textContent = isxf_admin_env.i18n.loading_preview;
                    toolsResult.style.display = 'none';

                    postEmailTools('isxf_preview_email')
                        .then(function (data) {
                            if (data.success) {
                                previewSubject.textContent = data.data.subject;
                                previewFrame.srcdoc = data.data.html;
                                openModal();
                            } else {
                                showToolsResult((data.data && data.data.message) ? data.data.message : isxf_admin_env.i18n.preview_failed, false);
                            }
                        })
                        .catch(function () { showToolsResult(isxf_admin_env.i18n.conn_error, false); })
                        .finally(function () {
                            previewBtn.disabled = false;
                            previewBtn.textContent = label;
                        });
                });

                // ✏️ Edit from this template — copy the inner template markup (merge
                // tags intact) into the custom editor and switch the select to custom
                if (copyTplBtn) {
                    copyTplBtn.addEventListener('click', function () {
                        var bodyField = $('textarea[name="isxf_form_email_body"]');
                        if (bodyField.val() && !window.confirm(isxf_admin_env.i18n.confirm_overwrite)) return;

                        var label = copyTplBtn.textContent;
                        copyTplBtn.disabled = true;
                        copyTplBtn.textContent = isxf_admin_env.i18n.loading_preview;
                        toolsResult.style.display = 'none';

                        postEmailTools('isxf_preview_email')
                            .then(function (data) {
                                if (data.success && data.data.editable_body) {
                                    emailSelect.value = 'custom';
                                    emailSelect.dispatchEvent(new Event('change'));
                                    $('input[name="isxf_form_email_subject"]').val(data.data.editable_subject);
                                    bodyField.val(data.data.editable_body);
                                    showToolsResult(isxf_admin_env.i18n.copied_to_editor, true);
                                } else {
                                    showToolsResult((data.data && data.data.message) ? data.data.message : isxf_admin_env.i18n.preview_failed, false);
                                }
                            })
                            .catch(function () { showToolsResult(isxf_admin_env.i18n.conn_error, false); })
                            .finally(function () {
                                copyTplBtn.disabled = false;
                                copyTplBtn.textContent = label;
                            });
                    });
                }

                // 🧪 Send test email to the current admin user (sample data, 🧪-prefixed subject)
                if (testSendBtn) {
                    testSendBtn.addEventListener('click', function () {
                        var label = testSendBtn.textContent;
                        testSendBtn.disabled = true;
                        testSendBtn.textContent = isxf_admin_env.i18n.sending;
                        toolsResult.style.display = 'none';

                        postEmailTools('isxf_send_template_test')
                            .then(function (data) {
                                showToolsResult(data.data.message, !!data.success);
                            })
                            .catch(function () { showToolsResult(isxf_admin_env.i18n.conn_error, false); })
                            .finally(function () {
                                testSendBtn.disabled = false;
                                testSendBtn.textContent = label;
                            });
                    });
                }
            }

            // Merge tag click-to-insert
            $(document).on('click', '.isxf-merge-tag', function () {
                var tag = $(this).data('tag');
                var textarea = $('textarea[name="isxf_form_email_body"]');
                if (textarea.length) {
                    var el = textarea[0];
                    var start = el.selectionStart;
                    var end = el.selectionEnd;
                    var text = el.value;
                    el.value = text.substring(0, start) + tag + text.substring(end);
                    el.selectionStart = el.selectionEnd = start + tag.length;
                    el.focus();
                }
            });

            fieldList.on('click', '.remove-row', function () { $(this).closest('tr').remove(); });
        });
    }
})();
