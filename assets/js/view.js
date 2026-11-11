(() => {
    const panel = document.querySelector('[data-krathong-view]');
    const stage = document.getElementById('releaseStage');
    const splash = document.getElementById('relSplash');
    const sky = document.getElementById('relSky');
    const replay = document.getElementById('replayRelease');
    const copy = document.getElementById('copyLink');
    const note = document.getElementById('copiedNote');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timers = [];

    function later(fn, ms) { const t = setTimeout(fn, ms); timers.push(t); return t; }
    function rand(a, b) { return a + Math.random() * (b - a); }

    function spawnSplash() {
        splash.replaceChildren();
        for (let i = 0; i < 14; i++) {
            const d = document.createElement('b');
            const dir = i % 2 ? 1 : -1;
            d.style.setProperty('--dx', `${dir * rand(20, 120)}px`);
            d.style.setProperty('--dy', `${-rand(60, 150)}px`);
            d.style.animationDelay = `${1.12 + rand(0, .12)}s`;
            splash.appendChild(d);
        }
    }

    function spawnSparkle() {
        const s = document.createElement('i');
        s.className = 'rel-sparkle';
        s.style.left = `${rand(42, 58)}%`;
        s.style.bottom = `${rand(42, 56)}%`;
        s.style.setProperty('--dx', `${rand(-40, 40)}px`);
        s.style.setProperty('--t', `${rand(2.6, 4.4)}s`);
        sky.appendChild(s);
        later(() => s.remove(), 4600);
    }

    function spawnLantern() {
        if (sky.querySelectorAll('.rel-lantern').length >= 4) return;
        const l = document.createElement('i');
        l.className = 'rel-lantern';
        l.style.left = `${rand(6, 90)}%`;
        l.style.setProperty('--dx', `${rand(-30, 40)}px`);
        l.style.setProperty('--t', `${rand(9, 14)}s`);
        sky.appendChild(l);
        later(() => l.remove(), 14500);
    }

    function run() {
        timers.forEach(clearTimeout); timers = [];
        sky.replaceChildren();
        if (reduced) return;
        panel.classList.remove('go', 'animating');
        stage.classList.remove('release-go');
        void stage.offsetWidth;                 // รีสตาร์ทอนิเมชัน
        panel.classList.add('animating');
        stage.classList.add('release-go');
        spawnSplash();
        later(() => panel.classList.add('go'), 30);
        // ประกายไฟจากเทียน + โคมลอยบนท้องฟ้า หลังกระทงลงน้ำ
        later(() => {
            const loop = () => { spawnSparkle(); timers.push(setTimeout(loop, rand(350, 800))); };
            loop();
            const lanterns = () => { spawnLantern(); timers.push(setTimeout(lanterns, rand(2500, 4500))); };
            lanterns();
        }, 1500);
    }

    if (panel && stage) {
        if (panel.dataset.animate === '1' || !reduced) run();
        replay?.addEventListener('click', run);
    }

    copy?.addEventListener('click', async () => {
        const url = new URL(copy.dataset.url, window.location.href).href;
        let ok = false;
        try { await navigator.clipboard.writeText(url); ok = true; }
        catch (e) {
            const input = document.createElement('input');
            input.value = url; document.body.appendChild(input); input.select();
            try { ok = document.execCommand('copy'); } catch (e2) { ok = false; }
            input.remove();
        }
        if (note) { note.textContent = ok ? 'คัดลอกลิงก์แล้ว' : url; note.hidden = false; }
    });
})();
