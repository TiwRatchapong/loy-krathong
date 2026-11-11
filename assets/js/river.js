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

    // ---- มุมมอง: มือถือ/ไอแพดล็อกจอ ไม่ให้เลื่อน และบังคับกระทงทุกใบให้อยู่ในส่วนที่มองเห็น ----
    const mine = world.querySelector('.rk-mine');
    const lockQuery = window.matchMedia('(pointer: coarse), (max-width: 1024px)');
    let locked = false;
    let lo = 0, hi = 100, yMax = 100;           // ขอบเขตที่กระทงลอยได้ (x เป็น % ความกว้างภาพ, y เป็น % ความสูงภาพ)
    const inBounds = (u, v) => !locked || (u >= lo && u <= hi && v / RATIO <= yMax);
    const allowed = (u, v) => inside(u, v) && inBounds(u, v);

    // ---- กดดูคำอธิษฐาน ----
    if (dialog && typeof dialog.showModal === 'function') {
        const name = document.getElementById('wishName');
        const text = document.getElementById('wishText');
        const link = document.getElementById('wishLink');
        const reportBtn = document.getElementById('wishReport');
        world.querySelectorAll('.rk').forEach(item => {
            item.addEventListener('click', () => {
                name.textContent = item.dataset.name;
                text.textContent = item.dataset.prayer;   // textContent: ไม่ตีความ HTML
                link.href = `view.php?id=${encodeURIComponent(item.dataset.id)}`;
                if (reportBtn) {
                    reportBtn.dataset.id = item.dataset.id;
                    reportBtn.hidden = item.classList.contains('rk-mine');   // ไม่ต้องรายงานกระทงของตัวเอง
                }
                dialog.showModal();
            });
        });
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    }

    // ---- กระทงลอยอยู่ในขอบเขตแม่น้ำ และเปลี่ยนขนาดตามความไกล-ใกล้ ----
    // ตำแหน่งตั้งต้น (left/top) มาจาก PHP และไม่ถูกแก้อีก — การเคลื่อนที่ใช้ transform อย่างเดียว
    // เพราะ transform ไม่ทำให้เบราว์เซอร์คำนวณเลย์เอาต์ใหม่ (เดิมแก้ left/top ทุกเฟรม × 40 ใบ จึงกระตุก)
    let worldW = world.offsetWidth;
    const SCALE_STEP = 0.02;                          // ขนาดเปลี่ยนเป็นขั้น ๆ (เปลี่ยนความกว้างต้องคำนวณเลย์เอาต์ จึงทำเท่าที่เห็นความต่าง)
    const boats = [...world.querySelectorAll('.rk')].map(el => {
        const x = parseFloat(el.dataset.x), y = parseFloat(el.dataset.y);
        return {
            el, isMine: el.classList.contains('rk-mine'),
            u: x, v: y * RATIO, u0: x, v0: y * RATIO, home: [x, y * RATIO],
            heading: parseFloat(el.dataset.heading) || rand(0, 6.28),
            target: 0, speed: parseFloat(el.dataset.speed) || 0.5,
            wobble: rand(0, 6.28), wobbleRate: rand(0.15, 0.4),
            lastS: -1, lastZ: -1, startAt: 0,
        };
    });
    boats.forEach(b => { b.target = b.heading; });

    // หาตำแหน่งใหม่ในน้ำที่อยู่ในกรอบจอ ห่างจากลำอื่นมากที่สุด
    function relocate(b) {
        let best = null, bestGap = -1;
        for (let t = 0; t < 160; t++) {
            const u = rand(lo, hi), v = rand(Y_FAR, yMax) * RATIO;
            if (!allowed(u, v)) continue;
            let gap = 99;
            boats.forEach(o => { if (o !== b && allowed(o.u, o.v)) gap = Math.min(gap, Math.hypot(o.u - u, o.v - v)); });
            if (gap > bestGap) { bestGap = gap; best = [u, v]; }
            if (gap >= 7) break;
        }
        if (best) { b.u = best[0]; b.v = best[1]; }
    }

    function layout() {
        locked = lockQuery.matches;
        view.classList.toggle('is-locked', locked);
        worldW = world.offsetWidth;
        const W = worldW, H = world.offsetHeight;
        const focus = mine ? parseFloat(mine.dataset.x) / 100 : 0.56;
        view.scrollLeft = clamp(focus * W - view.clientWidth / 2, 0, Math.max(0, W - view.clientWidth));
        if (!locked) { lo = 0; hi = 100; yMax = 100; return; }
        const padX = Math.min(70, view.clientWidth * 0.12);
        lo = (view.scrollLeft + padX) / W * 100;
        hi = (view.scrollLeft + view.clientWidth - padX) / W * 100;
        let bottomPx = view.clientHeight - 40;
        if (toast) bottomPx = Math.min(bottomPx, view.clientHeight - toast.offsetHeight - 70);   // เหนือกล่องข้อความด้านล่าง
        const worldTop = world.getBoundingClientRect().top;
        yMax = Math.max(Y_FAR + 8, (bottomPx - worldTop) / H * 100);
        boats.forEach(b => {
            if (!allowed(b.u, b.v)) relocate(b);
            if (!allowed(b.home[0], b.home[1])) b.home = [b.u, b.v];
            render(b);
        });
    }
    layout();
    window.addEventListener('resize', layout);
    if (lockQuery.addEventListener) lockQuery.addEventListener('change', layout);

    // ฟองคำอธิษฐานเหนือกระทง: ดันกลับเข้ากรอบจอถ้าชิดขอบ
    world.querySelectorAll('.rk').forEach(el => {
        const w = el.querySelector('.rk-wish');
        if (!w) return;
        const fit = () => {
            w.style.setProperty('--shift', '0px');
            const r = w.getBoundingClientRect(), m = 10;
            const s = r.left < m ? m - r.left : (r.right > window.innerWidth - m ? window.innerWidth - m - r.right : 0);
            w.style.setProperty('--shift', `${s}px`);
        };
        ['pointerenter', 'pointermove', 'focus'].forEach(ev => el.addEventListener(ev, fit));
    });

    function angleDiff(a, b) { return Math.atan2(Math.sin(a - b), Math.cos(a - b)); }

    function render(b) {
        const yPct = b.v / RATIO;
        const k = worldW / 100;                         // 1% ของความกว้างภาพ เป็นพิกเซล (u และ v วัดเป็น % ความกว้างทั้งคู่)
        b.el.style.transform = `translate3d(${((b.u - b.u0) * k).toFixed(2)}px, ${((b.v - b.v0) * k).toFixed(2)}px, 0) translate(-50%, -78%)`;
        const s = scaleAt(yPct) * (b.isMine ? 1.2 : 1);
        if (Math.abs(s - b.lastS) > SCALE_STEP) { b.el.style.setProperty('--s', s.toFixed(3)); b.lastS = s; }
        if (!b.isMine) {
            const z = Math.round(yPct * 4);
            if (z !== b.lastZ) { b.el.style.zIndex = String(z); b.lastZ = z; }
        }
    }

    function step(b, dt) {
        const s = scaleAt(b.v / RATIO);
        const look = 1.2 + 1.8 * s;                       // ระยะเผื่อขอบ: กระทงใหญ่ (ใกล้) เผื่อมากกว่า
        const v = b.speed * (0.5 + 0.5 * s) * dt;         // อยู่ไกลก็ดูเคลื่อนช้าลง
        b.wobble += b.wobbleRate * dt;
        b.target += Math.sin(b.wobble) * 0.35 * dt;       // เลี้ยวเบา ๆ ให้ลอยเป็นทางโค้ง

        const probe = ang => allowed(b.u + Math.cos(ang) * (v + look), b.v + Math.sin(ang) * (v + look));
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
        if (allowed(nu, nv)) { b.u = nu; b.v = nv; }
        else { b.target = b.heading + Math.PI * 0.5; }    // กันพลาด: ไม่ก้าวออกนอกขอบเขตเด็ดขาด
        if (!allowed(b.u, b.v)) { b.u = b.home[0]; b.v = b.home[1]; }
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
