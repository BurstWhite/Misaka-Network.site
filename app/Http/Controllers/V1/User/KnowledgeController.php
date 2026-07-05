<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeResource;
use App\Models\Knowledge;
use App\Models\User;
use App\Services\Plugin\HookManager;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'id' => 'nullable|sometimes|integer|min:1',
            'language' => 'nullable|sometimes|string|max:10',
            'keyword' => 'nullable|sometimes|string|max:255',
        ]);

        return $request->input('id')
            ? $this->fetchSingle($request)
            : $this->fetchList($request);
    }

    private function fetchSingle(Request $request)
    {
        $knowledge = $this->buildKnowledgeQuery()
            ->where('id', $request->input('id'))
            ->first();

        if (!$knowledge) {
            return $this->fail([500, __('Article does not exist')]);
        }

        $knowledge = $knowledge->toArray();
        $knowledge = $this->processKnowledgeContent($knowledge, $request->user());

        return $this->success(KnowledgeResource::make($knowledge));
    }

    private function fetchList(Request $request)
    {
        $builder = $this->buildKnowledgeQuery(['id', 'category', 'title', 'updated_at', 'body'])
            ->where('language', $request->input('language'))
            ->orderBy('sort', 'ASC');

        $keyword = $request->input('keyword');
        if ($keyword) {
            $builder = $builder->where(function ($query) use ($keyword) {
                $query->where('title', 'LIKE', "%{$keyword}%")
                    ->orWhere('body', 'LIKE', "%{$keyword}%");
            });
        }

        $knowledges = $builder->get()
            ->map(function ($knowledge) use ($request) {
                $knowledge = $knowledge->toArray();
                $knowledge = $this->processKnowledgeContent($knowledge, $request->user());
                return KnowledgeResource::make($knowledge);
            })
            ->groupBy('category');

        return $this->success($knowledges);
    }

    private function buildKnowledgeQuery(array $select = ['*'])
    {
        return Knowledge::select($select)->where('show', 1);
    }

    private function processKnowledgeContent(array $knowledge, User $user): array
    {
        if (!isset($knowledge['body'])) {
            return $knowledge;
        }

        if (!$this->userService->isAvailable($user)) {
            $this->formatAccessData($knowledge['body']);
        }
        $subscribeUrl = Helper::getSubscribeUrl($user['token']);
        $knowledge['body'] = $this->replacePlaceholders(
            $knowledge['body'],
            $subscribeUrl,
            $this->canViewAppleIdAccounts($user)
        );

        return $knowledge;
    }

    private function formatAccessData(&$body): void
    {
        $rules = [
            [
                'type' => 'regex',
                'pattern' => '/<!--access start-->(.*?)<!--access end-->/s',
                'replacement' => '<div class="v2board-no-access">' . __('You must have a valid subscription to view content in this area') . '</div>'
            ]
        ];

        $this->applyReplacementRules($body, $rules);
    }

    private function replacePlaceholders(string $body, string $subscribeUrl, bool $canViewAppleIdAccounts): string
    {
        $appleIdPlaceholders = $this->formatAppleIdPlaceholders($canViewAppleIdAccounts);
        $rules = [
            [
                'type' => 'string',
                'search' => '{{siteName}}',
                'replacement' => admin_setting('app_name', 'XBoard')
            ],
            [
                'type' => 'string',
                'search' => '{{subscribeUrl}}',
                'replacement' => $subscribeUrl
            ],
            [
                'type' => 'string',
                'search' => '{{urlEncodeSubscribeUrl}}',
                'replacement' => urlencode($subscribeUrl)
            ],
            [
                'type' => 'string',
                'search' => '{{safeBase64SubscribeUrl}}',
                'replacement' => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($subscribeUrl))
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdAccount}}',
                'replacement' => $appleIdPlaceholders['account']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdPassword}}',
                'replacement' => $appleIdPlaceholders['password']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdRegion}}',
                'replacement' => $appleIdPlaceholders['region']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdLabel}}',
                'replacement' => $appleIdPlaceholders['label']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdNote}}',
                'replacement' => $appleIdPlaceholders['note']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIds}}',
                'replacement' => $appleIdPlaceholders['list']
            ],
            [
                'type' => 'string',
                'search' => '{{appleIdList}}',
                'replacement' => $appleIdPlaceholders['list']
            ],
        ];

        $this->applyReplacementRules($body, $rules);
        return $body;
    }

    private function canViewAppleIdAccounts(User $user): bool
    {
        return !$user->banned
            && $user->plan_id !== null
            && ($user->expired_at === null || $user->expired_at > time());
    }

    private function formatAppleIdPlaceholders(bool $canViewAppleIdAccounts): array
    {
        if (!$canViewAppleIdAccounts) {
            $message = '<div class="v2board-no-access">购买套餐后可查看 Apple ID 账号密码信息</div>';
            return [
                'account' => $message,
                'password' => $message,
                'region' => $message,
                'label' => $message,
                'note' => $message,
                'list' => $message,
            ];
        }

        $accounts = $this->getEnabledAppleIdAccounts();
        if (count($accounts) === 0) {
            $message = '<div class="v2board-no-access">暂未配置 Apple ID 账号信息</div>';
            return [
                'account' => $message,
                'password' => $message,
                'region' => $message,
                'label' => $message,
                'note' => $message,
                'list' => $message,
            ];
        }

        $first = $accounts[0];

        return [
            'account' => e($first['account']),
            'password' => e($first['password']),
            'region' => e($first['region']),
            'label' => e($first['label']),
            'note' => e($first['note']),
            'list' => $this->formatAppleIdList($accounts),
        ];
    }

    private function getEnabledAppleIdAccounts(): array
    {
        $accounts = admin_setting('apple_id_accounts', []);
        if (is_string($accounts)) {
            $decoded = json_decode($accounts, true);
            $accounts = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($accounts)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($account) {
            if (!is_array($account)) {
                return null;
            }
            $normalized = [
                'label' => trim((string) ($account['label'] ?? '')),
                'account' => trim((string) ($account['account'] ?? '')),
                'password' => (string) ($account['password'] ?? ''),
                'region' => trim((string) ($account['region'] ?? '')),
                'note' => trim((string) ($account['note'] ?? '')),
                'enabled' => (bool) ($account['enabled'] ?? true),
            ];
            return $normalized['enabled'] && $normalized['account'] !== '' && $normalized['password'] !== ''
                ? $normalized
                : null;
        }, $accounts)));
    }

    private function formatAppleIdList(array $accounts): string
    {
        $items = array_map(function (array $account) {
            $title = $account['label'] ?: 'Apple ID';
            $meta = array_filter([
                $account['region'] ? '区域：' . e($account['region']) : '',
                $account['note'] ? '备注：' . e($account['note']) : '',
            ]);

            return '<div class="v2board-apple-id">'
                . '<div><strong>' . e($title) . '</strong></div>'
                . '<div>账号：<code>' . e($account['account']) . '</code></div>'
                . '<div>密码：<code>' . e($account['password']) . '</code></div>'
                . (count($meta) ? '<div>' . implode('；', $meta) . '</div>' : '')
                . '</div>';
        }, $accounts);

        return '<div class="v2board-apple-id-list">' . implode('', $items) . '</div>';
    }

    private function applyReplacementRules(string &$body, array $rules): void
    {
        foreach ($rules as $rule) {
            if ($rule['type'] === 'regex') {
                $body = preg_replace($rule['pattern'], $rule['replacement'], $body);
            } else {
                $body = str_replace($rule['search'], $rule['replacement'], $body);
            }
        }
    }
}
