document.addEventListener('DOMContentLoaded', function () {

    // Toast: white card, coloured icon, title + message, countdown bar.
    // Pauses while hovered/focused; errors stay a little longer.
    const TOAST_ICONS = {
        success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v6m0 4h.01" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>'
    };

    function showToast(message, type = 'success') {
        let container = document.querySelector('.isxf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'isxf-toast-container';
            document.body.appendChild(container);
        }
        const kind = type === 'success' ? 'success' : 'error';
        const i18n = (window.isxf_env && isxf_env.i18n) || {};
        const duration = kind === 'success' ? 4500 : 6500;

        const toast = document.createElement('div');
        toast.className = 'isxf-toast ' + kind;
        // Errors interrupt (alert); successes are announced politely.
        toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        toast.style.setProperty('--isxf-toast-duration', duration + 'ms');

        const icon = document.createElement('span');
        icon.className = 'isxf-toast-icon';
        icon.innerHTML = TOAST_ICONS[kind]; // static markup above, no user data

        const body = document.createElement('div');
        body.className = 'isxf-toast-body';
        const title = document.createElement('strong');
        title.className = 'isxf-toast-title';
        title.textContent = kind === 'success' ? (i18n.toast_success_title || '') : (i18n.toast_error_title || '');
        const text = document.createElement('span');
        text.className = 'isxf-toast-text';
        text.textContent = message;
        if (title.textContent) body.appendChild(title);
        body.appendChild(text);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'isxf-toast-close';
        close.setAttribute('aria-label', i18n.toast_close || 'Close');
        close.textContent = '×';

        const bar = document.createElement('span');
        bar.className = 'isxf-toast-bar';
        bar.setAttribute('aria-hidden', 'true');

        toast.append(icon, body, close, bar);
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));

        let timer = null;
        let remaining = duration;
        let startedAt = 0;
        const dismiss = () => {
            clearTimeout(timer);
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 350);
        };
        const start = () => { startedAt = Date.now(); timer = setTimeout(dismiss, remaining); toast.classList.remove('paused'); };
        const pause = () => { clearTimeout(timer); remaining -= Date.now() - startedAt; toast.classList.add('paused'); };
        close.addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', start);
        start();
    }

    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const forms = document.querySelectorAll('.isxf-form-container');

    forms.forEach(container => {
        const form = container.querySelector('.advanced-contact-form');
        const submitBtn = container.querySelector('.isxf-submit-btn');
        const submitHint = container.querySelector('.isxf-submit-hint');

        if (!form) return;

        // --- Inline field errors (Phase 2.6) -------------------------------

        function getFieldErrorEl(wrapper) {
            return wrapper.querySelector('.isxf-field-error');
        }

        function setFieldError(wrapper, message) {
            wrapper.classList.add('isxf-field-has-error');
            wrapper.querySelectorAll('input, select, textarea').forEach(field => {
                field.setAttribute('aria-invalid', 'true');
            });
            const errorEl = getFieldErrorEl(wrapper);
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.hidden = false;
            }
        }

        function clearFieldError(wrapper) {
            wrapper.classList.remove('isxf-field-has-error');
            wrapper.querySelectorAll('input, select, textarea').forEach(field => {
                field.removeAttribute('aria-invalid');
            });
            const errorEl = getFieldErrorEl(wrapper);
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.hidden = true;
            }
        }

        function clearAllFieldErrors() {
            form.querySelectorAll('.isxf-field-wrapper.isxf-field-has-error').forEach(clearFieldError);
        }

        // Clear a field's error as soon as the user edits it.
        form.addEventListener('input', function (e) {
            const wrapper = e.target.closest('.isxf-field-wrapper');
            if (wrapper && wrapper.classList.contains('isxf-field-has-error')) {
                clearFieldError(wrapper);
            }
        });
        form.addEventListener('change', function (e) {
            const wrapper = e.target.closest('.isxf-field-wrapper');
            if (wrapper && wrapper.classList.contains('isxf-field-has-error')) {
                clearFieldError(wrapper);
            }
        });

        // --- Email format validation (client-side convenience; the server ---
        // --- remains the source of truth) -----------------------------------

        function validateEmailField(input) {
            const wrapper = input.closest('.isxf-field-wrapper');
            if (!wrapper) return true;
            const value = input.value.trim();
            if (value && !EMAIL_RE.test(value)) {
                setFieldError(wrapper, isxf_env.i18n.email_invalid);
                return false;
            }
            return true;
        }

        form.querySelectorAll('input[type="email"]').forEach(input => {
            input.addEventListener('blur', function () {
                validateEmailField(this);
            });
        });

        // Validate required fields + email format; marks inline errors and
        // returns the first invalid wrapper (or null when everything is OK).
        function validateFieldsOnSubmit() {
            let firstInvalid = null;

            form.querySelectorAll('.isxf-field-wrapper[data-required="yes"]').forEach(wrapper => {
                const type = wrapper.dataset.type;
                let isEmpty = true;

                if (type === 'radio' || type === 'checkbox') {
                    isEmpty = wrapper.querySelectorAll('input:checked').length === 0;
                } else {
                    const field = wrapper.querySelector('input, select, textarea');
                    isEmpty = !field || !field.value.trim();
                }

                if (isEmpty) {
                    setFieldError(wrapper, isxf_env.i18n.field_required);
                    if (!firstInvalid) firstInvalid = wrapper;
                }
            });

            form.querySelectorAll('input[type="email"]').forEach(input => {
                const wrapper = input.closest('.isxf-field-wrapper');
                if (!validateEmailField(input) && !firstInvalid) {
                    firstInvalid = wrapper;
                }
            });

            return firstInvalid;
        }

        // Map a server-side error message back to a field when the message
        // mentions the field label (e.g. "Please fill in: <label>").
        function markFieldFromServerMessage(message) {
            if (!message) return;
            const wrappers = form.querySelectorAll('.isxf-field-wrapper');
            for (const wrapper of wrappers) {
                const labelEl = wrapper.querySelector('.isxf-field-label');
                if (!labelEl) continue;
                const labelText = labelEl.textContent.replace(/\*/g, '').trim();
                if (labelText && message.indexOf(labelText) !== -1) {
                    setFieldError(wrapper, message);
                    return;
                }
            }
        }

        if (typeof flatpickr !== 'undefined') {
            const normalDates = container.querySelectorAll(".isxf-modern-date");
            normalDates.forEach(el => {
                flatpickr(el, { locale: "th", dateFormat: "d/m/Y", minDate: "today" });
            });

            const checkInInput = container.querySelector(".isxf-date-check-in");
            const checkOutInput = container.querySelector(".isxf-date-check-out");

            if (checkInInput && checkOutInput) {
                const fpCheckIn = flatpickr(checkInInput, {
                    locale: "th",
                    dateFormat: "d/m/Y",
                    minDate: "today",
                    onChange: function (selectedDates, dateStr, instance) {
                        if (selectedDates[0]) {
                            const minOutDate = new Date(selectedDates[0]);
                            minOutDate.setDate(minOutDate.getDate() + 1);
                            fpCheckOut.set('minDate', minOutDate);
                            if (fpCheckOut.selectedDates[0] && fpCheckOut.selectedDates[0] < minOutDate) {
                                fpCheckOut.clear();
                            }
                            setTimeout(() => fpCheckOut.open(), 100);
                        }
                    }
                });

                const fpCheckOut = flatpickr(checkOutInput, {
                    locale: "th",
                    dateFormat: "d/m/Y",
                    minDate: new Date().fp_incr(1)
                });
            }
        }

        const telInputs = form.querySelectorAll('input[name*="tel"], input[type="tel"]');
        telInputs.forEach(input => {
            input.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        if (submitBtn && !submitBtn.querySelector('.isxf-spinner')) {
            const btnLabel = submitBtn.innerText;
            const spinner = document.createElement('span');
            spinner.className = 'isxf-spinner';
            const btnText = document.createElement('span');
            btnText.className = 'btn-text';
            btnText.textContent = btnLabel;
            submitBtn.textContent = '';
            submitBtn.appendChild(spinner);
            submitBtn.appendChild(btnText);
            submitBtn.setAttribute('aria-label', btnLabel);
            submitBtn.setAttribute('aria-busy', 'false');
        }

        const originalBtnText = submitBtn ? submitBtn.innerText : isxf_env.i18n.submit_form;
        let isSubmitting = false;

        function validateFormState() {
            if (isSubmitting) return;

            let isAllFilled = true;
            const requiredFields = form.querySelectorAll('.isxf-field-wrapper[data-required="yes"]');

            requiredFields.forEach(wrapper => {
                const type = wrapper.dataset.type;
                let isEmpty = true;

                if (type === 'radio' || type === 'checkbox') {
                    isEmpty = wrapper.querySelectorAll('input:checked').length === 0;
                } else {
                    const field = wrapper.querySelector('input, select, textarea');
                    isEmpty = !field || !field.value.trim();
                }

                if (isEmpty) isAllFilled = false;
            });

            submitBtn.disabled = !isAllFilled;
            if (submitHint) submitHint.hidden = isAllFilled;
        }

        form.addEventListener('input', validateFormState);
        form.addEventListener('change', validateFormState);
        setTimeout(validateFormState, 500);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;

            const firstInvalid = validateFieldsOnSubmit();
            if (firstInvalid) {
                showToast(isxf_env.i18n.form_has_errors, 'error');
                const focusTarget = firstInvalid.querySelector('input, select, textarea');
                if (focusTarget) focusTarget.focus();
                return;
            }

            if (isxf_env.service === 'google') {
                if (typeof grecaptcha !== 'undefined') {
                    isSubmitting = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.setAttribute('aria-busy', 'true');
                    submitBtn.disabled = true;
                    submitBtn.querySelector('.btn-text').innerText = isxf_env.i18n.checking_security;

                    grecaptcha.ready(function () {
                        grecaptcha.execute(isxf_env.site_key, { action: 'submit' }).then(function (token) {
                            let hiddenInput = form.querySelector('input[name="g-recaptcha-response"]');
                            if (!hiddenInput) {
                                hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'g-recaptcha-response';
                                form.appendChild(hiddenInput);
                            }
                            hiddenInput.value = token;
                            processFormSubmission();
                        }).catch(function (err) {
                            console.error('reCAPTCHA Error:', err);
                            showToast(isxf_env.i18n.recaptcha_error, 'error');
                            isSubmitting = false;
                            resetSubmitButton();
                        });
                    });
                } else {
                    isSubmitting = true;
                    processFormSubmission();
                }
            } else if (isxf_env.service === 'cloudflare') {
                const turnstileInput = form.querySelector('[name="cf-turnstile-response"]');
                if (turnstileInput && !turnstileInput.value) {
                    showToast(isxf_env.i18n.please_wait, 'error');
                    return;
                }
                isSubmitting = true;
                processFormSubmission();
            } else {
                isSubmitting = true;
                processFormSubmission();
            }
        });

        function resetSubmitButton() {
            submitBtn.classList.remove('is-loading');
            submitBtn.setAttribute('aria-busy', 'false');
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-text').innerText = originalBtnText;
            validateFormState();
        }

        function processFormSubmission() {
            submitBtn.classList.add('is-loading');
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').innerText = isxf_env.i18n.processing;

            fetch(isxf_env.ajax_url, { method: 'POST', body: new FormData(form) })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.data.message, 'success');
                        clearAllFieldErrors();
                        form.reset();
                        container.querySelectorAll('input[class*="isxf-date"], input[class*="isxf-modern"]').forEach(el => {
                            if (el._flatpickr) el._flatpickr.clear();
                        });
                    } else {
                        showToast(data.data.message, 'error');
                        markFieldFromServerMessage(data.data.message);
                    }
                })
                .catch(() => {
                    showToast(isxf_env.i18n.conn_error, 'error');
                })
                .finally(() => {
                    isSubmitting = false;
                    // A Turnstile token is single-use: whatever the outcome,
                    // fetch a new one so the next submit is not rejected as
                    // a duplicate.
                    if (isxf_env.service === 'cloudflare' && window.turnstile) {
                        const widget = form.querySelector('.cf-turnstile');
                        if (widget) window.turnstile.reset(widget);
                    }
                    resetSubmitButton();
                });
        }
    });
});
