(() => {
    // กระทงลอยประดับหน้าแรก: ปล่อยจากต้นน้ำซ้ายสุด ลอยไปขวาสุด แล้วค่อยๆ จางหาย จากนั้นปล่อยลำใหม่จากซ้ายอีกครั้ง
    // ลอยเฉพาะในผืนน้ำ (ขอบเขตเดียวกับ pages/river.php) และใช้เฉพาะแถวที่น้ำต่อเนื่อง — ไม่ลอยหน้าท่าน้ำ/สะพานไม้ ไม่ปล่อยกลางทาง
    const root = document.getElementById('heroRiver');
    const scene = document.querySelector('.scene');
    if (!root || !scene) return;
    const els = [...root.querySelectorAll('.hf')];
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rand = (a, b) => a + Math.random() * (b - a);

    const RATIO = parseFloat(root.dataset.ratio) || 0.5628;
    const Y_FAR = parseFloat(root.dataset.yFar) || 63.5;
    const Y_NEAR = parseFloat(root.dataset.yNear) || 97.5;
    let polyPct = [];
    try { polyPct = JSON.parse(root.dataset.poly || '[]'); } catch (e) { polyPct = []; }
    if (polyPct.length < 3) return;
    const poly = polyPct.map(([x, y]) => [x, y * RATIO]);   // หน่วย: % ของความกว้างภาพ ทั้งแกน x และ y

    function inside(u, v) {
        let c = false;
        for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
            const [xi, yi] = poly[i], [xj, yj] = poly[j];
            if ((yi > v) !== (yj > v) && u < (xj - xi) * (v - yi) / ((yj - yi) || 1e-9) + xi) c = !c;
        }
        return c;
    }

    // ---- ตำแหน่งภาพพื้นหลังบนจอ (cover + background-position ตาม CSS จริง) ----
    let W = 0, OX = 0, OY = 0, VW = 0, VH = 0;
    function geometry() {
        const r = scene.getBoundingClientRect();
        const pos = (getComputedStyle(scene).backgroundPosition || '50% 50%').split(' ');
        const px = parseFloat(pos[0]) / 100, py = parseFloat(pos[1] ?? pos[0]) / 100;
        W = Math.max(r.width, r.height / RATIO);
        OX = r.left + (r.width - W) * (isNaN(px) ? 0.5 : px);
        OY = r.top + (r.height - W * RATIO) * (isNaN(py) ? 0.5 : py);
        VW = window.innerWidth; VH = Math.min(window.innerHeight, r.height);
    }

    // ขนาดกระทงเท่าเดิม (px): ไกล 46 → ใกล้ ~116 ; ความเร็วเท่าเดิม ข้ามจอ 140vw ใน 70–120 วินาที (ใกล้เร็วกว่า)
    const SIZE_MAX = 122;
    const boats = els.map(el => ({ el, rank: 0, size: 0, vx: 0, v: 0, u0: 0, u1: 0, u: 0, wait: 0, active: false, lastZ: -1 }));
    boats.map((_, i) => i).sort(() => Math.random() - 0.5).forEach((bi, rank) => { boats[bi].rank = rank; });   // สุ่มว่าลำไหนอยู่ระดับความลึกไหน

    // ช่วงน้ำของแถว v ที่กระทงลอยได้ (เว้นขอบข้างตามความกว้างกระทง ไม่ให้ตัวกระทงล้ำขึ้นฝั่ง/ท่าน้ำ)
    // คืน [ซ้ายสุด, ขวาสุด] เฉพาะเมื่อน้ำในแถวนั้น "ต่อเนื่องตลอดแนว" ถ้าถูกท่าน้ำ/สิ่งกีดขวางตัดกลางคืน null
    function laneFor(size, v) {
        const hw = (size / W) * 100 * 0.55;
        const ok = u => inside(u, v) && inside(u - hw, v) && inside(u + hw, v) && inside(u, v + 0.6) && inside(u - hw, v + 0.4) && inside(u + hw, v + 0.4);
        let first = null, last = null, runs = 0, prev = false;
        for (let u = -2; u <= 102; u += 0.25) {
            const o = ok(u);
            if (o) { if (first === null) first = u; last = u; if (!prev) runs++; }
            prev = o;
        }
        return runs === 1 ? [first, last] : null;
    }

    function setup() {
        geometry();
        const live = boats.filter(b => getComputedStyle(b.el).display !== 'none').sort((a, c) => a.rank - c.rank);
        boats.forEach(b => { b.was = b.active; b.active = false; b.el.style.visibility = 'hidden'; });

        // แถวที่ลึก (ใกล้) ที่สุดที่น้ำยังต่อเนื่อง และก้นกระทงไม่หลุดขอบล่างของจอ
        let vLimit = Math.min(Y_NEAR * RATIO, (VH - 24 - OY) / W * 100);
        while (vLimit > Y_FAR * RATIO && !laneFor(SIZE_MAX, vLimit)) vLimit -= 0.25;
        const vTop = (Y_FAR + 2) * RATIO;

        live.forEach((b, i) => {
            const depth = live.length > 1 ? i / (live.length - 1) : 0.5;
            if (b.jit === undefined) { b.jit = rand(-6, 6); b.dur = rand(70, 120); b.vJit = rand(-0.4, 0.4); b.delay = i === 0 ? 0 : rand(0, 20); }
            b.size = Math.round(46 + depth * 70 + b.jit);
            b.el.style.width = b.size + 'px';
            b.vx = (VW * 1.4) / (b.dur - depth * 25);                 // px ต่อวินาที
            let v = Math.min(vTop + depth * (vLimit - vTop) + b.vJit, vLimit);
            let lane = laneFor(b.size, v);
            for (let k = 0; !lane && k < 40; k++) { v -= 0.25; lane = laneFor(b.size, v); }
            if (!lane || OY + v / 100 * W < 0) return;
            b.v = v;
            // ปล่อยจากต้นน้ำซ้ายสุด → ขวาสุด (ตัดส่วนที่อยู่นอกจอออก เพื่อไม่ต้องลอยรอข้างนอก)
            b.u0 = Math.max(lane[0], (-b.size - OX) / W * 100);
            b.u1 = Math.min(lane[1], (VW + b.size - OX) / W * 100);
            if (b.u1 <= b.u0) return;
            if (!(b.was && b.u >= b.u0 && b.u <= b.u1)) { b.u = b.u0; b.wait = reduced ? 0 : b.delay; }   // เริ่มที่ต้นน้ำเสมอ ทยอยปล่อยทีละลำ
            if (reduced) b.u = b.u0 + (b.u1 - b.u0) * 0.5;
            b.active = true;
            b.el.style.visibility = '';
            render(b);
        });
        root.classList.add('is-ready');
    }

    const FADE = 4;                                                   // ระยะจาง (% ภาพ) ตอนเข้า/ออกผืนน้ำ
    function render(b) {
        const sx = OX + b.u / 100 * W, sy = OY + b.v / 100 * W;
        b.el.style.transform = `translate3d(${sx.toFixed(1)}px, ${sy.toFixed(1)}px, 0) translate(-50%, -78%)`;
        const edge = Math.min(b.u - b.u0, b.u1 - b.u);
        b.el.style.opacity = reduced ? '1' : (b.wait > 0 ? 0 : Math.max(0, Math.min(1, edge / FADE))).toFixed(2);
        const z = Math.round(b.v / RATIO * 4);
        if (z !== b.lastZ) { b.el.style.zIndex = String(z); b.lastZ = z; }
    }

    setup();
    let queued = false;
    window.addEventListener('resize', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; setup(); });
    });
    window.addEventListener('load', setup, { once: true });

    if (reduced) return;
    let last = performance.now(), paused = document.hidden;
    document.addEventListener('visibilitychange', () => { paused = document.hidden; last = performance.now(); });
    const frame = now => {
        requestAnimationFrame(frame);
        const dt = Math.min((now - last) / 1000, 0.1);
        last = now;
        if (paused) return;
        for (const b of boats) {
            if (!b.active) continue;
            if (b.wait > 0) { b.wait -= dt; render(b); continue; }
            b.u += (b.vx * dt) / W * 100;                            // ลอยซ้ายไปขวาเท่านั้น
            if (b.u >= b.u1) { b.u = b.u0; b.wait = rand(0, 1.5); }    // ถึงขวาสุด (จางหมดแล้ว) → ปล่อยลำใหม่จากต้นน้ำ
            render(b);
        }
    };
    requestAnimationFrame(frame);
})();
