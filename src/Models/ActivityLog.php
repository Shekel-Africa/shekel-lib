<?php

namespace Shekel\ShekelLib\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
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
        'actor_id', 'status', 'ip'
    ];
}
