(() => {
    const form = document.getElementById('krathongWizard');
    if (!form) return;

    const SVG_NS = 'http://www.w3.org/2000/svg';
    const XLINK_NS = 'http://www.w3.org/1999/xlink';

    const steps = [...form.querySelectorAll('.wizard-step')];
    const progress = document.getElementById('stepProgress');
    const stepLabel = document.getElementById('stepLabel');
    const stepPercent = document.getElementById('stepPercent');
    const stepNames = [...form.querySelectorAll('.wizard-steps span')];
    const prevButton = document.getElementById('prevStep');
    const nextButton = document.getElementById('nextStep');
    const submitButton = document.getElementById('submitKrathong');
    const nickname = document.getElementById('nickname');
    const nicknameError = document.getElementById('nicknameError');
    const krathongInputs = [...form.querySelectorAll('input[name="krathong_type_id"]')];
    const flowerSetInputs = [...form.querySelectorAll('input[name="flower_set_id"]')];
    const prayerTabs = [...form.querySelectorAll('input[name="prayer_type"]')];
    const presetInputs = [...form.querySelectorAll('input[name="preset_prayer"]')];
    const customPrayer = document.getElementById('customPrayer');
    const prayerError = document.getElementById('prayerError');
    const prayerPresets = document.getElementById('prayerPresets');
    const prayerValue = document.getElementById('prayerValue');
    const charCount = document.getElementById('charCount');
    const previewNickname = document.getElementById('previewNickname');
    const previewPrayer = document.getElementById('previewPrayer');
    const previewKrathong = document.getElementById('previewKrathong');
    const pvBase = document.getElementById('pvBase');
    const pvFlowers = document.getElementById('pvFlowers');
    const checkUrl = form.dataset.checkUrl || '';

    const MESSAGES = {
        nickname: 'ชื่อเล่นมีคำที่ไม่เหมาะสม กรุณาเปลี่ยนชื่อ',
        prayer: 'คำอธิษฐานมีคำที่ไม่เหมาะสม กรุณาแก้ไขข้อความ',
    };
    const MASK = '•••';

    let currentStep = 0;
    const flagged = { nickname: false, prayer: false };
    let checkSeq = 0;
    let checkTimer = null;
    let verifiedForSubmit = false;

    /* ---------- SVG preview ---------- */

    function useEl(symbolId, x, y, size, extra = {}) {
        const use = document.createElementNS(SVG_NS, 'use');
        use.setAttribute('href', `#${symbolId}`);
        use.setAttributeNS(XLINK_NS, 'xlink:href', `#${symbolId}`);
        use.setAttribute('x', String(x));
        use.setAttribute('y', String(y));
        use.setAttribute('width', String(size[0]));
        use.setAttribute('height', String(size[1]));
        Object.entries(extra).forEach(([k, v]) => use.setAttribute(k, v));
        return use;
    }

    // ตำแหน่งดอกไม้บนกระทง [ศูนย์กลาง x, y, ขนาด, องศาหมุน] ลำดับ: กลาง, ซ้ายใน, ขวาใน, ซ้ายนอก, ขวานอก
    // ดอกกลางอยู่หน้าสุดและใหญ่สุด ดอกด้านนอกอยู่ไกลกว่าและเล็กกว่า ให้เป็นกลุ่มโค้งตามปากกระทง
    const FLOWER_SLOTS = [[120, 139, 54, 0], [93, 131, 44, -14], [147, 131, 44, 12], [67, 124, 38, -22], [173, 124, 38, 20]];
    // วาดจากด้านหลังมาหน้า ให้ดอกกลางทับอยู่หน้าสุด
    const DRAW_ORDER = [3, 4, 1, 2, 0];

    function selectedKrathongCard() {
        return krathongInputs.find(input => input.checked)?.closest('.krathong-option') || null;
    }

    function selectedSetCard() {
        return flowerSetInputs.find(input => input.checked)?.closest('.flower-set-option') || null;
    }

    function renderPreviewGraphic() {
        const krathongCard = selectedKrathongCard();
        const fallbackKey = krathongInputs[0]?.closest('.krathong-option')?.dataset.svg;
        const key = krathongCard?.dataset.svg || fallbackKey;
        pvBase.replaceChildren();
        if (key) {
            pvBase.appendChild(useEl(`kt-${key}`, 20, 62, [200, 130], krathongCard ? {} : { opacity: '0.3' }));
        }

        pvFlowers.replaceChildren();
        const flowerKeys = (selectedSetCard()?.dataset.flowers || '').split(',').filter(Boolean);
        DRAW_ORDER.forEach(slot => {
            const [cx, cy, size, rot] = FLOWER_SLOTS[slot];
            const x = cx - size / 2;
            const y = cy - size / 2;
            if (flowerKeys[slot]) {
                pvFlowers.appendChild(useEl(`fk-${flowerKeys[slot]}`, x, y, [size, size], { transform: `rotate(${rot} ${cx} ${cy})` }));
            } else {
                const ghost = document.createElementNS(SVG_NS, 'circle');
                ghost.setAttribute('cx', String(cx));
                ghost.setAttribute('cy', String(cy));
                ghost.setAttribute('r', '15');
                ghost.setAttribute('fill', 'rgba(232,199,126,.08)');
                ghost.setAttribute('stroke', 'rgba(232,199,126,.55)');
                ghost.setAttribute('stroke-dasharray', '3 3');
                pvFlowers.appendChild(ghost);
            }
        });
    }

    /* ---------- Prayer ---------- */

    function prayerText() {
        const type = prayerTabs.find(input => input.checked)?.value || 'preset';
        const preset = presetInputs.find(input => input.checked)?.value || '';
        return type === 'custom' ? customPrayer.value.trim() : preset;
    }

    function updatePrayer() {
        const type = prayerTabs.find(input => input.checked)?.value || 'preset';
        const isCustom = type === 'custom';
        prayerPresets.hidden = isCustom;
        customPrayer.hidden = !isCustom;
        const value = prayerText();
        prayerValue.value = value;
        previewPrayer.textContent = flagged.prayer && isCustom ? MASK : (value || 'คำอธิษฐานของคุณจะปรากฏที่นี่');
        charCount.textContent = `${customPrayer.value.length} / 300`;
        charCount.hidden = !isCustom;
        if (!isCustom) setFlag('prayer', false);
    }

    function updatePreview() {
        const name = nickname.value.trim();
        previewNickname.textContent = flagged.nickname ? MASK : (name || 'ชื่อของคุณ');
        previewKrathong.textContent = [
            selectedKrathongCard()?.dataset.name || 'ยังไม่ได้เลือกกระทง',
            selectedSetCard() ? selectedSetCard().dataset.name.split(' · ')[0] : null,
        ].filter(Boolean).join(' · ');
        renderPreviewGraphic();
        updatePrayer();
    }

    /* ---------- Profanity check (server-side list, same filter used on save) ---------- */

    function setFlag(field, isFlagged) {
        flagged[field] = isFlagged;
        const input = field === 'nickname' ? nickname : customPrayer;
        const message = field === 'nickname' ? nicknameError : prayerError;
        message.textContent = isFlagged ? MESSAGES[field] : '';
        message.hidden = !isFlagged;
        input.classList.toggle('invalid', isFlagged);
        input.setAttribute('aria-invalid', String(isFlagged));
    }

    /** ส่งข้อความไปตรวจที่เซิร์ฟเวอร์ คืนค่า true ถ้าผ่าน (ถ้าเรียกไม่สำเร็จให้ผ่านไปก่อน เพราะเซิร์ฟเวอร์ตรวจซ้ำตอนบันทึกอยู่แล้ว) */
    async function runCheck() {
        if (!checkUrl) return true;
        const seq = ++checkSeq;
        const isCustom = (prayerTabs.find(input => input.checked)?.value || 'preset') === 'custom';
        const body = new URLSearchParams({
            nickname: nickname.value,
            prayer: isCustom ? customPrayer.value : '',
        });
        try {
            const response = await fetch(checkUrl, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) return true;
            const result = await response.json();
            if (seq !== checkSeq) return !(flagged.nickname || flagged.prayer);
            setFlag('nickname', Boolean(result.nickname));
            setFlag('prayer', isCustom && Boolean(result.prayer));
            updatePreview();
            return !(flagged.nickname || flagged.prayer);
        } catch (error) {
            return true;
        }
    }

    function scheduleCheck() {
        verifiedForSubmit = false;
        clearTimeout(checkTimer);
        checkTimer = setTimeout(runCheck, 350);
    }

    /* ---------- Wizard ---------- */

    function showStep(index) {
        currentStep = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, i) => {
            step.hidden = i !== currentStep;
            step.classList.toggle('active', i === currentStep);
        });
        const percent = Math.round(((currentStep + 1) / steps.length) * 100);
        progress.style.width = `${percent}%`;
        stepLabel.textContent = `ขั้นตอนที่ ${currentStep + 1} จาก ${steps.length}`;
        stepPercent.textContent = `${percent}%`;
        stepNames.forEach((name, i) => {
            name.classList.toggle('active', i === currentStep);
            name.classList.toggle('done', i < currentStep);
        });
        prevButton.hidden = currentStep === 0;
        nextButton.hidden = currentStep === steps.length - 1;
        submitButton.hidden = currentStep !== steps.length - 1;
        if (currentStep === 4) updateReview();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(index) {
        if (index === 0) {
            if (!nickname.value.trim()) {
                nickname.focus();
                nickname.setCustomValidity('กรุณากรอกชื่อเล่นก่อน');
                nickname.reportValidity();
                return false;
            }
            nickname.setCustomValidity('');
            if (flagged.nickname) {
                nickname.focus();
                return false;
            }
        }
        if (index === 1 && !krathongInputs.some(input => input.checked)) {
            alert('กรุณาเลือกกระทงที่ต้องการ');
            return false;
        }
        if (index === 2 && !flowerSetInputs.some(input => input.checked)) {
            alert('กรุณาเลือกชุดดอกไม้');
            return false;
        }
        if (index === 3) {
            updatePrayer();
            if (!prayerValue.value) {
                alert('กรุณาเลือกหรือเขียนคำอธิษฐาน');
                if (prayerTabs.find(input => input.checked)?.value === 'custom') customPrayer.focus();
                return false;
            }
            if (flagged.prayer) {
                customPrayer.focus();
                return false;
            }
        }
        return true;
    }

    function updateReview() {
        document.getElementById('reviewNickname').textContent = flagged.nickname ? MASK : (nickname.value.trim() || '—');
        document.getElementById('reviewKrathong').textContent = selectedKrathongCard()?.dataset.name || '—';
        const setCard = selectedSetCard();
        document.getElementById('reviewFlowers').textContent = setCard ? `${setCard.dataset.name} — ${setCard.dataset.summary}` : '—';
        document.getElementById('reviewPrayer').textContent = flagged.prayer ? MASK : (prayerText() || '—');
    }

    nextButton.addEventListener('click', async () => {
        // ขั้นตอนที่มีข้อความจากผู้ใช้ (ชื่อ / คำอธิษฐาน) ให้ตรวจคำไม่เหมาะสมก่อนไปต่อ
        if (currentStep === 0 || currentStep === 3) {
            if (currentStep === 0 && !nickname.value.trim()) { validateStep(0); return; }
            nextButton.disabled = true;
            await runCheck();
            nextButton.disabled = false;
        }
        if (validateStep(currentStep)) showStep(currentStep + 1);
    });
    prevButton.addEventListener('click', () => showStep(currentStep - 1));

    nickname.addEventListener('input', () => {
        nickname.setCustomValidity('');
        updatePreview();
        scheduleCheck();
    });
    krathongInputs.forEach(input => input.addEventListener('change', () => {
        krathongInputs.forEach(item => item.closest('.krathong-option').classList.toggle('selected', item.checked));
        updatePreview();
    }));
    flowerSetInputs.forEach(input => input.addEventListener('change', () => {
        flowerSetInputs.forEach(item => item.closest('.krathong-option').classList.toggle('selected', item.checked));
        updatePreview();
    }));
    prayerTabs.forEach(input => input.addEventListener('change', () => { updatePrayer(); scheduleCheck(); }));
    presetInputs.forEach(input => input.addEventListener('change', updatePrayer));
    customPrayer.addEventListener('input', () => { updatePrayer(); scheduleCheck(); });

    form.addEventListener('submit', async event => {
        updatePrayer();
        if (verifiedForSubmit) return;
        event.preventDefault();
        submitButton.disabled = true;
        const textOk = await runCheck();
        submitButton.disabled = false;
        if (!textOk) {
            showStep(flagged.nickname ? 0 : 3);
            return;
        }
        if (!validateStep(0) || !validateStep(1) || !validateStep(2) || !validateStep(3)) return;
        verifiedForSubmit = true;
        form.requestSubmit(submitButton);
    });

    updatePreview();
    showStep(0);
    if (nickname.value.trim() || customPrayer.value.trim()) runCheck();
})();
