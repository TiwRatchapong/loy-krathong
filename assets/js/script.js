(() => {
    const fireflies = document.getElementById('fireflies');
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');

    // Decorative fireflies are generated locally; no external library is required.
    if (fireflies && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // เครื่องช้า/มือถือใช้จำนวนน้อยลง (แต่ละดวงเป็นเลเยอร์ที่มีเงาเรืองแสง)
        const lowPower = window.matchMedia('(pointer: coarse)').matches || (navigator.hardwareConcurrency || 8) <= 4;
        const count = lowPower ? 8 : 18;
        const frag = document.createDocumentFragment();
        for (let i = 0; i < count; i++) {
            const dot = document.createElement('span');
            dot.className = 'firefly';
            dot.style.left = `${Math.random() * 100}%`;
            dot.style.top = `${18 + Math.random() * 75}%`;
            dot.style.setProperty('--duration', `${4 + Math.random() * 7}s`);
            dot.style.animationDelay = `${Math.random() * -8}s`;
            frag.appendChild(dot);
        }
        fireflies.appendChild(frag);
    }

    if (menuToggle && mainNav) {
        const setOpen = (isOpen) => {
            mainNav.classList.toggle('open', isOpen);
            menuToggle.classList.toggle('is-open', isOpen);
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'ปิดเมนู' : 'เปิดเมนู');
        };

        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            setOpen(!mainNav.classList.contains('open'));
        });
        mainNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
        // แตะนอกเมนู / กด Esc / ขยายเป็นจอใหญ่ → ปิดเมนู
        document.addEventListener('click', (e) => {
            if (mainNav.classList.contains('open') && !mainNav.contains(e.target)) setOpen(false);
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
        window.matchMedia('(min-width: 901px)').addEventListener('change', () => setOpen(false));
    }

    // ---- ตัวเลขกระทง (ทั้งหมด/วันนี้) อัปเดตสด ----
    // หน้าแม่น้ำมีตัวดึงข้อมูลของตัวเอง (river.js) จึงเรียก window.ktUpdateStats ให้; หน้าอื่นดึงเองที่นี่
    const fmt = n => Number(n).toLocaleString('en-US');
    window.ktUpdateStats = data => {
        ['total', 'today'].forEach(key => {
            if (typeof data[key] !== 'number') return;
            document.querySelectorAll(`.kt-stats [data-stat="${key}"]`).forEach(el => {
                const next = fmt(data[key]);
                if (el.textContent === next) return;
                el.textContent = next;
                el.classList.remove('kt-bump'); void el.offsetWidth; el.classList.add('kt-bump');
            });
        });
    };
    const liveStats = document.querySelector('.kt-stats[data-live]');
    if (liveStats && !document.getElementById('riverWorld') && window.fetch) {
        const poll = async () => {
            try {
                const res = await fetch(liveStats.dataset.live, { cache: 'no-store' });
                if (res.ok) window.ktUpdateStats(await res.json());
            } catch (e) { /* ลองใหม่รอบหน้า */ }
        };
        const loop = () => setTimeout(async () => { if (!document.hidden) await poll(); loop(); }, 30000 + Math.random() * 10000);
        loop();
    }

})();
