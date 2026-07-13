<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\NodeResource;
use App\Models\Server;

class ServerController extends Controller
{
    public function fetch()
    {
        $servers = Server::query()
            ->select([
                'id',
                'type',
                'version',
                'name',
                'tags',
                'rate',
                'rate_time_enable',
                'rate_time_ranges',
                'sort',
                'show',
                'updated_at',
            ])
            ->where('show', true)
            ->orderBy('sort', 'ASC')
            ->get()
            ->each(function (Server $server) {
                $server->rate = $server->getCurrentRate();
            })
            ->append(['last_check_at', 'is_online', 'cache_key']);

        return $this->success(NodeResource::collection($servers));
    }
}
