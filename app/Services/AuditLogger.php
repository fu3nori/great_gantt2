<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Project;

class AuditLogger
{
    public function record(object $subject, string $action, array $before = [], array $after = []): void
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changes[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
            }
        }
        if ($before && ! $changes) {
            return;
        }
        $project = $subject instanceof Project ? $subject : ($subject->project ?? null);
        $organizationId = $subject instanceof Organization ? $subject->id : $project?->organization_id;
        ActivityLog::create([
            'user_id' => auth()->id(), 'organization_id' => $organizationId,
            'project_id' => $project?->id, 'subject_type' => $subject::class, 'subject_id' => $subject->id,
            'action' => $action, 'changes' => $changes ?: null, 'ip_address' => request()?->ip(),
        ]);
    }
}
