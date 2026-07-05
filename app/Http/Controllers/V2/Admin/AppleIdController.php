<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppleIdController extends Controller
{
    private const SETTING_KEY = 'apple_id_accounts';

    public function fetch()
    {
        return $this->success($this->getAccounts());
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'id' => 'nullable|string|max:80',
            'label' => 'nullable|string|max:80',
            'account' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'region' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
        ], [
            'account.required' => 'Apple ID 账号不能为空',
            'password.required' => 'Apple ID 密码不能为空',
        ]);

        $accounts = $this->getAccounts();
        $id = $params['id'] ?? null;
        $payload = $this->normalizeAccount([
            'id' => $id ?: (string) Str::uuid(),
            'label' => $params['label'] ?? '',
            'account' => $params['account'],
            'password' => $params['password'],
            'region' => $params['region'] ?? '',
            'note' => $params['note'] ?? '',
            'enabled' => $request->boolean('enabled', true),
        ]);

        $updated = false;
        foreach ($accounts as $index => $account) {
            if ($id && $account['id'] === $id) {
                $accounts[$index] = $payload;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $accounts[] = $payload;
        }

        $this->saveAccounts($accounts);

        return $this->success($accounts);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|string|max:80',
        ], [
            'id.required' => 'Apple ID 记录ID不能为空',
        ]);

        $accounts = array_values(array_filter($this->getAccounts(), function (array $account) use ($request) {
            return $account['id'] !== $request->input('id');
        }));

        $this->saveAccounts($accounts);

        return $this->success($accounts);
    }

    private function getAccounts(): array
    {
        $accounts = admin_setting(self::SETTING_KEY, []);
        if (is_string($accounts)) {
            $decoded = json_decode($accounts, true);
            $accounts = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($accounts)) {
            return [];
        }

        return array_values(array_map(function ($account) {
            return $this->normalizeAccount(is_array($account) ? $account : []);
        }, $accounts));
    }

    private function saveAccounts(array $accounts): void
    {
        admin_setting([
            self::SETTING_KEY => array_values(array_map(function (array $account) {
                return $this->normalizeAccount($account);
            }, $accounts)),
        ]);
    }

    private function normalizeAccount(array $account): array
    {
        return [
            'id' => (string) ($account['id'] ?? Str::uuid()),
            'label' => trim((string) ($account['label'] ?? '')),
            'account' => trim((string) ($account['account'] ?? '')),
            'password' => (string) ($account['password'] ?? ''),
            'region' => trim((string) ($account['region'] ?? '')),
            'note' => trim((string) ($account['note'] ?? '')),
            'enabled' => (bool) ($account['enabled'] ?? true),
        ];
    }
}
