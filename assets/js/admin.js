(() => {
    // แสดง/ซ่อนรหัสผ่าน
    document.querySelectorAll('[data-toggle-pass]').forEach(btn => btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePass);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'ซ่อน' : 'แสดง';
    }));

    // เลือกหลายรายการ
    const all = document.getElementById('checkAll');
    const boxes = [...document.querySelectorAll('.row-check')];
    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    const sync = () => {
        const n = boxes.filter(b => b.checked).length;
        if (bar) { bar.classList.toggle('on', n > 0); count.textContent = n; }
        if (all) all.checked = n > 0 && n === boxes.length;
    };
    if (all) all.addEventListener('change', () => { boxes.forEach(b => { b.checked = all.checked; }); sync(); });
    boxes.forEach(b => b.addEventListener('change', sync));

    // ยืนยันก่อนทำรายการที่ย้อนกลับไม่ได้
    document.querySelectorAll('[data-confirm]').forEach(btn => btn.addEventListener('click', e => {
        if (!confirm(btn.dataset.confirm)) e.preventDefault();
    }));

    // หน้าต่างรายละเอียดกระทง (ใช้ textContent เพื่อไม่ตีความ HTML)
    const dialog = document.getElementById('detailDialog');
    if (dialog && typeof dialog.showModal === 'function') {
        document.querySelectorAll('[data-detail]').forEach(btn => btn.addEventListener('click', () => {
            let data = {};
            try { data = JSON.parse(btn.dataset.detail); } catch (err) { return; }
            Object.keys(data).forEach(key => {
                const el = dialog.querySelector('[data-f="' + key + '"]');
                if (el) el.textContent = data[key] || '—';
            });
            dialog.showModal();
        }));
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    }

    // เมนูข้างแบบ drawer บนมือถือ/แท็บเล็ต (จอกว้างกว่า 900px เมนูแสดงค้างไว้ตามปกติ)
    const toggle = document.getElementById('navToggle');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('navScrim');
    if (toggle && sidebar) {
        const wide = window.matchMedia('(min-width: 901px)');
        const setOpen = (open, restoreFocus = true) => {
            document.body.classList.toggle('nav-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'ปิดเมนู' : 'เปิดเมนู');
            if (open) { const first = sidebar.querySelector('a'); if (first) first.focus({ preventScroll: true }); }
            else if (restoreFocus) toggle.focus({ preventScroll: true });
        };
        toggle.addEventListener('click', () => setOpen(!document.body.classList.contains('nav-open')));
        if (scrim) scrim.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && document.body.classList.contains('nav-open')) setOpen(false);
        });
        // ขยายหน้าต่างจนเมนูแสดงค้างแล้ว ให้ปิดสถานะ drawer ทิ้ง
        const onWide = () => { if (wide.matches) setOpen(false, false); };
        if (wide.addEventListener) wide.addEventListener('change', onWide); else wide.addListener(onWide);
    }

    // ทดสอบตัวกรองคำหยาบ: ส่งแบบ fetch แล้วอัปเดตผลในที่เดิม ไม่โหลดหน้าใหม่ (ไม่มี JS ก็ยังใช้ได้ตามปกติ)
    const testForm = document.querySelector('[data-inline-test]');
    const testResult = document.getElementById('profanityResult');
    if (testForm && testResult && window.fetch) {
        testForm.addEventListener('submit', async e => {
            e.preventDefault();
            const url = new URL(testForm.action, location.href);
            url.hash = '';
            new URLSearchParams(new FormData(testForm)).forEach((v, k) => url.searchParams.set(k, v));
            const btn = testForm.querySelector('button[type=submit]');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error(res.status);
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('profanityResult');
                if (!fresh) throw new Error('no result');
                testResult.innerHTML = fresh.innerHTML;
                history.replaceState(null, '', url.pathname + url.search + '#profanityTest');
            } catch (err) {
                testForm.submit();   // สำรอง: โหลดหน้าตามปกติ
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    // ข้อความแจ้งผลจางหายเอง
    const flash = document.querySelector('.flash');
    if (flash) setTimeout(() => flash.classList.add('gone'), 5000);
})();
