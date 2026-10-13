
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  const menuBtn = document.getElementById('menuBtn');
  const setMenu = open => {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('show', open);
  };
  if (menuBtn) menuBtn.addEventListener('click', () => setMenu(!sidebar.classList.contains('open')));
  if (backdrop) backdrop.addEventListener('click', () => setMenu(false));

  document.querySelectorAll('main table').forEach(t => {
    const wrap = document.createElement('div');
    wrap.className = 'table-wrap';
    t.parentNode.insertBefore(wrap, t);
    wrap.appendChild(t);
  });

  const statuses = ['pending','confirmed','cancelled','rejected','completed','no_show',
                    'active','inactive','blacklisted'];
  document.querySelectorAll('main td').forEach(td => {
    const txt = td.textContent.trim();
    if (statuses.includes(txt) && td.children.length <= 1) {
      td.innerHTML = '<span class="badge badge-' + txt + '">' + txt.replace('_', ' ') + '</span>';
    }
  });

  document.querySelectorAll('main a').forEach(a => {
    const t = a.textContent.trim();
    if (t.startsWith('+') || t === 'Make a reservation' || t === 'Edit details') a.classList.add('btn');
    if (t === 'Cancel') a.classList.add('btn', 'btn-secondary');
  });

  document.querySelectorAll('p[style*="color:green"]').forEach(p => {
    setTimeout(() => { p.classList.add('fade-out'); setTimeout(() => p.remove(), 700); }, 6000);
  });

  document.querySelectorAll('main form').forEach(f => {
    f.addEventListener('submit', ev => {
      if (ev.defaultPrevented) return;
      setTimeout(() => {
        f.querySelectorAll('button[type="submit"], button:not([type])').forEach(b => b.disabled = true);
      }, 0);
    });
  });

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
