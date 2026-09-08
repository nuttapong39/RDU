<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatabaseConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Phase 5 will add role check: auth()->user()->isAdmin()
    }

    public function rules(): array
    {
        $passwordRequired = ! $this->hasExistingConfig();

        return [
            'host'     => ['required', 'string', 'max:255'],
            'port'     => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:100'],
            'schema'   => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.required'     => 'กรุณาระบุ Host หรือ IP Address ของ HOSxP',
            'port.required'     => 'กรุณาระบุ Port',
            'port.integer'      => 'Port ต้องเป็นตัวเลข',
            'database.required' => 'กรุณาระบุชื่อ Database',
            'username.required' => 'กรุณาระบุ Username',
            'password.required' => 'กรุณาระบุ Password (ยังไม่มี Config — จำเป็นต้องระบุ)',
        ];
    }

    private function hasExistingConfig(): bool
    {
        return file_exists(storage_path('app/private/config/rdu_database.json'));
    }
}
