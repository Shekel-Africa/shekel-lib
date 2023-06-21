<?php

namespace Shekel\ShekelLib\Traits;

use Illuminate\Support\Facades\Gate;

trait GatePolicies
{
    public function registerShekelGates() {
        Gate::define('edit-action', function($user, $post, $reference="user_id") {
            //allow for admin
            if (strtolower($user->user_type) == 'admin') {
                return true;
            }
            return $user->id == $post->$reference;
        });
    }
}
