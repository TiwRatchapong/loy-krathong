(() => {
    const view = document.getElementById('riverView');
    const world = document.getElementById('riverWorld');
    const sky = document.getElementById('riverSky');
    const toast = document.getElementById('riverToast');
    const hud = document.getElementById('riverHud');
    const stars = document.getElementById('riverStars');
    const orbs = document.getElementById('riverOrbs');
    const dialog = document.getElementById('wishDialog');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // คำเปิดหน้า: เริ่มเล่นหลังฟอนต์พร้อม (สูงสุดรอ 1.5 วินาที) จะได้ไม่ขยับตอนฟอนต์สลับ
    const intro = document.querySelector('.moon-intro');
    if (intro) {
        const go = () => requestAnimationFrame(() => intro.classList.add('is-ready'));
        if (document.fonts && document.fonts.ready) Promise.race([document.fonts.ready, new Promise(r => setTimeout(r, 1500))]).then(go);
        else go();
    }
    const rand = (a, b) => a + Math.random() * (b - a);
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const MINE_SCALE = 1;                                       // กระทงของเราใหญ่กว่าของคนอื่น เห็นชัด
    const MOON = [69.4, 14.4, 5];                                 // ดวงจันทร์ในภาพ: x, y (% ภาพ) และรัศมี (% ความกว้างภาพ)

    // ---- พารามิเตอร์แม่น้ำ (ส่งมาจาก PHP: includes/functions.php ให้ตรงกันทั้งสองฝั่ง) ----
    const RATIO = parseFloat(world.dataset.ratio) || 0.5628;     // สูง/กว้างของภาพ
    const Y_FAR = parseFloat(world.dataset.yFar) || 60;
    const Y_NEAR = parseFloat(world.dataset.yNear) || 94;
    const S_FAR = parseFloat(world.dataset.sFar) || 0.42;
    const S_NEAR = parseFloat(world.dataset.sNear) || 1.35;
    let polyPct = [];
    try { polyPct = JSON.parse(world.dataset.poly || '[]'); } catch (e) { polyPct = []; }
    // คำนวณในหน่วย "% ของความกว้างภาพ" ทั้งแกน x และ y เพื่อให้ระยะทางเท่ากันทุกทิศ
    const poly = polyPct.map(([x, y]) => [x, y * RATIO]);
    const PX0 = polyPct.length ? Math.min(...polyPct.map(p => p[0])) : 5, PX1 = polyPct.length ? Math.max(...polyPct.map(p => p[0])) : 98;

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
    let bindWish = () => {};
    if (dialog && typeof dialog.showModal === 'function') {
        const name = document.getElementById('wishName');
        const text = document.getElementById('wishText');
        const link = document.getElementById('wishLink');
        const reportBtn = document.getElementById('wishReport');
        bindWish = item => {
            item.addEventListener('click', () => {
                name.textContent = item.dataset.name;
                text.textContent = item.dataset.prayer;   // textContent: ไม่ตีความ HTML
                link.href = `view.php?id=${encodeURIComponent(item.dataset.id)}`;
                if (reportBtn) {
                    reportBtn.dataset.id = item.dataset.id;
                    reportBtn.hidden = item.classList.contains('rk-mine');   // ไม่ต้องรายงานกระทงของตัวเอง
                }
                dialog.showModal();
                setPaused();
            });
        };
        world.querySelectorAll('.rk').forEach(bindWish);
        dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => setPaused());
    }

    // ---- กระทงลอยอยู่ในขอบเขตแม่น้ำ และเปลี่ยนขนาดตามความไกล-ใกล้ ----
    // ตำแหน่งตั้งต้น (left/top) มาจาก PHP และไม่ถูกแก้อีก — การเคลื่อนที่ใช้ transform อย่างเดียว
    // เพราะ transform ไม่ทำให้เบราว์เซอร์คำนวณเลย์เอาต์ใหม่ (เดิมแก้ left/top ทุกเฟรม × 40 ใบ จึงกระตุก)
    let worldW = world.offsetWidth;
    const SCALE_STEP = 0.02;                          // ขนาดเปลี่ยนเป็นขั้น ๆ (เปลี่ยนความกว้างต้องคำนวณเลย์เอาต์ จึงทำเท่าที่เห็นความต่าง)
    const makeBoat = el => {
        const x = parseFloat(el.dataset.x), y = parseFloat(el.dataset.y);
        return {
            el, isMine: el.classList.contains('rk-mine'),
            u: x, v: y * RATIO, u0: x, v0: y * RATIO, home: [x, y * RATIO],
            heading: parseFloat(el.dataset.heading) || rand(0, 6.28),
            target: 0, speed: parseFloat(el.dataset.speed) || 0.5,
            wobble: rand(0, 6.28), wobbleRate: rand(0.15, 0.4),
            lastS: -1, lastZ: -1, startAt: 0,
        };
    };
    // เครื่องอ่อน (มือถือ/แรมน้อย/คอร์น้อย): ลดจำนวนกระทงที่ขยับพร้อมกัน เพื่อไม่ให้กระตุก
    const lowPower = window.matchMedia('(pointer: coarse)').matches || window.innerWidth <= 760 || (navigator.hardwareConcurrency || 8) <= 4 || (navigator.deviceMemory || 8) <= 4
        || !!(navigator.connection && navigator.connection.saveData);
    let MAX_BOATS = lowPower ? 16 : 30;
    [...world.querySelectorAll('.rk:not(.rk-mine)')].slice(lowPower ? 14 : 30).forEach(el => el.remove());   // ลำที่เก่ากว่าอยู่ท้ายสุด (เรียงใหม่→เก่า)
    const boats = [...world.querySelectorAll('.rk')].map(makeBoat);
    boats.forEach(b => { b.target = b.heading; });

    // หาตำแหน่งใหม่ในน้ำที่อยู่ในกรอบจอ ห่างจากลำอื่นมากที่สุด
    function relocate(b) {
        let best = null, bestGap = -1;
        for (let t = 0; t < 160; t++) {
            const u = rand(lo, hi), v = rand(Y_FAR, yMax) * RATIO;
            if (!allowed(u, v)) continue;
            let gap = 99;
            boats.forEach(o => { if (o !== b && allowed(o.u, o.v)) gap = Math.min(gap, Math.hypot(o.u - u, (o.v - v) * 2.4)); });
            if (gap > bestGap) { bestGap = gap; best = [u, v]; }
            if (gap >= 7) break;
        }
        if (best) { b.u = best[0]; b.v = best[1]; }
    }

    function layout() {
        locked = lockQuery.matches;
        view.classList.toggle('is-locked', locked);
        const hdr = document.querySelector('.site-header');
        if (hud && hdr) hud.style.top = `${Math.round(hdr.getBoundingClientRect().bottom) + 4}px`;   // ชิปสถิติอยู่ใต้เมนูเสมอ
        worldW = world.offsetWidth;
        const W = worldW, H = world.offsetHeight;
        // จุดโฟกัสแนวนอน: เฉพาะ "มือถือ" (จอแคบ ≤760px) เลื่อนมาทางขวาให้เห็นพระจันทร์ (MOON x ≈ 69%) แต่ถ้ามีกระทงของเราต้องยังอยู่ในจอ
        // คอม/ไอแพด: เหมือนเดิม (กระทงของเรา หรือแสงจันทร์สะท้อนน้ำ)
        const phone = window.matchMedia('(max-width: 760px)').matches;
        const mineBoat = boats.find(b => b.isMine);
        if (phone && mineBoat && !mineBoat.nudged) { mineBoat.nudged = true; mineBoat.u = mineBoat.home[0] = 62; }   // ลงน้ำเยื้องขวาเล็กน้อย ให้เห็นทั้งกระทงและพระจันทร์
        const halfV = view.clientWidth / 2 / W;
        // ให้พระจันทร์อยู่มุมบนขวาของจอ (ศูนย์กลางที่ ~64% ของความกว้างจอ เว้นขอบขวา/ปุ่มเมนูไว้) ไม่ใช่กลางจอ
        const MOON_AT = Math.min(0.64, 1 - (MOON[2] / 100 * W + view.clientWidth * 0.08) / view.clientWidth);
        const MOON_FOCUS = MOON[0] / 100 - (MOON_AT - 0.5) * 2 * halfV;
        const mineX = !mine ? null : (phone && mineBoat ? mineBoat.u : parseFloat(mine.dataset.x)) / 100;   // มือถือ: ใช้ตำแหน่งที่เยื้องขวาแล้ว (ไม่ใช่ค่าตั้งต้น 50)
        const edge = Math.min(0.065, halfV * 0.5);
        const focus = !phone ? (mineX ?? 0.6)
            : (mineX === null ? MOON_FOCUS : clamp(MOON_FOCUS, mineX - halfV + edge, mineX + halfV - edge));
        view.scrollLeft = clamp(focus * W - view.clientWidth / 2, 0, Math.max(0, W - view.clientWidth));
        if (!locked) { lo = 0; hi = 100; yMax = 100; return; }
        const padX = Math.min(70, view.clientWidth * 0.12);
        const offX = world.offsetLeft;                                  // ภาพแคบกว่าจอ (จอกว้างมาก) ถูกจัดกลางด้วย margin
        lo = clamp((view.scrollLeft + padX - offX) / W * 100, 0, 100);
        hi = clamp((view.scrollLeft + view.clientWidth - padX - offX) / W * 100, 0, 100);
        let bottomPx = view.clientHeight - 40;
        if (toast) bottomPx = Math.min(bottomPx, view.clientHeight - toast.offsetHeight - 44);   // เหนือแถบปุ่มด้านล่าง
        const worldTop = world.getBoundingClientRect().top;
        yMax = Math.max(Y_FAR + 8, (bottomPx - worldTop) / H * 100);
        boats.forEach(b => {
            if (!allowed(b.u, b.v)) relocate(b);
            if (!allowed(b.home[0], b.home[1])) b.home = [b.u, b.v];
            render(b);
        });
    }
    layout();
    let layoutQueued = false;   // resize ยิงถี่มาก (โดยเฉพาะมือถือที่แถบ URL ยืด/หด) → รวมเป็นครั้งเดียวต่อเฟรม
    window.addEventListener('resize', () => {
        if (layoutQueued) return;
        layoutQueued = true;
        requestAnimationFrame(() => { layoutQueued = false; layout(); });
    });
    if (lockQuery.addEventListener) lockQuery.addEventListener('change', layout);
    // ฉากกว้างกว่าจอ: เริ่มที่ขอบซ้าย แล้ว layout() เลื่อนมากลาง → ซ่อนฉากไว้ (opacity 0 ใน CSS) จนเลื่อนเสร็จจึงค่อยแสดง กันภาพแว๊บที่ขอบซ้าย
    // เรียก layout() ซ้ำตอน load/pageshow เพราะเบราว์เซอร์มือถืออาจคืนตำแหน่งเลื่อนเดิมหลังโหลดตอนรีเฟรช
    const reveal = () => requestAnimationFrame(() => view.classList.add('is-ready'));
    reveal();
    window.addEventListener('load', () => { layout(); reveal(); }, { once: true });
    window.addEventListener('pageshow', e => { if (e.persisted) layout(); });

    // ฟองคำอธิษฐานเหนือกระทง: ดันกลับเข้ากรอบจอถ้าชิดขอบ
    const bindFit = el => {
        const w = el.querySelector('.rk-wish');
        if (!w) return;
        const fit = () => {
            w.style.setProperty('--shift', '0px');
            const r = w.getBoundingClientRect(), m = 10;
            const s = r.left < m ? m - r.left : (r.right > window.innerWidth - m ? window.innerWidth - m - r.right : 0);
            w.style.setProperty('--shift', `${s}px`);
        };
        ['pointerenter', 'pointermove', 'focus'].forEach(ev => el.addEventListener(ev, fit));
        if (el.classList.contains('rk-mine')) { fit(); window.addEventListener('resize', fit); setTimeout(fit, 2600); }   // ฟองคำอธิษฐานของเราแสดงตลอด: ดันกลับเข้ากรอบจอเสมอ
    };
    world.querySelectorAll('.rk').forEach(bindFit);

    function angleDiff(a, b) { return Math.atan2(Math.sin(a - b), Math.cos(a - b)); }

    function render(b) {
        const yPct = b.v / RATIO;
        const k = worldW / 100;                         // 1% ของความกว้างภาพ เป็นพิกเซล (u และ v วัดเป็น % ความกว้างทั้งคู่)
        b.el.style.transform = `translate3d(${((b.u - b.u0) * k).toFixed(2)}px, ${((b.v - b.v0) * k).toFixed(2)}px, 0) translate(-50%, -78%)`;
        const s = scaleAt(yPct) * (b.isMine ? MINE_SCALE : 1);
        b.s = s;
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

    // ---- ลูปแอนิเมชันเดียวของทั้งหน้า (เดิมมี rAF แยก 2 ลูป: กระทง + เมฆ) ----
    // - หยุดทำงานเมื่อซ่อนแท็บ หรือเปิดหน้าต่างคำอธิษฐาน (ฉากถูกบังอยู่ ไม่ต้องคำนวณ)
    // - ตัวคุมเฟรมเรต: ถ้าเฉลี่ยต่ำกว่า ~38 fps ต่อเนื่อง จะลดเอฟเฟกต์เป็นขั้น (is-lite → is-min) อัตโนมัติ
    //   เพื่อให้ทุกอุปกรณ์ได้ภาพลื่น แม้เครื่องที่เราไม่ได้ทดสอบ
    const hooks = [];                                   // (now, dt, frameNo) ทำงานทุกเฟรมที่ไม่ได้หยุดพัก
    let paused = false, frameNo = 0;
    function setPaused() {
        paused = document.hidden || !!(dialog && dialog.open);
        view.classList.toggle('is-paused', paused);
    }
    document.addEventListener('visibilitychange', setPaused);

    if (!reduced) {
        if (poly.length > 2) {
            const t0 = performance.now();
            boats.forEach(b => { b.startAt = b.isMine ? t0 + 2400 : t0; });   // กระทงเราเริ่มลอยหลังตกลงน้ำ
            hooks.push((now, dt, n) => {
                for (const b of boats) if (now >= b.startAt) step(b, dt);
                for (const b of boats) if (now >= b.startAt) render(b);
            });
        }
        let last = performance.now();
        const frame = now => {
            requestAnimationFrame(frame);
            const raw = now - last;
            last = now;
            if (paused) return;
            frameNo++;
            const dt = Math.min(raw / 1000, 0.1);
            for (const h of hooks) h(now, dt, frameNo);
        };
        requestAnimationFrame(frame);
    }

    // ---- ผืนน้ำ: วาดลง canvas ใบเดียว ----
    // เดิมเป็นแถบ CSS 9 ชั้น + clip-path ที่ต้องวาดมาสก์ใหม่ทุกเฟรม → เหลือ 1 เลเยอร์ วาดที่ ~30fps ด้วยความละเอียดต่ำ (น้ำเป็นภาพสะท้อนนุ่มๆ ตาดูไม่ออก)
    const wave = document.getElementById('riverWave');
    if (wave && wave.getContext && !reduced && poly.length > 2) {
        let bands = [];
        try { bands = JSON.parse(world.dataset.bands || '[]'); } catch (e) { bands = []; }
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => {
            const CW = 480, CH = Math.round(CW * RATIO);
            wave.width = CW; wave.height = CH;
            const g = wave.getContext('2d');
            g.beginPath();                                                       // ตัดด้วยขอบผืนน้ำ ครั้งเดียวตอนเริ่ม
            polyPct.forEach(([x, y], i) => { const px = x / 100 * CW, py = y / 100 * CH; if (i) g.lineTo(px, py); else g.moveTo(px, py); });
            g.closePath();
            g.clip();
            const src = document.createElement('canvas');                        // ย่อภาพต้นฉบับครั้งเดียว แล้ววาดจากภาพเล็กทุกเฟรม (เบากว่าย่อจากภาพใหญ่ซ้ำ ๆ)
            src.width = CW; src.height = CH;
            src.getContext('2d').drawImage(img, 0, 0, CW, CH);
            const waves = bands.map(([top, h], n) => ({ top, h: h + 0.15, amp: (0.0011 + n * 0.00032) * 3.2, dur: 5.2 + ((n * 37) % 11) * 0.33, del: -0.97 * n }));
            // แสงจันทร์สะท้อนบนน้ำ: แท่งไล่สีนุ่ม ๆ สร้างครั้งเดียว กะพริบด้วย globalAlpha (ถูกตัดด้วยขอบน้ำอยู่แล้ว)
            const colX = CW * 0.665, colW = CW * 0.17, colTop = CH * 0.63;
            const col = g.createLinearGradient(0, colTop, 0, CH);
            col.addColorStop(0, 'rgba(255,226,150,.38)'); col.addColorStop(1, 'rgba(255,226,150,.05)');
            const colH = g.createLinearGradient(colX - colW, 0, colX + colW, 0);
            colH.addColorStop(0, 'rgba(255,226,150,0)'); colH.addColorStop(.5, 'rgba(255,226,150,1)'); colH.addColorStop(1, 'rgba(255,226,150,0)');
            const draw = t => {
                g.clearRect(0, 0, CW, CH);
                for (const w of waves) {
                    const dx = -w.amp * CW * Math.cos(Math.PI * (t + w.del) / w.dur);
                    g.drawImage(src, 0, w.top / 100 * CH, CW, w.h / 100 * CH, dx, w.top / 100 * CH, CW, w.h / 100 * CH);
                }
                // ชั้นแสงสะท้อน: ลายระลอกแนวนอนเรียงเป็นเส้นบางที่ขยับไปมา
                g.fillStyle = colH;
                for (let y = colTop, n = 0; y < CH; y += 5, n++) {
                    const k = (y - colTop) / (CH - colTop);
                    const sway = Math.sin(t * 1.3 + n * 0.9) * (3 + k * 9);
                    g.globalAlpha = (0.16 + 0.34 * k) * (0.55 + 0.45 * Math.sin(t * 1.7 + n * 1.3));
                    g.fillRect(colX - colW * (0.5 + k * 0.7) + sway, y, colW * (1 + k * 1.4), 1.6 + k * 1.4);
                }
                g.globalAlpha = 1;
                g.fillStyle = 'rgba(5,8,34,.42)';                                // โทนมืดเดียวกับ --night-tint ของ .river-world
                g.fillRect(0, 0, CW, CH);
            };
            draw(0);
            hooks.push((now, dt, n) => { if (n % 3 === 0) draw(now / 1000); });   // ~20fps
        };
        img.src = world.dataset.bg || '';
    }

    // ---- ตอนปล่อยกระทงของเรา ----
    const returning = world.dataset.returning === '1';   // กลับมาเยี่ยม: กระทงของเราลอยอยู่แล้ว ไม่ต้องเล่นอนิเมชั่นปล่อยซ้ำ
    if (mine && !reduced && !returning) {
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

    // ---- บรรยากาศคืนจันทร์: ดาวระยิบ / ประกายลอยบนผิวน้ำ / โคมลอยฟ้า ----
    if (!reduced) {
        const frag = document.createDocumentFragment();
        // จัดกลุ่ม: ดาว/ประกายหลายสิบดวงกะพริบเป็นกลุ่มละ 1 เลเยอร์ (เดิม 1 ดวง = 1 เลเยอร์ที่เบราว์เซอร์ต้องคอมโพสต์ทุกเฟรม)
        const mkGroups = (parent, cls, k, tMin, tMax) => Array.from({ length: k }, (_, i) => {
            const g = document.createElement('div');
            g.className = cls;
            g.style.cssText = `--t:${(tMin + (tMax - tMin) * i / Math.max(1, k - 1)).toFixed(1)}s;--d:${(-i * 1.7).toFixed(1)}s`;
            parent.appendChild(g);
            return g;
        });
        const sg = mkGroups(frag, 'sg', 2, 2.8, 6);
        const starCount = lowPower ? 8 : 12;
        for (let i = 0; i < starCount; i++) {
            const x = rand(2, 98), y = rand(2, 96);
            if (Math.hypot(x - MOON[0], (y * 0.58 - MOON[1]) * RATIO) < MOON[2] * 1.5) continue;   // ไม่ใส่ดาวทับดวงจันทร์
            const s = document.createElement('i');
            s.style.cssText = `left:${x.toFixed(1)}%;top:${y.toFixed(1)}%;--t:${rand(2.8, 6).toFixed(1)}s;--d:${rand(-6, 0).toFixed(1)}s`;
            sg[i % sg.length].appendChild(s);
        }
        stars && stars.appendChild(frag);

        // หิ่งห้อย/ประกายแสงบนผิวน้ำ: สุ่มแบบกระจายสม่ำเสมอ (เลือกจุดที่ห่างจากดวงอื่นที่สุด) เฉพาะในผืนน้ำ
        const of = document.createDocumentFragment();
        const placed = [];
        const og = mkGroups(of, 'og', 2, 5, 11);
        const orbCount = lowPower ? 5 : 8;
        for (let n = 0, tries = 0; n < orbCount && tries < 400; tries++) {
            let best = null, bestGap = -1;
            for (let c = 0; c < 8; c++) {
                const x = rand(PX0, PX1), y = rand(Y_FAR, Y_NEAR);
                if (!inside(x, y * RATIO)) continue;
                const gap = placed.reduce((g, q) => Math.min(g, Math.hypot(q[0] - x, (q[1] - y) * RATIO)), 99);
                if (gap > bestGap) { bestGap = gap; best = [x, y]; }
            }
            if (!best) continue;
            placed.push(best);
            const o = document.createElement('i');
            const sz = 0.6 + (best[1] - Y_FAR) / (Y_NEAR - Y_FAR) * 0.9;   // ยิ่งใกล้ยิ่งใหญ่
            o.style.cssText = `left:${best[0].toFixed(1)}%;top:${best[1].toFixed(1)}%;--z:${sz.toFixed(2)};--t:${rand(5, 11).toFixed(1)}s;--d:${rand(-10, 0).toFixed(1)}s`;
            og[n % og.length].appendChild(o);
            n++;
        }
        orbs && orbs.appendChild(of);

        // โคมลอย: ขึ้นจากฝั่งตรงข้าม (หลังแนวต้นไม้/วัด y≈60% ของภาพ) ปล่อยกระจายหลายจุดตลอดแนวฝั่ง ถี่ขึ้นและมีหลายขนาด (ไกล/ใกล้)
        const maxLanterns = lowPower ? 2 : 3;
        let lastX = 50;
        const spawnOne = () => {
            if (document.hidden || sky.children.length >= maxLanterns) return;
            let x = rand(5, 97);
            for (let t = 0; t < 6 && Math.abs(x - lastX) < 12; t++) x = rand(5, 97);   // ไม่ปล่อยซ้ำจุดเดิมติดกัน
            lastX = x;
            const near = Math.random();
            const l = document.createElement('i');
            l.className = 'sky-lantern';
            l.appendChild(document.createElement('b'));
            l.style.left = `${x.toFixed(1)}%`;
            l.style.bottom = `${rand(-2, 7).toFixed(1)}%`;
            l.style.setProperty('--ls', (0.008 + near * 0.014).toFixed(4));
            l.style.setProperty('--dx', `${rand(-70, 90).toFixed(0)}px`);
            l.style.setProperty('--rise', rand(-0.46, -0.3).toFixed(2));
            l.style.setProperty('--t', `${(27 - near * 7 + rand(-3, 3)).toFixed(1)}s`);
                        sky.appendChild(l);
            setTimeout(() => l.remove(), 34000);
        };
        const spawnLoop = () => { spawnOne(); setTimeout(spawnLoop, rand(8000, 13000)); };
        for (let i = 0; i < 2; i++) setTimeout(spawnOne, 300 + i * 1800);   // เปิดหน้ามาแล้วมีโคมขึ้นทันทีหลายดวง
        setTimeout(spawnLoop, 3200);
    }

    // ---- แถบปุ่มด้านล่าง: ข้อความช่วยเหลือหุบเก็บหลังอ่านแล้ว ให้เหลือแต่ปุ่ม (ไม่รก) ----
    if (toast && toast.querySelector('.river-toast-msg')) {
        setTimeout(() => toast.classList.add('is-compact'), mine && !reduced && !returning ? 11500 : 7500);
    }

    // ---- อัปเดตสด: ยอดกระทง + กระทงใหม่ที่คนอื่นเพิ่งลอย (ถามเซิร์ฟเวอร์ทุก ~25 วินาที) ----
    const liveUrl = world.dataset.live;
    let latestId = parseInt(world.dataset.latest, 10) || 0;
    function addLiveBoat(html) {
        const tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        const el = tpl.content.firstElementChild;
        if (!el || world.querySelector(`.rk[data-id="${el.dataset.id}"]`)) return;
        world.appendChild(el);
        const b = makeBoat(el);
        boats.push(b);
        bindWish(el);
        bindFit(el);
        if (locked && !allowed(b.u, b.v)) relocate(b);
        b.target = b.heading;
        b.startAt = performance.now();
        render(b);
        if (!reduced) {
            el.classList.add('rk-arrive');
            el.addEventListener('animationend', () => el.classList.remove('rk-arrive'), { once: true });
        }
        // เกินเพดาน: เอากระทงเก่าสุด (ไม่ใช่ของเรา) ออก
        while (boats.length > MAX_BOATS) {
            const i = boats.findIndex(o => !o.isMine);
            if (i < 0) break;
            boats[i].el.remove();
            boats.splice(i, 1);
        }
    }
    async function pollLive() {
        try {
            const res = await fetch(`${liveUrl}?after=${latestId}`, { cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            if (window.ktUpdateStats) window.ktUpdateStats(data);
            (data.boats || []).forEach(addLiveBoat);
            if (data.latest > latestId) latestId = data.latest;
        } catch (e) { /* เงียบไว้ แล้วลองใหม่รอบหน้า */ }
    }
    if (liveUrl && window.fetch) {
        const schedule = () => setTimeout(async () => {
            if (!document.hidden) await pollLive();
            schedule();
        }, rand(22000, 30000));
        schedule();
        document.addEventListener('visibilitychange', () => { if (!document.hidden) pollLive(); });   // กลับมาที่แท็บ: อัปเดตทันที
    }
})();
