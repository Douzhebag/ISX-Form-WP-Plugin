/**
 * InsightX Form — Admin Scripts
 * Settings page (captcha toggle, SMTP test) + Form builder (sortable, field state)
 */
(function () {
    'use strict';

    // === Settings Page: Captcha service toggle ===
    var captchaSelect = document.getElementById('captcha_select');
    if (captchaSelect) {
        captchaSelect.onchange = function () {
            document.getElementById('settings_google').style.display = (this.value === 'google') ? 'block' : 'none';
            document.getElementById('settings_cloudflare').style.display = (this.value === 'cloudflare') ? 'block' : 'none';
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
            testBtn.textContent = '⏳ กำลังส่ง...';
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
                    testResult.innerHTML = '❌ เกิดข้อผิดพลาดในการเชื่อมต่อ';
                })
                .finally(function () {
                    testBtn.disabled = false;
                    testBtn.textContent = '📨 ส่งอีเมลทดสอบ';
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
                    optionsInput.prop('disabled', false).prop('placeholder', 'เช่น แดง,เขียว,น้ำเงิน').css('opacity', '1');
                } else {
                    optionsInput.prop('disabled', true).prop('placeholder', 'ไม่ใช้ตัวเลือก').val('').css('opacity', '0.3');
                }

                if (['text', 'textarea', 'email', 'tel', 'number', 'date', 'check_in', 'check_out'].indexOf(type) !== -1) {
                    placeholderInput.prop('disabled', false).prop('placeholder', 'พิมพ์ข้อความตัวอย่าง').css('opacity', '1');
                } else {
                    placeholderInput.prop('disabled', true).prop('placeholder', 'ไม่ใช้ placeholder').val('').css('opacity', '0.3');
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
                    '<td class="drag-handle" title="คลิกค้างเพื่อเลื่อนสลับตำแหน่ง">☰</td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][label]" required style="width:100%;"></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][name]" style="width:100%;"></td>' +
                    '<td><select name="isxf_fields[' + r + '][type]" class="field-type-select" style="width:100%">' +
                    '<option value="text">Text (ข้อความสั้น)</option>' +
                    '<option value="textarea">Textarea (ข้อความยาว)</option>' +
                    '<option value="email">Email</option>' +
                    '<option value="tel">Telephone</option>' +
                    '<option value="number">Number (ตัวเลข)</option>' +
                    '<option value="date">Date (ปฏิทินทั่วไป)</option>' +
                    '<option value="check_in">📅 Check-in Date (วันเช็คอิน)</option>' +
                    '<option value="check_out">📅 Check-out Date (วันเช็คเอาท์)</option>' +
                    '<option value="select">Select (Dropdown)</option>' +
                    '<option value="radio">Radio (เลือกได้ 1 ข้อ)</option>' +
                    '<option value="checkbox">Checkbox (เลือกได้หลายข้อ)</option>' +
                    '<option value="heading">Heading (หัวข้อฟอร์ม)</option>' +
                    '</select></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][placeholder]" class="field-placeholder-input" placeholder="พิมพ์ข้อความตัวอย่าง" style="width:100%;"></td>' +
                    '<td><input type="text" name="isxf_fields[' + r + '][options]" class="field-options-input" placeholder="ไม่ใช้ตัวเลือก" disabled style="width:100%; opacity:0.3;"></td>' +
                    '<td><select name="isxf_fields[' + r + '][width]" style="width:100%"><option value="100">100%</option><option value="50">50%</option></select></td>' +
                    '<td><select name="isxf_fields[' + r + '][required]" class="field-required-select"><option value="yes">Yes</option><option value="no">No</option></select></td>' +
                    '<td style="text-align: center;"><button type="button" class="button remove-row" style="color:red;" title="ลบฟิลด์นี้">❌</button></td>' +
                    '</tr>';
                fieldList.append(tr);
                updateFieldState(fieldList.find('tr:last .field-type-select'));
                r++;
            });

            // Email type toggle
            var emailSelect = document.getElementById('isxf-email-type-select');
            var customPanel = document.getElementById('isxf-custom-email-panel');
            if (emailSelect && customPanel) {
                emailSelect.addEventListener('change', function () {
                    customPanel.classList.toggle('active', this.value === 'custom');
                });
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
