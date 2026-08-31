let selected = null;
export const selectedRow = () => selected;
export function selectRow(row) {
    selected?.classList.remove('selected'); selected = row; selected?.classList.add('selected');
    document.querySelector('.toolbar-empty').classList.toggle('d-none', !!selected);
    document.querySelector('.toolbar-controls').classList.toggle('d-none', !selected);
    if (!selected) return;
    selectedTaskName.textContent = selected.dataset.title;
    toolbarStart.value = selected.dataset.start; toolbarEnd.value = selected.dataset.end; toolbarProgress.value = selected.dataset.progress;
}
export function bindSelection() {
    document.querySelectorAll('.task-row').forEach(row => row.addEventListener('click', event => { if (!event.target.closest('a,select,button')) selectRow(row); }));
}
