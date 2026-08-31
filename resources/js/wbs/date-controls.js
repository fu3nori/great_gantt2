import { patchTask } from './api';
import { selectedRow, selectRow } from './selection';
const shift = (value, days) => { const d = new Date(`${value}T00:00:00`); d.setDate(d.getDate()+days); return d.toISOString().slice(0,10); };
async function save(changes) {
    const row = selectedRow(); if (!row) return;
    try { const task = await patchTask(row, changes); selectRow(row); window.showToast('WBSを更新しました。','success'); return task; }
    catch (error) { window.showToast(error.message,'danger'); }
}
export function bindDateControls() {
    document.querySelectorAll('[data-shift]').forEach(button => button.addEventListener('click', () => { const [field,amount] = button.dataset.shift.split(','); const input = field === 'start' ? toolbarStart : toolbarEnd; input.value = shift(input.value, Number(amount)); save({start_date:toolbarStart.value,end_date:toolbarEnd.value}); }));
    toolbarSave?.addEventListener('click', () => save({start_date:toolbarStart.value,end_date:toolbarEnd.value,progress:Number(toolbarProgress.value)}));
    document.querySelectorAll('.wbs-status').forEach(select => select.addEventListener('change', async event => { event.stopPropagation(); const row = select.closest('.task-row'); try { await patchTask(row,{status:select.value}); window.showToast('ステータスを保存しました。','success'); } catch(error) { window.showToast(error.message,'danger'); } }));
    document.querySelectorAll('.wbs-assignee').forEach(select => select.addEventListener('change', async event => { event.stopPropagation(); const row = select.closest('.task-row'); try { await patchTask(row,{assignee_id:select.value || null}); window.showToast('担当者を保存しました。','success'); } catch(error) { window.showToast(error.message,'danger'); } }));
    const shell = document.getElementById('wbsShell');
    const dayWidth = () => Number.parseFloat(getComputedStyle(shell).getPropertyValue('--wbs-day-width')) || 34;
    const infoWidth = () => Number.parseFloat(getComputedStyle(shell).getPropertyValue('--wbs-info-width')) || 520;
    const scrollByDay = direction => shell.scrollTo({left: Math.max(0, shell.scrollLeft + (direction * dayWidth())), behavior:'auto'});
    document.getElementById('scrollPrevDay')?.addEventListener('click', () => scrollByDay(-1));
    document.getElementById('scrollNextDay')?.addEventListener('click', () => scrollByDay(1));
    document.getElementById('scrollToday')?.addEventListener('click', () => {
        const today = document.querySelector('.date-header .date-cell.is-today');
        if (!today) return;
        const dates = [...document.querySelectorAll('.date-header .date-cell')];
        const index = dates.indexOf(today);
        const visibleTimelineWidth = Math.max(dayWidth(), shell.clientWidth - infoWidth());
        shell.scrollTo({left: Math.max(0, (index * dayWidth()) - (visibleTimelineWidth / 2) + (dayWidth() / 2)), behavior:'smooth'});
    });
}
