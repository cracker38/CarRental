document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile menu toggle
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open);
    });
  }

  // 2. Make tables scroll sideways on small screens
  document.querySelectorAll('main table').forEach(t => {
    const wrap = document.createElement('div');
    wrap.className = 'table-wrap';
    t.parentNode.insertBefore(wrap, t);
    wrap.appendChild(t);
  });

  // 3. Turn status words into coloured badges
  const statuses = ['pending','confirmed','cancelled','rejected','completed','no_show',
                    'active','inactive','blacklisted'];
  document.querySelectorAll('main td').forEach(td => {
    const txt = td.textContent.trim();
    if (statuses.includes(txt) && td.children.length <= 1) {
      td.innerHTML = '<span class="badge badge-' + txt + '">' + txt.replace('_', ' ') + '</span>';
    }
  });

  // 4. Style key links as buttons
  document.querySelectorAll('main a').forEach(a => {
    const t = a.textContent.trim();
    if (t.startsWith('+') || t === 'Make a reservation' || t === 'Edit details') {
      a.classList.add('btn');
    }
    if (t === 'Cancel') a.classList.add('btn', 'btn-secondary');
  });

  // 5. Fade out success messages after 6 seconds
  document.querySelectorAll('p[style*="color:green"]').forEach(p => {
    setTimeout(() => { p.classList.add('fade-out'); setTimeout(() => p.remove(), 700); }, 6000);
  });

  // 6. Prevent double-submit (skip if the confirm() popup was cancelled)
  document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', (ev) => {
      if (ev.defaultPrevented) return;
      setTimeout(() => {
        f.querySelectorAll('button[type="submit"], button:not([type])').forEach(b => b.disabled = true);
      }, 0);
    });
  });

  // 7. Date helpers: block past pickup, return follows pickup
  const pickup = document.querySelector('input[name="pickup"]');
  const ret = document.querySelector('input[name="return"]');
  if (pickup && ret) {
    const pad = n => String(n).padStart(2, '0');
    const d = new Date();
    const nowStr = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    pickup.min = nowStr;
    ret.min = pickup.value || nowStr;
    pickup.addEventListener('change', () => {
      ret.min = pickup.value || nowStr;
      if (ret.value && ret.value <= pickup.value) ret.value = '';
    });
  }
});
