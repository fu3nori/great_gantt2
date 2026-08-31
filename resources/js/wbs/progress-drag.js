import { patchTask } from './api';
export function bindProgressDrag() {
    document.querySelectorAll('.gauge-handle').forEach(handle => handle.addEventListener('pointerdown', event => {
        event.preventDefault(); event.stopPropagation(); const gauge = handle.closest('.progress-gauge'), row = handle.closest('.task-row'); handle.setPointerCapture(event.pointerId);
        const update = clientX => { const rect=gauge.getBoundingClientRect(); const value=Math.max(0,Math.min(100,Math.round((clientX-rect.left)/rect.width*100))); gauge.style.setProperty('--progress',value); gauge.querySelector('span').textContent=`${value}%`; row.querySelector('.task-percent strong').textContent=`${value}%`; row.dataset.preview=value; };
        update(event.clientX); handle.addEventListener('pointermove', move => update(move.clientX));
        handle.addEventListener('pointerup', async () => { const value=Number(row.dataset.preview); delete row.dataset.preview; try { await patchTask(row,{progress:value}); window.showToast(`進捗を ${value}% に更新しました。`,'success'); } catch(error) { window.showToast(error.message,'danger'); } }, {once:true});
    }));
}
