/*
 * ระบบเสียงของเว็บลอยกระทง — เพลงพื้นหลังเป็นไฟล์ mp3 ส่วนบรรยากาศและเอฟเฟคสังเคราะห์ด้วย Web Audio API
 *
 *  • เพลง      ไฟล์ assets/audio/background-music.mp3 เล่นวนซ้ำ (จำตำแหน่งข้ามหน้า)
 *  • บรรยากาศ  สายน้ำ ลมกลางคืน จิ้งหรีด ระฆังวัดไกล ๆ และเสียงพลุไกล ๆ
 *  • เอฟเฟค    คลิก เลือก เปลี่ยนขั้นตอน ปล่อยกระทงลงน้ำ โคมลอย เปิดคำอธิษฐาน ฯลฯ
 *
 * เบราว์เซอร์ไม่อนุญาตให้เล่นเสียงเองก่อนผู้ใช้แตะหน้าจอ จึงเริ่มต้น "ปิดเสียง"
 * ผู้ใช้กดปุ่มลำโพงมุมจอเพื่อเปิด การตั้งค่าจะจำไว้ในเครื่อง (localStorage)
 *
 * เหตุการณ์ในหน้าเว็บ (เปลี่ยนขั้นตอน ปล่อยกระทง ฯลฯ) จับด้วย event / MutationObserver
 * จึงไม่ต้องแก้สคริปต์เดิม เรียกเสียงเองได้ด้วย window.KrathongAudio.play('splash')
 */
