document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle (mobile)
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle  = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });
        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Image upload zone – select files; they are submitted with the form on Update
    const uploadZone = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('imageFiles');
    const previewList = document.getElementById('imagePreviewList');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover'].forEach(evt => {
            uploadZone.addEventListener(evt, e => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            uploadZone.addEventListener(evt, e => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });
        });

        uploadZone.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            if (!dt || !dt.files.length) return;
            // Merge with any already selected files
            const dataTransfer = new DataTransfer();
            Array.from(fileInput.files || []).forEach(f => dataTransfer.items.add(f));
            Array.from(dt.files).forEach(f => {
                if (f.type.startsWith('image/')) dataTransfer.items.add(f);
            });
            fileInput.files = dataTransfer.files;
            renderPreviews(fileInput.files);
        });

        fileInput.addEventListener('change', () => renderPreviews(fileInput.files));
    }

    function renderPreviews(files) {
        if (!previewList) return;
        previewList.innerHTML = '';
        Array.from(files || []).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.innerHTML = `<img src="${e.target.result}" alt="preview">`;
                previewList.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
});
