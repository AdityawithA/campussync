// CampusSync — Main JS
// Handles: reactions (AJAX), modal close on outside click

const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '';

// ─── Reaction Buttons ───
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.react-btn, .react-btn-lg');
    if (!btn) return;

    const noticeId = btn.dataset.id;
    if (!noticeId) return;

    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('notice_id', noticeId);

        const res  = await fetch(window.location.origin + '/campussync/notices/react.php', {
            method: 'POST',
            body: formData,
        });

        const data = await res.json();

        if (data.success) {
            btn.classList.toggle('reacted', data.reacted);

            // Update displayed count
            const isLgBtn = btn.classList.contains('react-btn-lg');
            if (isLgBtn) {
                btn.textContent = `👍 ${data.count} ${data.count === 1 ? 'Reaction' : 'Reactions'}`;
                btn.classList.toggle('reacted', data.reacted);
            } else {
                btn.textContent = `👍 ${data.count}`;
            }
        }
    } catch (err) {
        console.error('Reaction failed:', err);
    } finally {
        btn.disabled = false;
    }
});

// ─── Modal close on outside click ───
document.addEventListener('click', (e) => {
    const modal = document.getElementById('create-group-modal');
    if (modal && e.target === modal) {
        modal.style.display = 'none';
    }
});

// ─── Auto-dismiss alerts ───
document.querySelectorAll('.alert-success').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 3500);
});
