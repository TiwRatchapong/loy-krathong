(() => {
    const view = document.getElementById('riverView');
    const world = document.getElementById('riverWorld');
    const sky = document.getElementById('riverSky');
    const toast = document.getElementById('riverToast');
    const dialog = document.getElementById('wishDialog');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rand = (a, b) => a + Math.random() * (b - a);
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

    // ---- พารามิเตอร์แม่น้ำ (ส่งมาจาก PHP: includes/functions.php ให้ตรงกันทั้งสองฝั่ง) ----
    const RATIO = parseFloat(world.dataset.ratio) || 0.5628;     // สูง/กว้างของภาพ
    const Y_FAR = parseFloat(world.dataset.yFar) || 55;
    const Y_NEAR = parseFloat(world.dataset.yNear) || 85;
    const S_FAR = parseFloat(world.dataset.sFar) || 0.3;
    const S_NEAR = parseFloat(world.dataset.sNear) || 1.25;
    let polyPct = [];
    try { polyPct = JSON.parse(world.dataset.poly || '[]'); } catch (e) { polyPct = []; }
    // คำนวณในหน่วย "% ของความกว้างภาพ" ทั้งแกน x และ y เพื่อให้ระยะทางเท่ากันทุกทิศ
    const poly = polyPct.map(([x, y]) => [x, y * RATIO]);

    function inside(u, v) {
        let c = false;
        for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
            const [xi, yi] = poly[i], [xj, yj] = poly[j];
            if ((yi > v) !== (yj > v) && u < (xj - xi) * (v - yi) / ((yj - yi) || 1e-9) + xi) c = !c;
        }
        return c;
    }
    // ยิ่งไกล (y น้อย) ยิ่งเล็ก ยิ่งใกล้ท่าน้ำยิ่งใหญ่
    function scaleAt(yPct) {
        const t = clamp((yPct - Y_FAR) / (Y_NEAR - Y_FAR), 0, 1);
        return S_FAR + (S_NEAR - S_FAR) * t;
    }

    // ---- มุมมอง: บนจอแคบภาพกว้างกว่าจอ เลื่อนไปที่กระทงของเรา (หรือกลางผืนน้ำ) ----
    const mine = world.querySelector('.rk-mine');
    function centerView() {
        const focus = mine ? parseFloat(mine.dataset.x) / 100 : 0.56;
        view.scrollLeft = focus * world.offsetWidth - view.clientWidth / 2;
    }
    centerView();
    window.addEventListener('resize', centerView);

    // ---- กดดูคำอธิษฐาน ----
    if (dialog && typeof dialog.showModal === 'function') {
        const name = document.getElementById('wishName');
        const text = document.getElementById('wishText');
        const link = document.getElementById('wishLink');
        world.querySelectorAll('.rk').forEach(item => {
            item.addEventListener('click', () => {
                name.textContent = item.dataset.name;
                text.textContent = item.dataset.prayer;   // textContent: ไม่ตีความ HTML
                link.href = `view.php?id=${encodeURIComponent(item.dataset.id)}`;
                dialog.showModal();
            });
        });
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    }

    // ---- กระทงลอยอยู่ในขอบเขตแม่น้ำ และเปลี่ยนขนาดตามความไกล-ใกล้ ----
    const boats = [...world.querySelectorAll('.rk')].map(el => {
        const x = parseFloat(el.dataset.x), y = parseFloat(el.dataset.y);
        return {
            el, isMine: el.classList.contains('rk-mine'),
            u: x, v: y * RATIO, home: [x, y * RATIO],
            heading: parseFloat(el.dataset.heading) || rand(0, 6.28),
            target: 0, speed: parseFloat(el.dataset.speed) || 0.5,
            wobble: rand(0, 6.28), wobbleRate: rand(0.15, 0.4),
            lastS: -1, startAt: 0,
        };
    });
    boats.forEach(b => { b.target = b.heading; });

    function angleDiff(a, b) { return Math.atan2(Math.sin(a - b), Math.cos(a - b)); }

    function render(b) {
        const yPct = b.v / RATIO;
        b.el.style.left = `${b.u.toFixed(3)}%`;
        b.el.style.top = `${yPct.toFixed(3)}%`;
        const s = scaleAt(yPct) * (b.isMine ? 1.2 : 1);
        if (Math.abs(s - b.lastS) > 0.003) { b.el.style.setProperty('--s', s.toFixed(3)); b.lastS = s; }
        if (!b.isMine) b.el.style.zIndex = String(Math.round(yPct * 4));
    }

    function step(b, dt) {
        const s = scaleAt(b.v / RATIO);
        const look = 1.2 + 1.8 * s;                       // ระยะเผื่อขอบ: กระทงใหญ่ (ใกล้) เผื่อมากกว่า
        const v = b.speed * (0.5 + 0.5 * s) * dt;         // อยู่ไกลก็ดูเคลื่อนช้าลง
        b.wobble += b.wobbleRate * dt;
        b.target += Math.sin(b.wobble) * 0.35 * dt;       // เลี้ยวเบา ๆ ให้ลอยเป็นทางโค้ง

        const probe = ang => inside(b.u + Math.cos(ang) * (v + look), b.v + Math.sin(ang) * (v + look));
        if (!probe(b.target)) {
            // ใกล้ขอบ: หาทิศที่ยังอยู่ในน้ำ (ลองเบี่ยงซ้าย-ขวาสลับกัน)
            let found = null;
            for (let k = 1; k <= 12 && found === null; k++) {
                for (const sign of [1, -1]) {
                    const a = b.target + sign * k * 0.35;
                    if (probe(a)) { found = a; break; }
                }
            }
            b.target = found !== null ? found : Math.atan2(b.home[1] - b.v, b.home[0] - b.u);
        }
        b.heading += angleDiff(b.target, b.heading) * Math.min(1, dt * 2.2);

        const nu = b.u + Math.cos(b.heading) * v, nv = b.v + Math.sin(b.heading) * v;
        if (inside(nu, nv)) { b.u = nu; b.v = nv; }
        else { b.target = b.heading + Math.PI * 0.5; }    // กันพลาด: ไม่ก้าวออกนอกขอบเขตเด็ดขาด
        if (!inside(b.u, b.v)) { b.u = b.home[0]; b.v = b.home[1]; }
    }

    boats.forEach(render);
    if (!reduced && poly.length > 2) {
        const t0 = performance.now();
        boats.forEach(b => { b.startAt = b.isMine ? t0 + 2400 : t0; });   // กระทงเราเริ่มลอยหลังตกลงน้ำ
        let last = t0;
        const tick = now => {
            const dt = Math.min((now - last) / 1000, 0.1);
            last = now;
            boats.forEach(b => { if (now >= b.startAt) { step(b, dt); render(b); } });
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    }

    // ---- ตอนปล่อยกระทงของเรา ----
    if (mine && !reduced) {
        mine.classList.add('is-releasing');
        const splash = mine.querySelector('.rk-splash');
        setTimeout(() => {
            for (let i = 0; i < 16; i++) {
                const d = document.createElement('b');
                const dir = i % 2 ? 1 : -1;
                d.style.setProperty('--dx', `${dir * rand(15, 90)}px`);
                d.style.setProperty('--dy', `${-rand(40, 110)}px`);
                splash.appendChild(d);
            }
            mine.classList.add('splashed');
        }, 2150);
        setTimeout(() => toast && toast.classList.add('river-toast-show'), 3200);
    } else if (toast) {
        toast.classList.add('river-toast-show');
    }

    // ---- โคมลอยบนท้องฟ้า ----
    if (!reduced) {
        const spawn = () => {
            if (sky.children.length < 5) {
                const l = document.createElement('i');
                l.className = 'sky-lantern';
                l.style.left = `${rand(5, 95)}%`;
                l.style.setProperty('--dx', `${rand(-40, 60)}px`);
                l.style.setProperty('--t', `${rand(14, 22)}s`);
                sky.appendChild(l);
                setTimeout(() => l.remove(), 22500);
            }
            setTimeout(spawn, rand(2500, 5000));
        };
        spawn();
    }
})();
