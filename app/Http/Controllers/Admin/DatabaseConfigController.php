<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DatabaseConfigRequest;
use App\Models\AuditLog;
use App\Services\DatabaseConfigService;
use App\Services\FontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DatabaseConfigController extends Controller
{
    public function __construct(
        private DatabaseConfigService $configService,
        private FontService $fonts
    ) {}

    public function show(): View
    {
        return view('admin.database.show', [
            'config'      => $this->configService->getSafeConfig(),
            'hasConfig'   => $this->configService->hasConfig(),
            'fontCatalog' => $this->fonts->catalog(),
            'fontCurrent' => $this->fonts->current(),
        ]);
    }

    public function update(DatabaseConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $oldConfig = $this->configService->getSafeConfig();

        $this->configService->save($data);

        // Reload the 'rdu' connection so it picks up the new config
        DB::purge('rdu');

        AuditLog::record('db_config.update', [
            'subject'   => 'HOSxP Database Configuration',
            'old_value' => $oldConfig,
            'new_value' => [
                'host'     => $data['host'],
                'port'     => $data['port'],
                'database' => $data['database'],
                'schema'   => $data['schema'] ?? 'public',
                'username' => $data['username'],
                // password intentionally omitted from audit log
            ],
        ]);

        return redirect()
            ->route('admin.database.show')
            ->with('success', 'บันทึก HOSxP Configuration เรียบร้อยแล้ว');
    }

    /** AJAX endpoint — test connection with form data (does not save). */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'host'     => ['required', 'string'],
            'port'     => ['required', 'integer'],
            'database' => ['required', 'string'],
            'schema'   => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $result = $this->configService->testConnection($request->only(
            'host', 'port', 'database', 'schema', 'username', 'password'
        ));

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
