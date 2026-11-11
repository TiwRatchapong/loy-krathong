(() => {
    const dialog = document.getElementById('wishDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const name = document.getElementById('wishName');
    const text = document.getElementById('wishText');
    const link = document.getElementById('wishLink');

    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', () => {
            name.textContent = item.dataset.name;
            text.textContent = item.dataset.prayer;   // textContent: ไม่ตีความ HTML
            link.href = `view.php?id=${encodeURIComponent(item.dataset.id)}`;
            dialog.showModal();
        });
    });
    // กดนอกกล่องเพื่อปิด
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();
