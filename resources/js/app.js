import './bootstrap';
import './echo';
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import { initWbs } from './wbs/index';
import { initHomeRealtime } from './home-realtime';

window.bootstrap = bootstrap;
document.querySelectorAll('[title]').forEach(element => new bootstrap.Tooltip(element));

window.showToast = (message, type = 'primary') => {
    const element = document.getElementById('appToast');
    if (! element) return;

    element.className = `toast border-0 text-bg-${type}`;
    element.querySelector('.toast-body').textContent = message;
    bootstrap.Toast.getOrCreateInstance(element).show();
};

const renderTaskStatus = (status, label) => {
    const statusBadge = document.querySelector('[data-task-status-badge]');

    if (! statusBadge || ! status) return;

    [...statusBadge.classList]
        .filter(className => className.startsWith('status-'))
        .forEach(className => statusBadge.classList.remove(className));
    statusBadge.classList.add(`status-${status}`);
    statusBadge.textContent = label || status;
};

const selectedStatusLabel = form => form.elements.status?.selectedOptions?.[0]?.textContent?.trim();

const updateTaskSummary = (form, payload) => {
    const status = payload.task?.status;
    const statusLabel = payload.status_label || selectedStatusLabel(form) || status;
    const statusBadge = document.querySelector('[data-task-status-badge]');

    renderTaskStatus(status, statusLabel);
    if (statusBadge && status) {
        statusBadge.dataset.savedStatus = status;
        statusBadge.dataset.savedLabel = statusLabel;
    }

    const progress = Number(payload.task?.progress);
    if (! Number.isFinite(progress)) return;

    if (form.elements.progress) form.elements.progress.value = progress;
    document.querySelector('[data-task-progress-donut]')?.style.setProperty('--progress', progress);

    const progressText = document.querySelector('[data-task-progress-text]');
    if (progressText) progressText.textContent = `${progress}%`;

    const progressBar = document.querySelector('[data-task-progress-bar]');
    if (progressBar) progressBar.style.width = `${progress}%`;
};

document.querySelectorAll('[data-ajax-task]').forEach(form => {
    const statusSelect = form.elements.status;
    const statusBadge = document.querySelector('[data-task-status-badge]');

    statusSelect?.addEventListener('change', () => {
        renderTaskStatus(statusSelect.value, selectedStatusLabel(form));
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

    const indicator = document.getElementById('saveIndicator');
    if (indicator) indicator.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 保存中';

    const data = Object.fromEntries(new FormData(form));

    try {
        const response = await fetch(form.action, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify(data),
        });
        const payload = await response.json();

        if (! response.ok) {
            throw Object.assign(new Error(payload.message || '保存できませんでした。'), { payload });
        }

        form.elements.lock_version.value = payload.task.lock_version;
        updateTaskSummary(form, payload);
        if (indicator) indicator.innerHTML = '<i class="bi bi-cloud-check"></i> 保存済み';
        window.showToast('タスクを保存しました。', 'success');
    } catch (error) {
        if (indicator) indicator.innerHTML = '<i class="bi bi-cloud-slash"></i> 保存失敗';
        window.showToast(error.message, 'danger');
        if (error.payload?.task) {
            form.elements.lock_version.value = error.payload.task.lock_version;
            if (error.payload.task.status) {
                statusSelect.value = error.payload.task.status;
                renderTaskStatus(statusSelect.value, selectedStatusLabel(form));
            }
        } else if (statusBadge?.dataset.savedStatus) {
            statusSelect.value = statusBadge.dataset.savedStatus;
            renderTaskStatus(statusBadge.dataset.savedStatus, statusBadge.dataset.savedLabel);
        }
    }
    });
});

if (document.getElementById('wbsShell')) initWbs();
if (document.getElementById('projectGrid')) initHomeRealtime();
