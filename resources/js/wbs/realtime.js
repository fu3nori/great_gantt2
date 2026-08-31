export function bindRealtime() {
    if (!window.Echo) return;
    const ids=[...new Set([...document.querySelectorAll('.project-row')].map(row=>row.dataset.projectId))];
    ids.forEach(id=>window.Echo.private(`project.${id}`).listen('.TaskUpdated',event=>{if(event.action==='deleted')document.querySelector(`.task-row[data-task-id="${event.task.id}"]`)?.remove();else document.dispatchEvent(new CustomEvent('wbs:task',{detail:event.task}))}).listen('.ProjectUpdated',event=>{if(event.action==='deleted')document.querySelectorAll(`[data-project-id="${id}"]`).forEach(x=>x.remove())}));
}
