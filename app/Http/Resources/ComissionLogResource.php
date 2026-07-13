<?php

namespace App\Http\Resources;

use App\Models\CommissionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComissionLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=> $this['id'],
            "order_amount" => $this['order_amount'],
            "trade_no" => $this['trade_no'],
            "get_amount" => $this['get_amount'],
            "created_at" => $this['created_at'],
            "invited_user" => $this->whenLoaded('invitedUser', fn () => $this->invitedUserData())
        ];
    }

    private function invitedUserData(): array
    {
        /** @var CommissionLog $log */
        $log = $this->resource;
        $user = $log->invitedUser;

        return [
            'email' => $this->maskEmail($user?->email),
            'invite_code' => $user?->invite_code,
            'joined_at' => $user?->created_at,
        ];
    }

    private function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1) . str_repeat('*', max(2, strlen($name) - 1)) . '@' . $domain;
    }
}
