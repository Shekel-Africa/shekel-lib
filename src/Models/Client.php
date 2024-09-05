<?php

namespace Shekel\ShekelLib\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shekel\ShekelLib\Traits\UsesUuid;

class Client extends Model
{
    use HasFactory, UsesUuid;
    protected $connection = 'admin';
}