(() => {
    'use strict';
    if (window.KrathongAudio) return;
    const SCRIPT_SRC = document.currentScript && document.currentScript.src;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;

    /* ---------------- การตั้งค่า (จำไว้ในเครื่อง) ---------------- */
    const STORE = 'krathong-audio-v1';
    const st = { on: false, music: 0.7, ambience: 0.8, sfx: 0.8, chosen: false };
    try { Object.assign(st, JSON.parse(localStorage.getItem(STORE) || '{}')); } catch (e) { /* ใช้ค่าเริ่มต้น */ }
    const save = () => { try { localStorage.setItem(STORE, JSON.stringify(st)); } catch (e) { /* ไม่เป็นไร */ } };
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const rand = (a, b) => a + Math.random() * (b - a);
    const pick = arr => arr[Math.floor(Math.random() * arr.length)];
    for (const k of ['music', 'ambience', 'sfx']) st[k] = clamp(Number(st[k]) || 0, 0, 1);

    /* ---------------- กราฟเสียง ---------------- */
    let ctx = null, master, musicGain, ambGain, sfxGain, revIn, pinkBuf, brownBuf;
    let ready = false, running = false;           // ready = สร้างกราฟแล้ว, running = กำลังเปิดเสียงอยู่
    const loops = new Set();                       // ตัวจับเวลาของเสียงสุ่ม (หยุดเมื่อปิดเสียง)
    const vol = v => v * v;                        // ให้แถบเลื่อนฟังเป็นเส้นตรงขึ้น

    function makeNoise(kind) {
        const len = ctx.sampleRate * 2;                  // 2 วินาทีพอ (เล่นวนซ้ำ) ลดงานบน main thread ตอนกดเปิดเสียง
        const buf = ctx.createBuffer(2, len, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = buf.getChannelData(c);
            let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0, last = 0;
            for (let i = 0; i < len; i++) {
                const w = Math.random() * 2 - 1;
                if (kind === 'brown') {
                    last = (last + 0.02 * w) / 1.02; d[i] = last * 3.5;
                } else {                           // pink (Paul Kellet)
                    b0 = 0.99886 * b0 + w * 0.0555179; b1 = 0.99332 * b1 + w * 0.0750759;
                    b2 = 0.969 * b2 + w * 0.153852;    b3 = 0.8665 * b3 + w * 0.3104856;
                    b4 = 0.55 * b4 + w * 0.5329522;    b5 = -0.7616 * b5 - w * 0.016898;
                    d[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + w * 0.5362) * 0.11; b6 = w * 0.115926;
                }
            }
        }
        return buf;
    }

    function makeReverb(seconds = 2.2) {
        const len = Math.floor(ctx.sampleRate * seconds);
        const buf = ctx.createBuffer(2, len, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = buf.getChannelData(c);
            let lp = 0;
            for (let i = 0; i < len; i++) {
                lp += ((Math.random() * 2 - 1) - lp) * 0.55;          // ตัดเสียงแหลมให้ห้องนุ่ม
                d[i] = lp * Math.pow(1 - i / len, 2.6);
            }
        }
        const conv = ctx.createConvolver();
        conv.buffer = buf;
        return conv;
    }

    function build() {
        ctx = new AC();
        const comp = ctx.createDynamicsCompressor();
        comp.threshold.value = -16; comp.ratio.value = 4; comp.attack.value = 0.01; comp.release.value = 0.3;
        master = ctx.createGain(); master.gain.value = 0;
        master.connect(comp); comp.connect(ctx.destination);

        musicGain = ctx.createGain(); ambGain = ctx.createGain(); sfxGain = ctx.createGain();
        [musicGain, ambGain, sfxGain].forEach(g => g.connect(master));
        applyVolumes();

        // รีเวิร์บร่วม (ใช้กับเพลง ระฆัง และเอฟเฟคส่วนใหญ่)
        revIn = ctx.createGain();
        const rev = makeReverb();
        const revOut = ctx.createGain(); revOut.gain.value = 0.9;
        revIn.connect(rev); rev.connect(revOut); revOut.connect(master);

        pinkBuf = makeNoise('pink');
        brownBuf = makeNoise('brown');
        buildAmbience();
        ready = true;
    }

    function applyVolumes() {
        if (!ctx) return;
        const t = ctx.currentTime;
        musicGain.gain.setTargetAtTime(vol(st.music) * 0.5, t, 0.08);   // ไฟล์เพลงมาสเตอร์มาดัง (~ -14 LUFS) จึงลดให้พอดีกับเสียงบรรยากาศ
        ambGain.gain.setTargetAtTime(vol(st.ambience) * 1.8, t, 0.08);   // ให้ได้ยินชัดใต้เพลง
        sfxGain.gain.setTargetAtTime(vol(st.sfx) * 2.2, t, 0.05);
    }

    // ส่งเสียงเข้าบัส (+ ส่งบางส่วนเข้ารีเวิร์บ)
    function route(node, bus, reverb = 0.25, pan = 0) {
        let out = node;
        if (pan && ctx.createStereoPanner) { const p = ctx.createStereoPanner(); p.pan.value = pan; node.connect(p); out = p; }
        out.connect(bus);
        if (reverb > 0) { const s = ctx.createGain(); s.gain.value = reverb; out.connect(s); s.connect(revIn); }
    }

    /* ---------------- ตัวสร้างเสียงพื้นฐาน ---------------- */
    const SCALE = [261.63, 293.66, 329.63, 392.0, 440.0];           // โด เร มี ซอล ลา (เพนทาโทนิก)
    const noteFreq = i => SCALE[((i % 5) + 5) % 5] * Math.pow(2, Math.floor(i / 5));

    // เสียงดีด/ตี คล้ายระนาดเอก-ขิม: ฮาร์โมนิกส์ 3 ตัว ตกเร็ว
    function pluck(bus, t, f, { dur = 1.8, vel = 0.2, bright = 1, reverb = 0.35, pan = 0 } = {}) {
        const g = ctx.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.linearRampToValueAtTime(vel, t + 0.006);
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        const lp = ctx.createBiquadFilter(); lp.type = 'lowpass';
        lp.frequency.setValueAtTime(4200 * bright, t);
        lp.frequency.exponentialRampToValueAtTime(900, t + dur * 0.7);
        for (const [ratio, amp, type] of [[1, 1, 'triangle'], [2, 0.32, 'sine'], [4.07, 0.1, 'sine']]) {
            const o = ctx.createOscillator(), og = ctx.createGain();
            o.type = type; o.frequency.value = f * ratio; o.detune.value = rand(-4, 4);
            og.gain.value = amp;
            o.connect(og); og.connect(g);
            o.start(t); o.stop(t + dur + 0.1);
        }
        g.connect(lp);
        route(lp, bus, reverb, pan);
    }

    // ระฆัง/ฆ้องวัด: ฮาร์โมนิกส์ไม่เป็นจำนวนเต็ม ตกช้า
    function bell(bus, t, f = 196, { vel = 0.16, dur = 5, reverb = 0.6, pan = 0 } = {}) {
        const g = ctx.createGain(); g.gain.value = 1;
        [[1, 1], [2.0, 0.55], [2.76, 0.4], [5.4, 0.18], [8.93, 0.08]].forEach(([r, a], i) => {
            const o = ctx.createOscillator(), og = ctx.createGain();
            o.type = 'sine'; o.frequency.value = f * r;
            og.gain.setValueAtTime(0.0001, t);
            og.gain.linearRampToValueAtTime(vel * a, t + 0.004);
            og.gain.exponentialRampToValueAtTime(0.0001, t + dur / (1 + i * 0.6));
            o.connect(og); og.connect(g);
            o.start(t); o.stop(t + dur + 0.1);
        });
        route(g, bus, reverb, pan);
    }

    // เสียงลม/น้ำสั้น ๆ: นอยส์ผ่านฟิลเตอร์ที่กวาดความถี่
    function noiseBurst(bus, t, { from = 1800, to = 500, dur = 0.5, vel = 0.3, q = 0.9, attack = 0.02, reverb = 0.2, buf = null } = {}) {
        const s = ctx.createBufferSource(); s.buffer = buf || pinkBuf;
        const bp = ctx.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = q;
        bp.frequency.setValueAtTime(from, t); bp.frequency.exponentialRampToValueAtTime(to, t + dur);
        const g = ctx.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.linearRampToValueAtTime(vel, t + attack);
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        s.connect(bp); bp.connect(g);
        s.start(t, rand(0, 1.2)); s.stop(t + dur + 0.05);
        route(g, bus, reverb);
    }

    function droplet(bus, t, vel = 0.1) {
        const o = ctx.createOscillator(), g = ctx.createGain();
        const f = rand(900, 2200);
        o.type = 'sine'; o.frequency.setValueAtTime(f, t); o.frequency.exponentialRampToValueAtTime(f * 1.9, t + 0.05);
        g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(vel, t + 0.005);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.09);
        o.connect(g); o.start(t); o.stop(t + 0.12);
        route(g, bus, 0.3, rand(-0.5, 0.5));
    }

    function thump(bus, t, f0 = 170, f1 = 55, vel = 0.35, dur = 0.16) {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'sine'; o.frequency.setValueAtTime(f0, t); o.frequency.exponentialRampToValueAtTime(f1, t + dur);
        g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(vel, t + 0.008);
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur + 0.05);
        o.connect(g); o.start(t); o.stop(t + dur + 0.1);
        route(g, bus, 0.1);
    }

    /* ---------------- เอฟเฟค ---------------- */
    const SFX = {
        tick(t) {                                       // แตะปุ่มทั่วไป: เสียงไม้เบา ๆ
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'triangle'; o.frequency.setValueAtTime(1300, t); o.frequency.exponentialRampToValueAtTime(620, t + 0.05);
            g.gain.setValueAtTime(0.0001, t); g.gain.linearRampToValueAtTime(0.16, t + 0.004);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.09);
            o.connect(g); o.start(t); o.stop(t + 0.12);
            route(g, sfxGain, 0.12);
        },
        select(t) {                                     // เลือกกระทง/ดอกไม้/คำอธิษฐาน
            pluck(sfxGain, t, noteFreq(pick([5, 6, 7, 8, 9])), { dur: 0.9, vel: 0.2, reverb: 0.4 });
        },
        next(t, step = 0) {                             // ไปขั้นต่อไป: ไล่ขึ้น 2 โน้ต สูงขึ้นตามขั้น
            const i = 4 + clamp(step, 0, 5);
            pluck(sfxGain, t, noteFreq(i), { dur: 1.0, vel: 0.2 });
            pluck(sfxGain, t + 0.11, noteFreq(i + 2), { dur: 1.3, vel: 0.2 });
        },
        back(t) {
            pluck(sfxGain, t, noteFreq(8), { dur: 0.8, vel: 0.15, bright: 0.7 });
            pluck(sfxGain, t + 0.1, noteFreq(5), { dur: 1.0, vel: 0.15, bright: 0.7 });
        },
        error(t) {                                      // พบคำไม่เหมาะสม: ตุ้บเบา ๆ สองครั้ง
            thump(sfxGain, t, 240, 150, 0.28, 0.12);
            thump(sfxGain, t + 0.15, 210, 130, 0.28, 0.14);
        },
        chime(t) {                                      // เปิดอ่านคำอธิษฐาน
            [7, 9, 12].forEach((n, i) => pluck(sfxGain, t + i * 0.09, noteFreq(n), { dur: 1.8, vel: 0.14, reverb: 0.55 }));
        },
        ding(t) {                                       // คัดลอกลิงก์สำเร็จ
            pluck(sfxGain, t, noteFreq(12), { dur: 0.9, vel: 0.16 });
            pluck(sfxGain, t + 0.08, noteFreq(14), { dur: 1.1, vel: 0.16 });
        },
        on(t) {                                         // ยืนยันตอนเปิดเสียง
            [5, 7, 9].forEach((n, i) => pluck(sfxGain, t + i * 0.12, noteFreq(n), { dur: 1.6, vel: 0.17, reverb: 0.5 }));
        },
        whoosh(t) {                                     // กระทง/โคมเคลื่อนตัวออก
            noiseBurst(sfxGain, t, { from: 300, to: 1500, dur: 0.9, vel: 0.12, q: 0.7, attack: 0.45, reverb: 0.3 });
        },
        lantern(t) {                                    // โคมลอยขึ้นฟ้า (เบามาก)
            noiseBurst(sfxGain, t, { from: 250, to: 900, dur: 1.4, vel: 0.05, q: 0.6, attack: 0.7, reverb: 0.5 });
            pluck(sfxGain, t + 0.2, noteFreq(pick([9, 10, 11])), { dur: 2.2, vel: 0.06, reverb: 0.7, pan: rand(-0.6, 0.6) });
        },
        splash(t) {                                     // กระทงตกลงน้ำ
            thump(sfxGain, t, 190, 60, 0.3, 0.14);
            noiseBurst(sfxGain, t, { from: 2200, to: 450, dur: 0.55, vel: 0.32, q: 0.8, attack: 0.012, reverb: 0.3 });
            for (let i = 0; i < 7; i++) droplet(sfxGain, t + 0.05 + Math.random() * 0.6, rand(0.05, 0.13));
        },
        release(t) {                                    // กดปล่อยกระทง
            SFX.whoosh(t);
            SFX.splash(t + 0.25);
        },
        success(t) {                                    // กระทงลอยแล้ว: ระฆังกับประกายเสียง
            bell(sfxGain, t, 196, { vel: 0.15, dur: 4.5 });
            [9, 11, 12, 14, 16].forEach((n, i) => pluck(sfxGain, t + 0.15 + i * 0.13, noteFreq(n), { dur: 2.4, vel: 0.1, reverb: 0.7, pan: (i - 2) * 0.25 }));
        },
    };

    function play(name, ...args) {
        if (!st.on || !running || !ctx || ctx.state !== 'running' || !SFX[name]) return;
        try { SFX[name](ctx.currentTime + 0.01, ...args); } catch (e) { /* เสียงล้มเหลวต้องไม่กระทบหน้าเว็บ */ }
        document.dispatchEvent(new CustomEvent('krathong:sfx', { detail: name }));   // ให้สคริปต์อื่น/ตัวทดสอบรู้ว่าเล่นเสียงอะไร
    }

    /* ---------------- บรรยากาศ ---------------- */
    function lfo(param, freq, depth) {                 // ให้ค่าพารามิเตอร์แกว่งช้า ๆ
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = freq; g.gain.value = depth;
        o.connect(g); g.connect(param); o.start();
    }

    function loopSource(buf) {
        const s = ctx.createBufferSource(); s.buffer = buf; s.loop = true;
        s.start(0, rand(0, 1.5)); return s;
    }

    function buildAmbience() {
        // สายน้ำ: ตัวน้ำ (เสียงต่ำ) + ผิวน้ำ (กลาง) + ประกายน้ำ (สูง) แกว่งช้า ๆ ให้เหมือนคลื่น
        const water = ctx.createGain(); water.gain.value = 0.5; water.connect(ambGain);
        const body = ctx.createBiquadFilter(); body.type = 'lowpass'; body.frequency.value = 260;
        const bodyG = ctx.createGain(); bodyG.gain.value = 0.55;
        loopSource(brownBuf).connect(body); body.connect(bodyG); bodyG.connect(water);
        lfo(bodyG.gain, 0.11, 0.18);

        const mid = ctx.createBiquadFilter(); mid.type = 'bandpass'; mid.frequency.value = 520; mid.Q.value = 0.5;
        const midG = ctx.createGain(); midG.gain.value = 0.35;
        loopSource(pinkBuf).connect(mid); mid.connect(midG); midG.connect(water);
        lfo(midG.gain, 0.17, 0.12); lfo(mid.frequency, 0.07, 140);

        const hi = ctx.createBiquadFilter(); hi.type = 'bandpass'; hi.frequency.value = 1900; hi.Q.value = 1.1;
        const hiG = ctx.createGain(); hiG.gain.value = 0.05;
        loopSource(pinkBuf).connect(hi); hi.connect(hiG); hiG.connect(water);
        lfo(hiG.gain, 0.43, 0.03); lfo(hi.frequency, 0.21, 380);

        // ลมกลางคืน
        const wind = ctx.createBiquadFilter(); wind.type = 'bandpass'; wind.frequency.value = 700; wind.Q.value = 0.8;
        const windG = ctx.createGain(); windG.gain.value = 0.09;
        loopSource(pinkBuf).connect(wind); wind.connect(windG); windG.connect(ambGain);
        lfo(windG.gain, 0.05, 0.07); lfo(wind.frequency, 0.04, 260);
    }

    // เสียงสุ่มที่ทำงานเป็นช่วง ๆ (ทำงานเฉพาะตอนเปิดเสียง)
    function every(minMs, maxMs, fn, first = true) {
        const entry = { id: 0 };
        const fire = () => {
            if (!running) return;
            if (ctx.state === 'running') { try { fn(ctx.currentTime + 0.02); } catch (e) { /* ข้าม */ } }
            entry.id = setTimeout(fire, rand(minMs, maxMs));
        };
        entry.id = setTimeout(fire, first ? rand(300, minMs) : rand(minMs, maxMs));
        loops.add(entry);
    }
    const stopLoops = () => { loops.forEach(e => clearTimeout(e.id)); loops.clear(); };

    function cricketChirp(t, f, vel, pan) {
        const pulses = 3 + Math.floor(Math.random() * 2);
        for (let i = 0; i < pulses; i++) {
            const s = t + i * 0.058;
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = f;
            g.gain.setValueAtTime(0.0001, s); g.gain.linearRampToValueAtTime(vel, s + 0.012);
            g.gain.exponentialRampToValueAtTime(0.0001, s + 0.04);
            o.connect(g); o.start(s); o.stop(s + 0.06);
            route(g, ambGain, 0.12, pan);
        }
    }

    function startAmbienceLoops() {
        // จิ้งหรีด 3 ตัว คนละระดับเสียงและตำแหน่งซ้าย-ขวา
        [[4350, -0.7, 1.1, 2.4], [4900, 0.6, 1.4, 3.0], [5300, 0.1, 1.9, 4.2]].forEach(([f, pan, a, b]) =>
            every(a * 1000, b * 1000, t => cricketChirp(t, f * rand(0.98, 1.02), 0.022, pan)));
        // คลื่นน้ำกระเพื่อมเป็นระยะ
        every(900, 2600, t => noiseBurst(ambGain, t, { from: rand(500, 900), to: rand(1100, 1700), dur: rand(0.25, 0.5), vel: 0.09, q: 1.6, attack: 0.12, reverb: 0.3 }));
        // ระฆังวัดไกล ๆ
        every(22000, 42000, t => bell(ambGain, t, pick([147, 165, 196]), { vel: 0.05, dur: 6, reverb: 0.9, pan: rand(-0.5, 0.5) }), false);
        // พลุไกล ๆ
        every(26000, 52000, t => {
            noiseBurst(ambGain, t, { from: 900, to: 90, dur: 1.2, vel: 0.18, q: 0.5, attack: 0.01, reverb: 0.8, buf: brownBuf });
            thump(ambGain, t, 90, 40, 0.16, 0.5);
            for (let i = 0; i < 6; i++) noiseBurst(ambGain, t + 0.5 + Math.random() * 1.2, { from: 3000, to: 1500, dur: 0.06, vel: 0.03, q: 2, attack: 0.004, reverb: 0.6 });
        }, false);
    }

    /* ---------------- เพลง (ไฟล์ assets/audio/background-music.mp3) ---------------- */
    // เล่นวนซ้ำผ่านบัสเพลง จึงปรับความดังด้วยแถบเลื่อนเดียวกับเพลงเดิมได้
    // เว็บเป็นหลายหน้า: จำตำแหน่งที่เล่นค้างไว้ใน sessionStorage แล้วเล่นต่อเมื่อเปลี่ยนหน้า
    const MUSIC_URL = SCRIPT_SRC ? new URL('../audio/background-music.mp3', SCRIPT_SRC).href : '../assets/audio/background-music.mp3';
    const POS_KEY = 'krathong-music-pos';
    let track = null, posTimer = 0;

    function savePos() {
        if (!track || !isFinite(track.currentTime)) return;
        try { sessionStorage.setItem(POS_KEY, JSON.stringify({ pos: track.currentTime, at: Date.now() })); } catch (e) { /* ไม่เป็นไร */ }
    }

    function ensureTrack() {
        if (track) return track;
        track = new Audio(MUSIC_URL);
        track.loop = true;
        track.preload = 'auto';
        let resumeAt = 0;
        try {
            const saved = JSON.parse(sessionStorage.getItem(POS_KEY) || 'null');
            if (saved && isFinite(saved.pos)) resumeAt = saved.pos + Math.min(6, Math.max(0, (Date.now() - saved.at) / 1000));
        } catch (e) { /* เริ่มจากต้นเพลง */ }
        if (resumeAt > 0) {
            track.addEventListener('loadedmetadata', () => {
                if (isFinite(track.duration) && track.duration > 0) track.currentTime = resumeAt % track.duration;
            }, { once: true });
        }
        ctx.createMediaElementSource(track).connect(musicGain);
        window.addEventListener('pagehide', savePos);
        return track;
    }

    function musicPlay() {
        if (!running || !ctx) return;
        const t = ensureTrack();
        const p = t.play();
        if (p && p.catch) p.catch(() => { /* ยังไม่ได้รับอนุญาต: รอผู้ใช้แตะครั้งแรก (bindGestureResume) */ });
        clearInterval(posTimer);
        posTimer = setInterval(savePos, 1000);
    }

    function musicPause() {
        clearInterval(posTimer);
        if (track) { savePos(); track.pause(); }
    }

    /* ---------------- เปิด / ปิด ---------------- */
    let gestureBound = false;

    function bindGestureResume() {                      // เบราว์เซอร์ยังไม่ให้เล่น → รอผู้ใช้แตะครั้งแรกแล้วเริ่มให้
        if (gestureBound) return;
        gestureBound = true;
        const go = () => {
            ['pointerdown', 'keydown', 'touchend'].forEach(ev => document.removeEventListener(ev, go, true));
            gestureBound = false;
            if (st.on && ctx) ctx.resume().then(() => { musicPlay(); refreshUi(); }).catch(() => {});
        };
        ['pointerdown', 'keydown', 'touchend'].forEach(ev => document.addEventListener(ev, go, true));
    }

    async function start(userGesture = false) {
        if (!ready) build();
        running = true;
        const resumed = ctx.resume();                   // ต้องเรียกพร้อมกับการแตะ (Safari/iOS)
        const ok = await Promise.race([resumed.then(() => true, () => false), new Promise(r => setTimeout(() => r(false), 350))]);
        if (!running) return;                           // ผู้ใช้ปิดระหว่างรอ
        const t = ctx.currentTime;
        master.gain.cancelScheduledValues(t);
        master.gain.setTargetAtTime(0.9, t, 0.6);       // ค่อย ๆ ดังขึ้น
        applyVolumes();
        stopLoops();
        startAmbienceLoops();
        musicPlay();
        if (ctx.state !== 'running') bindGestureResume();
        else if (userGesture) play('on');
        refreshUi();
        return ok;
    }

    function stop() {
        running = false;
        stopLoops();
        if (!ctx) return;
        const t = ctx.currentTime;
        master.gain.cancelScheduledValues(t);
        master.gain.setTargetAtTime(0, t, 0.15);
        setTimeout(() => { if (!running) { musicPause(); if (ctx.state === 'running') ctx.suspend().catch(() => {}); } }, 900);
    }

    function setOn(on, userGesture = false) {
        st.on = on; st.chosen = true; save();
        if (on) start(userGesture); else stop();
        refreshUi();
    }

    document.addEventListener('visibilitychange', () => {   // ซ่อนแท็บ → พักเสียงเพื่อประหยัดแบตเตอรี่
        if (!ctx || !st.on) return;
        if (document.hidden) { if (track) track.pause(); ctx.suspend().catch(() => {}); }
        else { ctx.resume().then(() => { musicPlay(); refreshUi(); }).catch(() => {}); }
    });

    /* ---------------- ปุ่มควบคุม ---------------- */
    const ICON_ON = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.6L12 18.5v-13L7.6 9.5H4z" fill="currentColor"/><path d="M15.2 9.2a4 4 0 0 1 0 5.6M17.6 6.8a7.4 7.4 0 0 1 0 10.4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    const ICON_OFF = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5v5h3.6L12 18.5v-13L7.6 9.5H4z" fill="currentColor"/><path d="M16 9.5l5 5M21 9.5l-5 5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    const ICON_SET = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h9M18 7h1M5 17h1M10 17h9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="16" cy="7" r="2.1" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="8" cy="17" r="2.1" fill="none" stroke="currentColor" stroke-width="1.7"/></svg>';

    let ui = null;

    function buildUi() {
        const box = document.createElement('div');
        box.className = 'kt-audio';
        box.id = 'ktAudio';
        box.innerHTML = `
            <div class="kt-audio-panel" id="ktAudioPanel" role="group" aria-label="ตั้งค่าเสียง" hidden>
                <p class="kt-audio-title">เสียงบรรยากาศ</p>
                <label class="kt-audio-row"><span>เพลง</span><input type="range" min="0" max="100" step="1" data-k="music" aria-label="ระดับเสียงเพลง"></label>
                <label class="kt-audio-row"><span>บรรยากาศ</span><input type="range" min="0" max="100" step="1" data-k="ambience" aria-label="ระดับเสียงบรรยากาศ สายน้ำ จิ้งหรีด"></label>
                <label class="kt-audio-row"><span>เอฟเฟค</span><input type="range" min="0" max="100" step="1" data-k="sfx" aria-label="ระดับเสียงเอฟเฟค"></label>
            </div>
            <div class="kt-audio-bar">
                <button type="button" class="kt-audio-btn kt-audio-toggle" aria-pressed="false" aria-label="เปิดเสียงบรรยากาศ"></button>
                <button type="button" class="kt-audio-btn kt-audio-more" aria-expanded="false" aria-controls="ktAudioPanel" aria-label="ตั้งค่าเสียง">${ICON_SET}</button>
            </div>
            <div class="kt-audio-hint" id="ktAudioHint" role="status" hidden>แตะเพื่อเปิดเสียงบรรยากาศ ♪</div>`;
        document.body.appendChild(box);
        ui = {
            box,
            toggle: box.querySelector('.kt-audio-toggle'),
            more: box.querySelector('.kt-audio-more'),
            panel: box.querySelector('.kt-audio-panel'),
            hint: box.querySelector('.kt-audio-hint'),
            sliders: [...box.querySelectorAll('input[type=range]')],
        };
        ui.sliders.forEach(s => {
            s.value = Math.round(st[s.dataset.k] * 100);
            s.style.setProperty('--fill', `${s.value}%`);
            s.addEventListener('input', () => {
                st[s.dataset.k] = Number(s.value) / 100;
                s.style.setProperty('--fill', `${s.value}%`);
                applyVolumes();
            });
            s.addEventListener('change', () => { save(); if (s.dataset.k === 'sfx') play('select'); });
        });
        ui.toggle.addEventListener('click', () => { hideHint(); setOn(!st.on, true); });
        ui.more.addEventListener('click', () => { hideHint(); setPanel(ui.panel.hidden); });
        document.addEventListener('click', e => { if (!ui.panel.hidden && !box.contains(e.target)) setPanel(false); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !ui.panel.hidden) { setPanel(false); ui.more.focus(); } });
        if (!st.chosen) {                               // ผู้ใช้ใหม่: เด้งคำใบ้สั้น ๆ ครั้งเดียว
            ui.hint.hidden = false;
            setTimeout(hideHint, 9000);
        }
        refreshUi();
    }

    function hideHint() { if (ui && ui.hint) ui.hint.hidden = true; }
    function setPanel(open) {
        ui.panel.hidden = !open;
        ui.more.setAttribute('aria-expanded', String(open));
    }

    function refreshUi() {
        if (!ui) return;
        const playing = st.on && ctx && ctx.state === 'running';
        ui.toggle.innerHTML = st.on ? ICON_ON : ICON_OFF;
        ui.toggle.setAttribute('aria-pressed', String(st.on));
        ui.toggle.setAttribute('aria-label', st.on ? 'ปิดเสียงบรรยากาศ' : 'เปิดเสียงบรรยากาศ');
        ui.box.classList.toggle('is-on', st.on);
        ui.box.classList.toggle('is-playing', Boolean(playing));
        ui.box.classList.toggle('is-waiting', Boolean(st.on && !playing));   // เปิดไว้ แต่รอผู้ใช้แตะหน้าจอก่อน
    }

    /* ---------------- จับเหตุการณ์ในหน้าเว็บ (ไม่ต้องแก้สคริปต์เดิม) ---------------- */
    function watch() {
        // 1) แตะปุ่ม/ลิงก์ทั่วไป
        const HANDLED_ELSEWHERE = '#nextStep, #prevStep, #submitKrathong, .rk, .gallery-item, #copyLink, .kt-audio *';
        document.addEventListener('click', e => {
            const el = e.target.closest && e.target.closest('a, button');
            if (!el || el.matches(HANDLED_ELSEWHERE)) return;
            play('tick');
        }, true);

        // 2) เลือกตัวเลือกในฟอร์ม (กระทง / ชุดดอกไม้ / คำอธิษฐาน)
        document.addEventListener('change', e => {
            const t = e.target;
            if (t && t.matches && t.matches('input[type=radio]')) play('select');
        });

        // 3) ไปขั้นถัดไป / ย้อนกลับ ในตัวสร้างกระทง (อ่านจากข้อความ "ขั้นตอนที่ N จาก M")
        const label = document.getElementById('stepLabel');
        if (label) {
            const num = () => Number((label.textContent.match(/\d+/) || [0])[0]);
            let prev = num() || null;
            new MutationObserver(() => {
                const n = num();
                if (!n) return;
                if (prev !== null && n !== prev) play(n > prev ? 'next' : 'back', n - 1);
                prev = n;
            }).observe(label, { childList: true, characterData: true, subtree: true });
        }

        // 4) คำเตือนคำไม่เหมาะสม (ข้อความ error เปลี่ยนจากซ่อนเป็นแสดง)
        ['nicknameError', 'prayerError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) new MutationObserver(() => { if (!el.hidden) play('error'); }).observe(el, { attributes: true, attributeFilter: ['hidden'] });
        });

        // 5) กดลอยกระทงสำเร็จ (submit ที่ไม่ถูก preventDefault = ส่งจริง)
        document.addEventListener('submit', e => {
            if (e.target && e.target.id === 'krathongWizard' && !e.defaultPrevented) play('release');
        });

        // 6) หน้าแม่น้ำ: กระทงของเราเริ่มลอย → ตกน้ำ
        const mine = document.querySelector('.rk-mine');
        if (mine) {
            new MutationObserver(records => {
                const added = cls => mine.classList.contains(cls) && records.some(r => !(r.oldValue || '').split(/\s+/).includes(cls));
                if (added('is-releasing')) play('whoosh');
                if (added('splashed')) { play('splash'); setTimeout(() => play('success'), 450); }
            }).observe(mine, { attributes: true, attributeFilter: ['class'], attributeOldValue: true });
        }

        // 7) โคมลอยบนท้องฟ้าโผล่ขึ้นมา
        const sky = document.getElementById('riverSky');
        if (sky) new MutationObserver(records => {
            if (records.some(r => r.addedNodes.length)) play('lantern');
        }).observe(sky, { childList: true });

        // 8) หน้าแชร์กระทง: อนิเมชันปล่อยกระทง (เล่นซ้ำได้)
        const stage = document.getElementById('releaseStage');
        if (stage) new MutationObserver(records => {
            const added = stage.classList.contains('release-go') && records.some(r => !(r.oldValue || '').split(/\s+/).includes('release-go'));
            if (!added) return;
            play('whoosh');
            setTimeout(() => play('splash'), 1150);
            setTimeout(() => play('success'), 1650);
        }).observe(stage, { attributes: true, attributeFilter: ['class'], attributeOldValue: true });

        // 9) เปิดหน้าต่างคำอธิษฐาน / รายงาน
        document.querySelectorAll('dialog').forEach(d => {
            new MutationObserver(() => { if (d.open) play('chime'); }).observe(d, { attributes: true, attributeFilter: ['open'] });
        });

        // 10) คัดลอกลิงก์
        const copy = document.getElementById('copyLink');
        if (copy) copy.addEventListener('click', () => setTimeout(() => play('ding'), 120));
    }

    /* ---------------- เริ่มต้น ---------------- */
    window.KrathongAudio = {
        play,
        enable: () => setOn(true, true),
        disable: () => setOn(false),
        get enabled() { return st.on; },
        get playing() { return Boolean(st.on && ctx && ctx.state === 'running'); },
    };

    const init = () => {
        buildUi();
        watch();
        if (st.on) start(false).catch(() => {});        // เปิดไว้จากหน้าก่อน → เริ่มต่อทันทีถ้าเบราว์เซอร์อนุญาต ไม่งั้นรอแตะแรก
    };
    // สคริปต์อยู่ท้าย <body> จึงเริ่มทันที (ต้องมาก่อน river.js เพื่อจับเหตุการณ์ตั้งแต่ต้น)
    if (document.body) init();
    else document.addEventListener('DOMContentLoaded', init);
})();
