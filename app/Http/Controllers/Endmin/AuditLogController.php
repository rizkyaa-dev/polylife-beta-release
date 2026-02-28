<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Models\EndminAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));
        $search = trim((string) $request->query('q', ''));

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
                $subQuery->where('module', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('targetUser', function ($targetQuery) use ($search) {
                        $targetQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $logs = $query
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $availableModules = EndminAuditLog::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $availableActions = EndminAuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('endmin.audit-logs.index', [
            'logs' => $logs,
            'filters' => [
                'module' => $module,
                'action' => $action,
                'q' => $search,
            ],
            'availableModules' => $availableModules,
            'availableActions' => $availableActions,
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
