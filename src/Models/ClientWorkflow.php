<?php

namespace Shekel\ShekelLib\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shekel\ShekelLib\Traits\HasClient;
use Shekel\ShekelLib\Traits\UsesUuid;

class ClientWorkflow extends Model
{
    use HasFactory, UsesUuid, HasClient;

    protected $table = 'client_workflows';
    protected $connection = 'admin';
}