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

    // ---- บันทึกเป็นรูปภาพ (PNG 1080×1350 สำหรับโพสต์โซเชียล) วาดในเบราว์เซอร์ ไม่ต้องใช้เซิร์ฟเวอร์ ----
    const saveBtn = document.getElementById('saveImage');
    function loadImg(src) {
        return new Promise((ok) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; });
    }
    saveBtn?.addEventListener('click', async () => {
        const art = document.querySelector('.view-art');
        const sprites = [...document.querySelectorAll('svg[width="0"][height="0"]')];   // sprite ซ่อนไว้ (กระทง/ดอกไม้/ไล่สี) อาจมีหลายชุด
        if (!art || !sprites.length) return;
        const label = saveBtn.innerHTML;
        saveBtn.disabled = true; saveBtn.textContent = 'กำลังสร้างรูป…';
        try {
            const W = 1080, H = 1350;
            // รวม defs ของ sprite (symbol + gradient) เข้าไปในสำเนา SVG เพื่อให้ <use> ทำงานเมื่อเป็นภาพเดี่ยว
            const clone = art.cloneNode(true);
            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            clone.setAttribute('width', '900'); clone.setAttribute('height', '900');
            clone.setAttribute('viewBox', clone.getAttribute('viewBox') || '0 0 240 200');
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            defs.innerHTML = sprites.map(sp => sp.innerHTML).join('');
            clone.insertBefore(defs, clone.firstChild);
            const svgUrl = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' }));
            const img = new Image();
            await new Promise((ok, fail) => { img.onload = ok; img.onerror = fail; img.src = svgUrl; });
            // โหลดฟอนต์หลักของเว็บ (Kanit) ให้ครบทุกน้ำหนักที่ใช้ — ต้องส่งตัวอย่างอักษรไทยด้วย ไม่งั้นเบราว์เซอร์ไม่โหลดชุดตัวอักษรไทย แล้ววาดด้วยฟอนต์สำรอง
            if (document.fonts?.load) {
                try { await Promise.all(['400', '500', '600'].map(w => document.fonts.load(`${w} 40px Kanit`, 'กระทงลอยคำอธิษฐาน Aa'))); } catch (e) { /* ใช้ฟอนต์สำรอง */ }
            }
            // ฟอนต์ไทยลายมือ Charmonman สำหรับรูปที่บันทึก (หน้านี้ยังไม่ได้โหลดไว้ จึงโหลดเพิ่มตอนกดบันทึก) — ถ้าโหลดไม่ได้จะใช้ Kanit แทน
            let thai = false;
            try {
                if (!document.getElementById('fontCharmonman')) {
                    const l = document.createElement('link'); l.id = 'fontCharmonman'; l.rel = 'stylesheet';
                    l.href = 'https://fonts.googleapis.com/css2?family=Charmonman:wght@400;700&display=swap';
                    await new Promise((ok, fail) => { l.onload = ok; l.onerror = fail; document.head.appendChild(l); setTimeout(fail, 6000); });
                }
                await Promise.all(['400', '700'].map(w => document.fonts.load(`${w} 40px Charmonman`, 'กระทงลอยคำอธิษฐาน Aa')));
                thai = document.fonts.check('700 40px Charmonman', 'กระทง');
            } catch (e) { thai = false; }
            const FONT = thai ? "Charmonman, Kanit, 'Noto Sans Thai', sans-serif" : "Kanit, 'Noto Sans Thai', sans-serif";
            const K = thai ? 1.18 : 1;                              // Charmonman ตัวเล็กกว่า Kanit จึงขยายขนาดให้สมดุล
            const W_TITLE = thai ? '700' : '600', W_BODY = thai ? '400' : '500';

            const c = document.createElement('canvas'); c.width = W; c.height = H;
            const g = c.getContext('2d');
            // พื้นหลังใช้ภาพเดียวกับหน้าเว็บ เพื่อให้รูปที่บันทึกดูเป็นชุดเดียวกัน
            const bg = await loadImg('../assets/images/backgrounds/river-lantern-night.webp');
            if (bg) {
                const r = Math.max(W / bg.width, H / bg.height);
                const bw = bg.width * r, bh = bg.height * r;
                g.drawImage(bg, (W - bw) / 2, (H - bh) * .48, bw, bh);
            } else {
                const sky = g.createLinearGradient(0, 0, 0, H);
                sky.addColorStop(0, '#0a0f35'); sky.addColorStop(.6, '#101a4e'); sky.addColorStop(1, '#0b2a3a');
                g.fillStyle = sky; g.fillRect(0, 0, W, H);
            }
            // เงาไล่สีให้ตัวหนังสืออ่านง่าย และกลืนกับภาพ
            const shade = g.createLinearGradient(0, 0, 0, H);
            shade.addColorStop(0, 'rgba(5,10,36,.55)'); shade.addColorStop(.3, 'rgba(5,10,36,.12)');
            shade.addColorStop(.62, 'rgba(5,12,34,.2)'); shade.addColorStop(1, 'rgba(4,10,30,.85)');
            g.fillStyle = shade; g.fillRect(0, 0, W, H);
            const glow = g.createRadialGradient(W / 2, 660, 20, W / 2, 660, 520);   // แสงเทียนรอบกระทง
            glow.addColorStop(0, 'rgba(255,200,120,.28)'); glow.addColorStop(1, 'rgba(255,200,120,0)');
            g.fillStyle = glow; g.fillRect(0, 150, W, 900);
            g.drawImage(img, (W - 840) / 2, 232, 840, 840 * (img.height / img.width || 1));   // ยกกระทงขึ้นให้อยู่กลางภาพ เหลือที่ว่างด้านล่างให้การ์ดคำอธิษฐาน

            // ---- ลวดลายไทย (วาดด้วยโค้ด): กลีบบัว และข้าวหลามตัด ----
            const GOLD = '#f0cf86';
            const petal = (x, y, len, wid, rot) => {            // กลีบบัวปลายแหลม ฐานอยู่ที่ (x,y)
                g.save(); g.translate(x, y); g.rotate(rot);
                g.beginPath(); g.moveTo(0, 0);
                g.bezierCurveTo(wid, -len * .35, wid * .8, -len * .75, 0, -len);
                g.bezierCurveTo(-wid * .8, -len * .75, -wid, -len * .35, 0, 0);
                g.closePath(); g.fill(); g.restore();
            };
            const lotus = (x, y, r) => {                          // ดอกบัว 5 กลีบ
                g.fillStyle = GOLD;
                [-70, -35, 35, 70].forEach(a => petal(x, y + r * .35, r * 1.05, r * .38, a * Math.PI / 180));
                petal(x, y + r * .35, r * 1.3, r * .45, 0);
                g.fillStyle = 'rgba(8,14,44,.9)'; g.beginPath(); g.arc(x, y + r * .35, r * .16, 0, 7); g.fill();
            };
            const prajamyam = (x, y, r) => {                      // ลายประจำยามอกลาย: ดาวสี่แฉกมีกลีบเล็กแทรกมุม ลายไทยดั้งเดิม
                g.fillStyle = GOLD;
                for (let i = 0; i < 4; i++) petal(x, y, r, r * .3, i * Math.PI / 2);
                g.fillStyle = 'rgba(240,207,134,.7)';
                for (let i = 0; i < 4; i++) petal(x, y, r * .58, r * .2, Math.PI / 4 + i * Math.PI / 2);
                g.fillStyle = 'rgba(8,14,44,.95)'; g.beginPath(); g.arc(x, y, r * .2, 0, 7); g.fill();
                g.fillStyle = GOLD; g.beginPath(); g.arc(x, y, r * .09, 0, 7); g.fill();
            };
            const diamond = (x, y, r) => { g.beginPath(); g.moveTo(x, y - r); g.lineTo(x + r, y); g.lineTo(x, y + r); g.lineTo(x - r, y); g.closePath(); g.fill(); };

            g.textAlign = 'center';
            g.shadowColor = 'rgba(0,0,20,.85)'; g.shadowBlur = 16; g.shadowOffsetY = 2;
            g.fillStyle = '#f0cf86'; g.font = `${W_BODY} ${Math.round(34 * K)}px ${FONT}`;
            g.fillText('ลอยกระทงออนไลน์', W / 2, 166);
            // ชื่อ: ลดขนาดลงอัตโนมัติถ้ายาวเกินกรอบ
            const title = `กระทงของ ${saveBtn.dataset.name || ''}`.slice(0, 40);
            let tSize = Math.round(84 * (thai ? 1.1 : 1));   // Charmonman มีหางหยักยื่นขึ้นด้านบนมาก จึงไม่ขยายมาก
            g.font = `${W_TITLE} ${tSize}px ${FONT}`;
            while (g.measureText(title).width > 620 && tSize > 40) { tSize -= 2; g.font = `${W_TITLE} ${tSize}px ${FONT}`; }
            g.shadowBlur = 28; g.shadowColor = 'rgba(255,190,100,.55)';                         // เรืองแสงอุ่นรอบชื่อ ให้เด่นขึ้น
            g.fillStyle = '#fff8e6';
            g.fillText(title, W / 2, 296);
            g.shadowBlur = 10; g.shadowColor = 'rgba(0,0,20,.8)'; g.fillText(title, W / 2, 296);
            g.shadowColor = 'transparent'; g.shadowBlur = 0;
            // กนกเปลวข้างชื่อ: หางโค้งเรียวชี้เข้าหาชื่อ ขดใบเทศตรงกลาง และเปลวสามแฉกชี้ขึ้นที่ปลายนอก (ซ้าย-ขวาสมมาตร)
            const kranokSide = (side, x, y) => {
                g.save(); g.translate(x, y); g.scale(side, 1);          // จุดเริ่ม (0,0) อยู่ด้านชื่อ ตัวลายยื่นไปทาง -x
                g.fillStyle = GOLD; g.strokeStyle = GOLD; g.lineCap = 'round'; g.lineJoin = 'round';
                // หางเรียว: ปลายแหลมด้านชื่อ ค่อยๆ หนาขึ้นแล้วโค้งขึ้นไปหาขด
                g.beginPath(); g.moveTo(0, 2);
                g.bezierCurveTo(-34, -4, -76, 0, -112, -18);
                g.bezierCurveTo(-136, -30, -150, -16, -146, 2);
                g.bezierCurveTo(-128, -12, -110, -12, -96, -6);
                g.bezierCurveTo(-66, 8, -30, 9, 0, 2);
                g.fill();
                // แขนเปลวใหญ่: จากหางโค้งขึ้นไปสะบัดเป็นปลายแหลมชี้ขึ้นทางด้านนอก
                g.beginPath(); g.moveTo(-70, -2);
                g.bezierCurveTo(-110, -8, -146, -22, -142, -52);
                g.bezierCurveTo(-140, -64, -146, -72, -156, -84);
                g.bezierCurveTo(-130, -78, -116, -60, -122, -40);
                g.bezierCurveTo(-124, -26, -100, -16, -70, -2);
                g.fill();
                // ขดใบเทศใหญ่ ขดเข้าหาศูนย์กลาง
                const cx = -92, cy = -32;
                g.beginPath();
                for (let k = 0; k <= 52; k++) { const ang = Math.PI * .9 + k * .16, rr = 28 * (1 - k / 62); const px = cx + Math.cos(ang) * rr, py = cy + Math.sin(ang) * rr; k ? g.lineTo(px, py) : g.moveTo(px, py); }
                g.lineWidth = 6.5; g.stroke();
                g.beginPath(); g.arc(cx, cy, 4.5, 0, 7); g.fill();
                // เปลวเรียวชี้ขึ้นเหนือขด เอนไปทางปลายนอก
                petal(-86, -54, 44, 8, -8 * Math.PI / 180);
                petal(-106, -50, 40, 7, -22 * Math.PI / 180);
                petal(-70, -44, 32, 7, 14 * Math.PI / 180);
                g.restore();
            };
            const half = g.measureText(title).width / 2 + 16;   // วางไว้ข้างชื่อ ห่างจากปลายตัวอักษร 16px ตามความยาวชื่อจริง
            g.shadowColor = 'rgba(255,190,100,.5)'; g.shadowBlur = 12;
            const orn = await loadImg('../assets/images/kranok-side.png');   // ลายกนกเปลวจากภาพต้นแบบ (ซ้าย) ใช้กลับด้านเป็นฝั่งขวา; ถ้าโหลดไม่ได้ใช้ลายที่วาดด้วยโค้ดแทน
            if (orn) {
                const ow = 160, oh = Math.round(160 * orn.height / orn.width), oy = 302 - oh;   // ปลายหางอยู่ระดับเส้นฐานของชื่อ
                g.drawImage(orn, W / 2 - half - ow, oy, ow, oh);
                g.save(); g.translate(W / 2 + half, 0); g.scale(-1, 1); g.drawImage(orn, -ow, oy, ow, oh); g.restore();
            } else { kranokSide(1, W / 2 - half, 292); kranokSide(-1, W / 2 + half, 292); }
            g.shadowColor = 'transparent'; g.shadowBlur = 0;

            // คำอธิษฐาน: อยู่ในการ์ดโปร่งแสงสีเข้มขอบทอง ให้เด่นแยกจากพื้นหลัง ตัวอักษรหนาขึ้น ลดขนาดอัตโนมัติถ้าข้อความยาว
            const prayer = (saveBtn.dataset.prayer || '').trim();
            // ตัดบรรทัดตามคำ (Intl.Segmenter ภาษาไทย) ไม่ให้คำขาดกลางคำ; ถ้าเบราว์เซอร์ไม่รองรับจะตัดทีละตัวอักษร
            let words;
            try { words = [...new Intl.Segmenter('th', { granularity: 'word' }).segment(prayer)].map(x => x.segment); }
            catch (e) { words = [...prayer]; }
            const TEXT_W = 700;                                      // ความกว้างข้อความ (การ์ดกว้าง 840 เหลือขอบในซ้าย-ขวาข้างละ 70)
            const wrap = (size) => {
                g.font = `${W_BODY} ${size}px ${FONT}`;
                const out = []; let line = '';
                for (const w of words) {
                    if (g.measureText(line + w).width <= TEXT_W) { line += w; continue; }
                    if (line.trim()) out.push(line.trim());
                    line = '';
                    if (g.measureText(w).width > TEXT_W) {           // คำเดียวยาวเกินบรรทัด: ตัดทีละตัวอักษร
                        for (const ch of w) { if (g.measureText(line + ch).width > TEXT_W) { out.push(line); line = ch; } else line += ch; }
                    } else line = w.trimStart();
                }
                if (line.trim()) out.push(line.trim());
                return out;
            };
            const MAX_INNER = 200;                                   // ความสูงข้อความสูงสุดในการ์ด
            let size = Math.round(44 * K), lh = 64, lines = wrap(size);
            for (const sz of [44, 40, 36, 32, 28, 25].map(v => Math.round(v * K))) {
                size = sz; lh = Math.round(sz * (thai ? 1.55 : 1.5)); lines = wrap(sz);
                if (lines.length * lh <= MAX_INNER) break;
            }
            const maxLines = Math.max(1, Math.floor(MAX_INNER / lh));
            if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1].slice(0, -1) + '…'; }
            if (lines.length) {
                const padY = 54, cardH = lines.length * lh + padY * 2, cardW = 840, cardX = (W - cardW) / 2, cardB = 1250, cardY = cardB - cardH;   // เว้นขอบล่าง 110px ขอบข้าง 120px
                g.save();
                g.shadowColor = 'rgba(0,0,10,.55)'; g.shadowBlur = 40; g.shadowOffsetY = 10;
                g.beginPath(); g.roundRect ? g.roundRect(cardX, cardY, cardW, cardH, 36) : g.rect(cardX, cardY, cardW, cardH);
                g.fillStyle = 'rgba(6,14,42,.8)'; g.fill();
                g.restore();
                g.lineWidth = 3; g.strokeStyle = 'rgba(240,207,134,.9)'; g.stroke();
                g.beginPath(); g.roundRect ? g.roundRect(cardX + 12, cardY + 12, cardW - 24, cardH - 24, 26) : g.rect(cardX + 12, cardY + 12, cardW - 24, cardH - 24);
                g.lineWidth = 1.2; g.strokeStyle = 'rgba(240,207,134,.45)'; g.stroke();   // เส้นกรอบชั้นใน
                // กนกเปลวที่มุมทั้งสี่ และดอกบัวกลางขอบบน/ล่าง
                g.fillStyle = GOLD;
                [[cardX + 24, cardY + 24], [cardX + cardW - 24, cardY + 24], [cardX + 24, cardY + cardH - 24], [cardX + cardW - 24, cardY + cardH - 24]].forEach(([x, y]) => {
                    diamond(x, y, 7);
                });
                g.save(); g.shadowColor = 'rgba(0,0,10,.6)'; g.shadowBlur = 8;
                g.fillStyle = 'rgba(6,14,42,1)'; g.fillRect(W / 2 - 46, cardY - 3, 92, 6);   // เว้นช่องเส้นขอบให้ดอกบัว
                g.restore();
                lotus(W / 2, cardY - 8, 22);
                g.font = `${W_BODY} ${size}px ${FONT}`; g.fillStyle = '#fffaf0';
                g.shadowColor = 'rgba(0,0,20,.6)'; g.shadowBlur = 6; g.shadowOffsetY = 1;
                // จัดข้อความให้อยู่กึ่งกลางการ์ดตามขนาดจริงของฟอนต์ (วัดความสูงตัวอักษร) ระยะบน-ล่างจึงเท่ากันไม่ว่าฟอนต์ไหน
                const mm = g.measureText('ขอให้กี่ทัง'), asc = mm.actualBoundingBoxAscent || size * .8, dsc = mm.actualBoundingBoxDescent || size * .25;
                const y0 = cardY + (cardH - ((lines.length - 1) * lh + asc + dsc)) / 2 + asc;
                lines.forEach((l, i) => g.fillText(l, W / 2, y0 + lh * i));
                g.shadowColor = 'transparent'; g.shadowBlur = 0; g.shadowOffsetY = 0;
            }
            // ---- ข้อความท้ายภาพ ----
            g.fillStyle = GOLD; g.font = `${W_BODY} ${Math.round(30 * K)}px ${FONT}`; g.textAlign = 'center';
            g.shadowColor = 'rgba(0,0,20,.85)'; g.shadowBlur = 10;
            g.fillText('วันเพ็ญเดือนสิบสอง', W / 2, 1306);
            g.shadowColor = 'transparent'; g.shadowBlur = 0;
            const tw = g.measureText('วันเพ็ญเดือนสิบสอง').width / 2;
            [-1, 1].forEach(d => { g.fillStyle = GOLD; diamond(W / 2 + d * (tw + 26), 1296, 5); g.fillRect(W / 2 + d * (tw + 40) - (d < 0 ? 60 : 0), 1295, 60, 2); });
            URL.revokeObjectURL(svgUrl);
            const blob = await new Promise(r => c.toBlob(r, 'image/png'));
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = `krathong-${Date.now()}.png`;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        } catch (e) {
            if (note) { note.textContent = 'บันทึกรูปไม่สำเร็จ ลองแคปหน้าจอแทนได้'; note.hidden = false; }
        } finally { saveBtn.disabled = false; saveBtn.innerHTML = label; }
    });
})();
