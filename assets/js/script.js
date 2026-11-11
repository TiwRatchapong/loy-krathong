(() => {
    const fireflies = document.getElementById('fireflies');
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');

    // Decorative fireflies are generated locally; no external library is required.
    if (fireflies && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        for (let i = 0; i < 24; i++) {
            const dot = document.createElement('span');
            dot.className = 'firefly';
            dot.style.left = `${Math.random() * 100}%`;
            dot.style.top = `${18 + Math.random() * 75}%`;
            dot.style.setProperty('--duration', `${4 + Math.random() * 7}s`);
            dot.style.animationDelay = `${Math.random() * -8}s`;
            fireflies.appendChild(dot);
        }
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
        window.matchMedia('(min-width: 761px)').addEventListener('change', () => setOpen(false));
    }
})();
