<?php

namespace Shekel\ShekelLib\Models\v3;

use Illuminate\Database\Eloquent\Model;
use Shekel\ShekelLib\Traits\HasClient;
use Shekel\ShekelLib\Traits\UsesUuid;

class ActivityLog extends Model
{
    use UsesUuid, HasClient;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url', 'description', 'log', 'headers', 'properties', 'response_data', 'initiator_id',
        'actor_id', 'status', 'ip', 'user_agent', 'device', 'ip_geo_location', 'app_client_id'
    ];
}
