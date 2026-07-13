<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function generateAuthData(?Request $request = null): array
    {
        $request ??= request();
        $userAgent = $request->userAgent();
        $token = $this->user->createToken(
            self::describeDevice($userAgent),
            ['*'],
            now()->addYear()
        );
        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
        ])->save();

        $tokenParts = explode('|', $token->plainTextToken);
        $formattedToken = 'Bearer ' . ($tokenParts[1] ?? $tokenParts[0]);

        return [
            'token' => $this->user->token,
            'auth_data' => $formattedToken,
            'is_admin' => $this->user->is_admin,
        ];
    }

    public function getSessions(?Request $request = null): array
    {
        $currentToken = $this->user->currentAccessToken();
        if ($request && $currentToken instanceof PersonalAccessToken && (!$currentToken->ip_address || !$currentToken->user_agent)) {
            $currentToken->forceFill([
                'ip_address' => $currentToken->ip_address ?: $request->ip(),
                'user_agent' => $currentToken->user_agent ?: $request->userAgent(),
            ])->save();
        }

        if ($currentToken instanceof PersonalAccessToken) {
            $this->user->tokens()
                ->where('id', '!=', $currentToken->id)
                ->where(function ($query): void {
                    $query->whereNull('ip_address')->orWhereNull('user_agent');
                })
                ->delete();
        }

        return $this->user->tokens()->latest('last_used_at')->latest('created_at')->get()->map(function (PersonalAccessToken $token) use ($currentToken): array {
            return [
                'id' => $token->id,
                'device' => $token->user_agent ? self::describeDevice($token->user_agent) : '历史会话',
                'ip' => $token->ip_address,
                'last_login_at' => $token->last_used_at->timestamp ?? $token->created_at->timestamp,
                'created_at' => $token->created_at->timestamp,
                'current' => $currentToken instanceof PersonalAccessToken && $currentToken->id === $token->id,
            ];
        })->all();
    }

    public function removeSession(string $sessionId): bool
    {
        $this->user->tokens()->where('id', $sessionId)->delete();
        return true;
    }

    public function removeAllSessions(): bool
    {
        $this->user->tokens()->delete();
        return true;
    }

    public static function findUserByBearerToken(string $bearerToken): ?User
    {
        $token = str_replace('Bearer ', '', $bearerToken);
        
        $accessToken = PersonalAccessToken::findToken($token);
        
        $tokenable = $accessToken?->tokenable;
        
        return $tokenable instanceof User ? $tokenable : null;
    }

    private static function describeDevice(?string $userAgent): string
    {
        $agent = (string) $userAgent;
        $browser = match (true) {
            preg_match('/Edg\//i', $agent) === 1 => 'Edge',
            preg_match('/(?:Chrome|CriOS)\//i', $agent) === 1 => 'Chrome',
            preg_match('/(?:Firefox|FxiOS)\//i', $agent) === 1 => 'Firefox',
            preg_match('/Version\/.+Safari\//i', $agent) === 1 => 'Safari',
            default => '未知浏览器',
        };
        $system = match (true) {
            preg_match('/iPad|iPhone|iPod/i', $agent) === 1 => 'iOS',
            preg_match('/Android/i', $agent) === 1 => 'Android',
            preg_match('/Windows/i', $agent) === 1 => 'Windows',
            preg_match('/Macintosh|Mac OS X/i', $agent) === 1 => 'macOS',
            preg_match('/Linux/i', $agent) === 1 => 'Linux',
            default => '未知系统',
        };

        return $browser . ' · ' . $system;
    }

    /**
     * 解密认证数据
     *
     * @param string $authorization
     * @return array|null 用户数据或null
     */
    public static function decryptAuthData(string $authorization): ?array
    {
        $user = self::findUserByBearerToken($authorization);
        
        if (!$user) {
            return null;
        }
        
        return [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => (bool)$user->is_admin,
            'is_staff' => (bool)$user->is_staff
        ];
    }
}
