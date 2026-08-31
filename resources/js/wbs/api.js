const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

export async function patchTask(row, changes) {
    const response = await fetch(row.dataset.updateUrl, {
        method: 'PATCH', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},
        body: JSON.stringify({...changes, lock_version:Number(row.dataset.lockVersion)}),
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 409 && body.task) document.dispatchEvent(new CustomEvent('wbs:task', {detail:body.task}));
        const validation = body.errors ? Object.values(body.errors).flat().join(' ') : '';
        throw new Error(validation || body.message || `保存に失敗しました (${response.status})`);
    }
    document.dispatchEvent(new CustomEvent('wbs:task', {detail:body.task}));
    return body.task;
}

export async function deleteProject(projectId) {
    const response = await fetch(`/projects/${projectId}`, {method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf()}});
    if (!response.ok && !response.redirected) throw new Error('プロジェクトを削除できませんでした。');
}
