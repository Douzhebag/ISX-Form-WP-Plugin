document.addEventListener('DOMContentLoaded', function () {

    function showToast(message, type = 'success') {
        let container = document.querySelector('.isxf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'isxf-toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `isxf-toast ${type}`;
        const icon = type === 'success' ? '✅ ' : '⚠️ ';
        toast.innerHTML = `<span>${icon} ${message}</span><span class="isxf-toast-close" style="cursor:pointer; margin-left:10px;">&times;</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        toast.querySelector('.isxf-toast-close').onclick = () => toast.remove();
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 4000);
    }

    const forms = document.querySelectorAll('.isxf-form-container');

    forms.forEach(container => {
        const form = container.querySelector('.advanced-contact-form');
        const submitBtn = container.querySelector('.isxf-submit-btn');

        if (!form) return;

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
            submitBtn.innerHTML = '<span class="isxf-spinner"></span><span class="btn-text">' + submitBtn.innerText + '</span>';
            submitBtn.setAttribute('aria-label', submitBtn.innerText);
            submitBtn.setAttribute('aria-busy', 'false');
        }

        const originalBtnText = submitBtn ? submitBtn.innerText : 'ยืนยันส่งแบบฟอร์ม';
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
        }

        form.addEventListener('input', validateFormState);
        form.addEventListener('change', validateFormState);
        setTimeout(validateFormState, 500);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;

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
                        form.reset();
                        container.querySelectorAll('input[class*="isxf-date"], input[class*="isxf-modern"]').forEach(el => {
                            if (el._flatpickr) el._flatpickr.clear();
                        });
                    } else {
                        showToast(data.data.message, 'error');
                    }
                })
                .catch(() => {
                    showToast(isxf_env.i18n.conn_error, 'error');
                })
                .finally(() => {
                    isSubmitting = false;
                    resetSubmitButton();
                });
        }
    });
});