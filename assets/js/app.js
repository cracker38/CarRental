document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
    };
    const openSidebar = () => {
        sidebar?.classList.add('open');
        backdrop?.classList.add('show');
    };

    toggle?.addEventListener('click', () => {
        if (sidebar?.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
    backdrop?.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', (event) => {
            if (!window.confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-auto-submit]').forEach((el) => {
        el.addEventListener('change', () => el.closest('form')?.submit());
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('data-password-toggle'));
            if (!input) {
                return;
            }
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            button.innerHTML = hidden ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    });

    const email = document.getElementById('email');
    if (email && email.form?.dataset.rememberEmail === 'true') {
        const saved = window.localStorage.getItem('crms_email');
        if (saved && !email.value) {
            email.value = saved;
        }
        email.form.addEventListener('submit', () => {
            if (email.value) {
                window.localStorage.setItem('crms_email', email.value);
            }
        });
    }

    const permSearch = document.getElementById('permissionSearch');
    if (permSearch) {
        permSearch.addEventListener('input', () => {
            const q = permSearch.value.toLowerCase();
            document.querySelectorAll('[data-perm-item]').forEach((item) => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('[data-check-group]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.getAttribute('data-check-group');
            const boxes = document.querySelectorAll(`input[data-group="${group}"]`);
            const allOn = Array.from(boxes).every((box) => box.checked);
            boxes.forEach((box) => { box.checked = !allOn; });
        });
    });
});
