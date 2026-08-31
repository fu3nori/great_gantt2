export function initHomeRealtime() {
    if (!window.Echo) return;
    document.querySelectorAll('#projectGrid [data-project-id]').forEach(card => {
        const id = card.dataset.projectId;
        window.Echo.private(`project.${id}`).listen('.TaskUpdated', event => {
            const value = Number(event.project_progress ?? 0);
            card.querySelector('[data-project-progress]').textContent = `${value}%`;
            card.querySelector('.progress-bar').style.width = `${value}%`;
        }).listen('.ProjectUpdated', event => {
            if (event.action === 'deleted') card.remove();
        });
    });
}
