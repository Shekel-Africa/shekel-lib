<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Shekel\ShekelLib\Models\Client;

class ClientRepository extends BaseRepository
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    public static function getClient(string $id)
    {
        return Cache::remember($id, 14440, function () use ($id) {
            return $this->get($id);
        });
    }
}