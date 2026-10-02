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

    // === Settings Page: save global settings automatically ===
    var settingsForm = document.querySelector('.isxf-settings form[action="options.php"]');
    if (settingsForm) {
        var saveTimer = null;
        var saveInProgress = false;
        var saveAgain = false;
        var saveToast = document.createElement('div');
        saveToast.className = 'isxf-settings-toast';
        saveToast.setAttribute('role', 'status');
        saveToast.setAttribute('aria-live', 'polite');
        saveToast.hidden = true; // nothing to say until the first save
        document.body.appendChild(saveToast);

        var showSaveToast = function (message, state) {
            saveToast.textContent = message;
            saveToast.className = 'isxf-settings-toast is-' + state;
            saveToast.hidden = false;
            window.clearTimeout(saveToast.hideTimer);
            if (state !== 'saving') {
                saveToast.hideTimer = window.setTimeout(function () {
                    saveToast.hidden = true;
                }, 2800);
            }
        };

        var saveSettings = function () {
            if (saveInProgress) {
                saveAgain = true;
                return;
            }
            saveInProgress = true;
            saveAgain = false;
            showSaveToast(isxf_admin_env.i18n.settings_saving, 'saving');

            var fd = new FormData();
            fd.append('action', 'isxf_save_settings');
            fd.append('nonce', isxf_admin_env.settings_save_nonce);
            settingsForm.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
                if (field.name === 'option_page' || field.name === '_wpnonce' || field.name === '_wp_http_referer') return;
                if (field.type === 'checkbox') {
                    if (field.checked) fd.set('settings[' + field.name + ']', field.value || 'yes');
                    else if (!fd.has('settings[' + field.name + ']')) fd.set('settings[' + field.name + ']', '');
                } else if (field.type !== 'button' && field.type !== 'submit') {
                    fd.set('settings[' + field.name + ']', field.value);
                }
            });

            fetch(isxf_admin_env.ajax_url, { method: 'POST', body: fd })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) showSaveToast(isxf_admin_env.i18n.settings_saved, 'success');
                    else showSaveToast(data.data && data.data.message ? data.data.message : isxf_admin_env.i18n.settings_save_failed, 'error');
                })
                .catch(function () { showSaveToast(isxf_admin_env.i18n.settings_save_failed, 'error'); })
                .finally(function () {
                    saveInProgress = false;
                    if (saveAgain) saveSettings();
                });
        };

        var queueSettingsSave = function () {
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(saveSettings, 650);
        };
        settingsForm.addEventListener('input', queueSettingsSave);
        settingsForm.addEventListener('change', queueSettingsSave);
        settingsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(saveTimer);
            saveSettings();
        });
        window.isxfSaveSettingsNow = function () {
            window.clearTimeout(saveTimer);
            saveSettings();
        };
    }

    // === Settings Page: SMTP auth method toggle (Basic vs OAuth2) ===
    var authSelect = document.getElementById('isxf_auth_method');
    if (authSelect) {
        var toggleAuth = function () {
            var v = authSelect.value;
            var isOauth = (v === 'oauth_google');
            var basic = document.getElementById('isxf_auth_password');
            var oauth = document.getElementById('isxf_auth_oauth');
            if (basic) basic.style.display = isOauth ? 'none' : 'block';
            if (oauth) oauth.style.display = isOauth ? 'block' : 'none';
        };
        authSelect.onchange = toggleAuth;
        toggleAuth();
    }

    // === Settings Page: SMTP basic-auth preset autofill ===
    var applyPreset = function (preset) {
        var host = document.querySelector('input[name="isxf_smtp_host"]');
        var port = document.querySelector('input[name="isxf_smtp_port"]');
        var user = document.querySelector('input[name="isxf_smtp_user"]');
        if (preset === 'resend') {
            if (host) host.value = 'smtp.resend.com';
            if (port) port.value = '587';
            if (user) user.value = 'resend';
        } else if (preset === 'cloudflare') {
            // Cloudflare Email Service: implicit TLS on 465 only (no STARTTLS/587).
            if (host) host.value = 'smtp.mx.cloudflare.net';
            if (port) port.value = '465';
            if (user) user.value = 'api_token';
        }
    };

    // === Settings Page: provider cards ===
    // Each card sets the (hidden) auth-method select and fills its preset,
    // so what gets saved is exactly what the old dropdowns saved.
    var providerCards = document.querySelectorAll('.isxf-provider-card');
    providerCards.forEach(function (card) {
        card.addEventListener('click', function () {
            providerCards.forEach(function (c) {
                var on = (c === card);
                c.classList.toggle('is-selected', on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            if (authSelect) {
                authSelect.value = card.dataset.auth;
                toggleAuth();
            }
            setSmtpBadge('idle', isxf_admin_env.i18n.smtp_not_checked);
            applyPreset(card.dataset.preset);

            // Show only the setup note of the chosen provider (none for Google / Custom).
            var notes = document.getElementById('isxf-provider-notes');
            if (notes) {
                var any = false;
                notes.querySelectorAll('[data-provider-note]').forEach(function (n) {
                    var show = (n.dataset.providerNote === card.dataset.preset);
                    n.hidden = !show;
                    if (show) any = true;
                });
                notes.hidden = !any;
            }
            if (window.isxfSaveSettingsNow) window.isxfSaveSettingsNow();
        });
    });

    // === Settings Page: verify saved SMTP credentials without sending mail ===
    // Badge in the SMTP header (green = connected, red = failed, grey = not
    // checked) + the reason under the button when it failed.
    var smtpConnectBtn = document.getElementById('isxf-test-smtp-connection-btn');
    var smtpBadge = document.getElementById('isxf-smtp-badge');
    var setSmtpBadge = function (state, text) {
        if (!smtpBadge) return;
        smtpBadge.setAttribute('data-state', state);
        smtpBadge.querySelector('.isxf-conn-badge-text').textContent = text;
    };
    if (smtpConnectBtn) {
        var smtpConnectResult = document.getElementById('isxf-test-smtp-connection-result');
        var smtpConnectLabel = smtpConnectBtn.textContent;
        var i18n = isxf_admin_env.i18n;
        var showReason = function (text) {
            smtpConnectResult.textContent = text || '';
            smtpConnectResult.hidden = !text;
        };
        smtpConnectBtn.addEventListener('click', function () {
            smtpConnectBtn.disabled = true;
            smtpConnectBtn.textContent = i18n.checking_smtp_connection;
            setSmtpBadge('checking', i18n.smtp_checking);
            showReason('');
            var fd = new FormData();
            fd.append('action', 'isxf_test_smtp_connection');
            fd.append('nonce', isxf_admin_env.smtp_connection_nonce);
            fetch(isxf_admin_env.ajax_url, { method: 'POST', body: fd })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var message = data && data.data && data.data.message ? data.data.message : i18n.conn_error;
                    if (data && data.success) {
                        setSmtpBadge('ok', i18n.smtp_connected);
                    } else {
                        setSmtpBadge('error', i18n.smtp_failed);
                        showReason(message);
                    }
                })
                .catch(function () {
                    setSmtpBadge('error', i18n.smtp_failed);
                    showReason(i18n.conn_error);
                })
                .finally(function () {
                    smtpConnectBtn.disabled = false;
                    smtpConnectBtn.textContent = smtpConnectLabel;
                });
        });
    }

    // Changing anything the connection depends on makes the old result stale.
    document.querySelectorAll('.isxf-settings [name="isxf_smtp_enable"], .isxf-settings [name="isxf_smtp_host"], .isxf-settings [name="isxf_smtp_port"], .isxf-settings [name="isxf_smtp_user"], .isxf-settings [name="isxf_smtp_pass"], #isxf_auth_method').forEach(function (field) {
        field.addEventListener('change', function () {
            setSmtpBadge('idle', isxf_admin_env.i18n.smtp_not_checked);
            var reason = document.getElementById('isxf-test-smtp-connection-result');
            if (reason) reason.hidden = true;
        });
    });

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
                        testResult.style.background = '#e6f7ec';
                        testResult.style.border = '1px solid #16a34a';
                        testResult.style.color = '#16a34a';
                        testResult.textContent = '✅ ' + data.data.message;
                    } else {
                        testResult.style.background = '#ffe8e8';
                        testResult.style.border = '1px solid #db0000';
                        testResult.style.color = '#db0000';
                        testResult.textContent = '❌ ' + data.data.message;
                    }
                })
                .catch(function () {
                    testResult.style.display = 'block';
                    testResult.style.background = '#ffe8e8';
                    testResult.style.border = '1px solid #db0000';
                    testResult.style.color = '#db0000';
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
                    toolsResult.style.background = ok ? '#e6f7ec' : '#ffe8e8';
                    toolsResult.style.border = ok ? '1px solid #16a34a' : '1px solid #db0000';
                    toolsResult.style.color = ok ? '#16a34a' : '#db0000';
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
