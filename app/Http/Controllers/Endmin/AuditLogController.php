<?php

namespace App\Http\Controllers\Endmin;

use App\Http\Controllers\Controller;
use App\Queries\User\AuditLogIndexQuery;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogIndexQuery $auditLogIndexQuery
    ) {
    }

    public function index(Request $request)
    {
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));
        $search = trim((string) $request->query('q', ''));
        $payload = $this->auditLogIndexQuery->build($module, $action, $search);

        return view('endmin.audit-logs.index', [
            'logs' => $payload['logs'],
            'filters' => [
                'module' => $module,
                'action' => $action,
                'q' => $search,
            ],
            'availableModules' => $payload['availableModules'],
            'availableActions' => $payload['availableActions'],
            'sidebarView' => 'layouts.components.endmin-sidebar',
        ]);
    }
}
