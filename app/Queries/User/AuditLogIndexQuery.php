<?php

namespace App\Queries\User;

use App\Models\EndminAuditLog;

class AuditLogIndexQuery
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $module, string $action, string $search): array
    {
        $query = EndminAuditLog::query()
            ->with(['actor:id,name,email', 'targetUser:id,name,email']);

        if ($module !== '') {
            $query->where('module', $module);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('module', 'like', '%' . $search . '%')
                    ->orWhere('action', 'like', '%' . $search . '%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('targetUser', function ($targetQuery) use ($search) {
                        $targetQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        return [
            'logs' => $query->latest()->paginate(30)->withQueryString(),
            'availableModules' => EndminAuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'availableActions' => EndminAuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ];
    }
}
