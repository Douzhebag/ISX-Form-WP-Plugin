/**
 * InsightX Entries — JavaScript
 * Handles status updates, notes, and bulk actions via AJAX.
 */
(function(){
    var ajaxUrl = acf_entries_env.ajax_url;
    var nonce = acf_entries_env.nonce;

    function showToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'ix-toast ' + type;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function() { t.style.opacity = '0'; t.style.transition = 'opacity 0.3s'; }, 2500);
        setTimeout(function() { t.remove(); }, 3000);
    }

    var cbAll = document.getElementById('cb-select-all');
    if (cbAll) {
        cbAll.addEventListener('change', function(e) {
            var cbs = document.querySelectorAll('input[name="entry_ids[]"]');
            for (var i = 0; i < cbs.length; i++) { cbs[i].checked = e.target.checked; }
        });
    }

    document.querySelectorAll('.ix-status-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var entryId = this.dataset.entryId;
            var status = this.value;
            var selectEl = this;
            selectEl.disabled = true;

            var fd = new FormData();
            fd.append('action', 'acf_update_entry_status');
            fd.append('nonce', nonce);
            fd.append('entry_id', entryId);
            fd.append('status', status);

            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast(data.data.message, 'success');
                        selectEl.dataset.original = status;
                    } else {
                        showToast(data.data.message, 'error');
                        selectEl.value = selectEl.dataset.original;
                    }
                })
                .catch(function() {
                    showToast('เกิดข้อผิดพลาด', 'error');
                    selectEl.value = selectEl.dataset.original;
                })
                .finally(function() { selectEl.disabled = false; });
        });
    });

    document.querySelectorAll('.ix-note-display').forEach(function(disp) {
        disp.addEventListener('click', function() {
            var id = this.dataset.entryId;
            this.style.display = 'none';
            var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
            editor.classList.add('active');
            editor.querySelector('textarea').focus();
        });
    });

    document.querySelectorAll('.ix-note-cancel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.entryId;
            var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
            editor.classList.remove('active');
            document.querySelector('.ix-note-display[data-entry-id="' + id + '"]').style.display = 'flex';
        });
    });

    document.querySelectorAll('.ix-note-save').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.entryId;
            var editor = document.querySelector('.ix-note-editor[data-entry-id="' + id + '"]');
            var textarea = editor.querySelector('textarea');
            var note = textarea.value;
            var saveBtn = this;
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ กำลังบันทึก...';

            var fd = new FormData();
            fd.append('action', 'acf_update_entry_note');
            fd.append('nonce', nonce);
            fd.append('entry_id', id);
            fd.append('note', note);

            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast(data.data.message, 'success');
                        editor.classList.remove('active');
                        var display = document.querySelector('.ix-note-display[data-entry-id="' + id + '"]');
                        display.style.display = 'flex';
                        if (note.trim()) {
                            display.innerHTML = '<span class="ix-note-text">' + note.replace(/</g,'&lt;') + '</span><span class="ix-note-edit-icon">✏️</span>';
                        } else {
                            display.innerHTML = '<span class="ix-note-placeholder">+ เพิ่มโน้ต</span><span class="ix-note-edit-icon">✏️</span>';
                        }
                    } else {
                        showToast(data.data.message, 'error');
                    }
                })
                .catch(function() { showToast('เกิดข้อผิดพลาด', 'error'); })
                .finally(function() { saveBtn.disabled = false; saveBtn.textContent = '💾 บันทึก'; });
        });
    });
})();
