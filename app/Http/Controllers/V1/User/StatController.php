<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficLogResource;
use App\Models\StatUser;
use App\Services\StatisticalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function getTrafficLog(Request $request)
    {
        $startDate = now()->startOfMonth();
        if ($request->filled('days')) {
            $days = min(max($request->integer('days'), 1), 31);
            $startDate = now()->startOfDay()->subDays($days - 1);
        }

        $records = StatUser::query()
            ->where('user_id', $request->user()->id)
            ->where('record_at', '>=', $startDate->timestamp)
            ->orderBy('record_at', 'DESC')
            ->get();

        $data = TrafficLogResource::collection(collect($records));
        return $this->success($data);
    }
}
