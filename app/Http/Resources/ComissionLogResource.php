<?php

namespace App\Http\Resources;

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
            "invited_user" => $this->whenLoaded('invitedUser', fn () => [
                'email' => $this->maskEmail($this->invitedUser?->email),
                'invite_code' => $this->invitedUser?->invite_code,
                'joined_at' => $this->invitedUser?->created_at,
            ])
        ];
    }

    private function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) return null;
        [$name, $domain] = explode('@', $email, 2);
        $visible = substr($name, 0, 6);
        return $visible . str_repeat('*', max(2, strlen($name) - strlen($visible))) . '@' . $domain;
    }
}
