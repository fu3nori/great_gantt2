const DAY = 86400000;
const iso = value => typeof value === 'string' ? value.slice(0,10) : '';

export function paintRow(row, task) {
    if (!row || !task) return;
    row.dataset.title = task.title;
    row.dataset.start = iso(task.start_date);
    row.dataset.end = iso(task.end_date);
    row.dataset.progress = task.progress;
    row.dataset.lockVersion = task.lock_version;
    row.querySelector('.task-percent strong').textContent = `${task.progress}%`;
    const status = typeof task.status === 'object' ? task.status.value : task.status;
    row.querySelector('.wbs-status').value = status;
    const gauge = row.querySelector('.progress-gauge');
    if (gauge) { gauge.style.setProperty('--progress', task.progress); gauge.querySelector('span').textContent = `${task.progress}%`; }
    const start = new Date(`${iso(task.start_date)}T00:00:00`), end = new Date(`${iso(task.end_date)}T00:00:00`), today = new Date(); today.setHours(0,0,0,0);
    const headers = [...document.querySelectorAll('.date-header .date-cell')];
    row.querySelectorAll('.task-timeline > .grid-cell').forEach((cell, index) => {
        cell.classList.remove('bar-completed','bar-past','bar-future','bar-overdue');
        const date = new Date(`${headers[index].dataset.date}T00:00:00`);
        if (date >= start && date <= end) cell.classList.add(status === 'completed' ? 'bar-completed' : date < today ? 'bar-past' : 'bar-future');
        else if (status !== 'completed' && date > end && date <= today) cell.classList.add('bar-overdue');
    });
    const firstDate = new Date(`${headers[0].dataset.date}T00:00:00`);
    if (gauge) { gauge.style.setProperty('--start', Math.round((start-firstDate)/DAY)); gauge.style.setProperty('--days', Math.round((end-start)/DAY)+1); }
}

export function bindChart() {
    document.addEventListener('wbs:task', event => paintRow(document.querySelector(`.task-row[data-task-id="${event.detail.id}"]`), event.detail));
    document.querySelectorAll('[data-project-toggle]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.projectToggle, rows = document.querySelectorAll(`.task-row[data-project-id="${id}"]`), collapsed = !button.classList.contains('collapsed');
        button.classList.toggle('collapsed', collapsed); rows.forEach(row => row.hidden = collapsed);
    }));
    document.querySelectorAll('[data-task-toggle]').forEach(button => button.addEventListener('click', event => {
        event.stopPropagation(); const id = button.dataset.taskToggle, collapsed = !button.classList.contains('collapsed'); button.classList.toggle('collapsed', collapsed);
        const visit = parent => document.querySelectorAll(`.task-row[data-parent-id="${parent}"]`).forEach(row => { row.hidden = collapsed; if (collapsed) visit(row.dataset.taskId); }); visit(id);
    }));
}
