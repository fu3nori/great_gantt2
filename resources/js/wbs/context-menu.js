import { deleteProject } from './api';
export function bindContextMenu() {
    const menu=document.getElementById('projectContext'); let projectId=null; let projectUrl=null; let deleteUrl=null;
    document.querySelectorAll('[data-project-context]').forEach(target=>target.addEventListener('contextmenu',event=>{event.preventDefault();projectId=target.dataset.projectContext;projectUrl=target.dataset.projectUrl;deleteUrl=target.dataset.deleteUrl;menu.style.left=`${event.clientX}px`;menu.style.top=`${event.clientY}px`;menu.classList.remove('d-none')}));
    document.addEventListener('click',()=>menu.classList.add('d-none')); menu.addEventListener('click',async event=>{const action=event.target.closest('button')?.dataset.action;if(!action)return;if(['open','task','invite'].includes(action)&&projectUrl) location.href=projectUrl;if(action==='delete'&&deleteUrl&&confirm('プロジェクトを削除しますか？')){try{await deleteProject(deleteUrl);document.querySelectorAll(`[data-project-id="${projectId}"]`).forEach(x=>x.remove());window.showToast('プロジェクトを削除しました。','success')}catch(error){window.showToast(error.message,'danger')}}});
}
