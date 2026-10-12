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

    const uploadZone = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('imageFiles');
    const previewList = document.getElementById('imagePreviewList');
    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());
        ['dragenter','dragover'].forEach(evt => uploadZone.addEventListener(evt, e => { e.preventDefault(); uploadZone.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(evt => uploadZone.addEventListener(evt, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); }));
        uploadZone.addEventListener('drop', e => { fileInput.files = e.dataTransfer.files; renderPreviews(fileInput.files); });
        fileInput.addEventListener('change', () => renderPreviews(fileInput.files));
    }
    function renderPreviews(files) {
        if (!previewList) return;
        previewList.innerHTML = '';
        Array.from(files).forEach(file => {
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

// Set primary image (AJAX)
document.querySelectorAll('.btn-set-primary').forEach(btn => {
    btn.addEventListener('click', async () => {
        const imageId = btn.dataset.imageId;
        const vehicleId = btn.dataset.vehicleId;
        if (!confirm('Set this as the primary image?')) return;
        try {
            const res = await fetch((window.BASE_URL || '/CarRental') + '/api/set_primary_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_id: imageId, vehicle_id: vehicleId })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.message || 'Failed');
        } catch (e) {
            alert('Network error');
        }
    });
});

// Delete image (AJAX)
document.querySelectorAll('.btn-delete-image').forEach(btn => {
    btn.addEventListener('click', async () => {
        const imageId = btn.dataset.imageId;
        const vehicleId = btn.dataset.vehicleId;
        if (!confirm('Delete this image permanently?')) return;
        try {
            const res = await fetch((window.BASE_URL || '/CarRental') + '/api/delete_image.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_id: imageId, vehicle_id: vehicleId })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.message || 'Failed');
        } catch (e) {
            alert('Network error');
        }
    });
});
