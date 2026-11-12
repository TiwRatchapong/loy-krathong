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
            if (document.fonts?.load) { try { await document.fonts.load('500 40px Kanit'); } catch (e) { /* ใช้ฟอนต์สำรอง */ } }

            const c = document.createElement('canvas'); c.width = W; c.height = H;
            const g = c.getContext('2d');
            const sky = g.createLinearGradient(0, 0, 0, H);
            sky.addColorStop(0, '#07131f'); sky.addColorStop(.55, '#0f2a3a'); sky.addColorStop(1, '#0b2a26');
            g.fillStyle = sky; g.fillRect(0, 0, W, H);
            for (let i = 0; i < 70; i++) {   // ดาว
                g.fillStyle = `rgba(255,240,200,${.15 + Math.random() * .5})`;
                g.beginPath(); g.arc(Math.random() * W, Math.random() * H * .6, Math.random() * 2 + .4, 0, 7); g.fill();
            }
            const moon = g.createRadialGradient(W * .8, 170, 10, W * .8, 170, 130);
            moon.addColorStop(0, 'rgba(255,236,170,.95)'); moon.addColorStop(.45, 'rgba(255,226,150,.35)'); moon.addColorStop(1, 'rgba(255,226,150,0)');
            g.fillStyle = moon; g.fillRect(0, 0, W, 420);
            g.fillStyle = 'rgba(30,70,95,.55)'; g.fillRect(0, 760, W, H - 760);   // ผืนน้ำ
            g.drawImage(img, (W - 900) / 2, 330, 900, 900 * (img.height / img.width || 1));

            g.textAlign = 'center';
            g.fillStyle = '#e8c77e'; g.font = '500 30px Kanit, sans-serif';
            g.fillText('ลอยกระทงออนไลน์', W / 2, 90);
            g.fillStyle = '#fff5df'; g.font = '600 56px Kanit, sans-serif';
            g.fillText(`กระทงของ ${saveBtn.dataset.name}`.slice(0, 40), W / 2, 200);
            g.font = '400 34px Kanit, sans-serif'; g.fillStyle = 'rgba(255,245,223,.9)';
            const words = [...(saveBtn.dataset.prayer || '')];   // ตัดบรรทัดทีละตัวอักษร (ภาษาไทยไม่มีวรรค)
            const lines = []; let line = '';
            words.forEach(ch => { if (g.measureText(line + ch).width > 880) { lines.push(line); line = ch; } else line += ch; });
            if (line) lines.push(line);
            // คำอธิษฐานอยู่ใต้ภาพกระทง
            const top = 1190;
            lines.slice(0, 4).forEach((l, i) => { g.fillText(i === 3 && lines.length > 4 ? l.slice(0, -1) + '…' : l, W / 2, top + i * 48); });
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
