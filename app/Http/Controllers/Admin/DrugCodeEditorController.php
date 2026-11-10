<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DrugCodeSetEditor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin page that grants or revokes the "ผู้ตั้งค่ารหัสยา" permission (CONTEXT.md). */
class DrugCodeEditorController extends Controller
{
    public function index(): View
    {
        $editors   = DrugCodeSetEditor::with('user')->get()->keyBy('user_id');
        $users     = User::orderBy('name')->get(['id', 'name', 'username']);
        $adminIds  = $users->filter(fn (User $u) => Gate::forUser($u)->allows('manage-settings'))->pluck('id')->all();

        return view('admin.drug-code-editors.index', [
            'users'    => $users,
            'editors'  => $editors,
            'adminIds' => $adminIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::findOrFail($data['user_id']);

        if (Gate::forUser($user)->allows('manage-settings')) {
            return back()->with('error', 'ผู้ดูแลระบบมีสิทธิ์ตั้งค่ารหัสยาอยู่แล้ว');
        }

        $editor = DrugCodeSetEditor::firstOrCreate(
            ['user_id' => $user->id],
            ['granted_by' => $request->user()->id]
        );

        if ($editor->wasRecentlyCreated) {
            AuditLog::record('drug_code_editor.grant', [
                'subject'   => "user:{$user->id}",
                'new_value' => ['username' => $user->username],
            ]);
        }

        return back()->with('success', "ให้สิทธิ์ตั้งค่ารหัสยาแก่ {$user->name} แล้ว");
    }

    public function destroy(DrugCodeSetEditor $editor): RedirectResponse
    {
        $user = $editor->user;
        $editor->delete();

        AuditLog::record('drug_code_editor.revoke', [
            'subject'   => "user:{$editor->user_id}",
            'old_value' => ['username' => $user?->username],
        ]);

        return back()->with('success', 'ถอนสิทธิ์ตั้งค่ารหัสยาแล้ว');
    }
}
