(() => {
    const dialog = document.getElementById('reportDialog');
    const triggers = document.querySelectorAll('[data-report-open]');
    // เบราว์เซอร์เก่าที่ไม่รองรับ <dialog>: ซ่อนปุ่มรายงาน แทนที่จะให้กดแล้วไม่เกิดอะไร
    if (!dialog || typeof dialog.showModal !== 'function') {
        triggers.forEach(btn => { btn.hidden = true; });
        return;
    }

    const form = document.getElementById('reportForm');
    const idInput = form.querySelector('input[name="id"]');
    const detail = document.getElementById('reportDetail');
    const hint = document.getElementById('reportDetailHint');
    const count = document.getElementById('reportCount');
    const msg = document.getElementById('reportMsg');
    const submit = document.getElementById('reportSubmit');
    const done = document.getElementById('reportDone');
    const doneText = document.getElementById('reportDoneText');
    const endpoint = dialog.dataset.endpoint || 'report.php';
    let sending = false;

    const showMsg = text => { msg.textContent = text; msg.hidden = !text; };
    const syncCount = () => { count.textContent = String(detail.value.length); };
    const syncHint = () => {
        const other = form.elements.reason.value === 'other';
        hint.textContent = other ? '(จำเป็น)' : '(ไม่บังคับ)';
    };

    function open(id) {
        form.reset();
        idInput.value = String(id);
        showMsg('');
        syncCount();
        syncHint();
        form.hidden = false;
        done.hidden = true;
        submit.disabled = false;
        sending = false;
        dialog.showModal();
    }

    // ปุ่ม "รายงาน" (อยู่ในหน้าต่างคำอธิษฐานหรือหน้าแชร์) — ปิดหน้าต่างเดิมก่อนเปิดหน้าต่างรายงาน
    document.addEventListener('click', event => {
        const btn = event.target.closest('[data-report-open]');
        if (!btn) return;
        const id = parseInt(btn.dataset.id || '', 10);
        if (!id) return;
        const parent = btn.closest('dialog');
        if (parent && parent !== dialog) parent.close();
        open(id);
    });

    dialog.querySelectorAll('[data-report-cancel]').forEach(btn => btn.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    detail.addEventListener('input', syncCount);
    form.addEventListener('change', () => { syncHint(); showMsg(''); });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending) return;
        const reason = form.elements.reason.value;
        if (!reason) { showMsg('กรุณาเลือกเหตุผลที่รายงาน'); return; }
        if (reason === 'other' && detail.value.trim().length < 3) {
            showMsg('กรุณาอธิบายเหตุผลสั้น ๆ เมื่อเลือก "อื่น ๆ"');
            detail.focus();
            return;
        }
        showMsg('');
        sending = true;
        submit.disabled = true;
        submit.textContent = 'กำลังส่ง…';
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'X-Requested-With': 'krathong', 'Accept': 'application/json' },
                body: new FormData(form),
                credentials: 'same-origin',
            });
            let data = {};
            try { data = await res.json(); } catch (err) { data = {}; }
            if (res.ok && data.ok) {
                doneText.textContent = data.message || 'ขอบคุณที่แจ้ง ผู้ดูแลจะตรวจสอบโดยเร็ว';
                form.hidden = true;
                done.hidden = false;
            } else {
                showMsg(data.error || 'ส่งรายงานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
            }
        } catch (err) {
            showMsg('เชื่อมต่อไม่ได้ กรุณาตรวจสอบอินเทอร์เน็ตแล้วลองใหม่');
        } finally {
            sending = false;
            submit.disabled = false;
            submit.textContent = 'ส่งรายงาน';
        }
    });
})();
