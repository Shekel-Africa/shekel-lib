<?php

namespace Shekel\ShekelLib\Traits;

use Illuminate\Support\Facades\Gate;
use Shekel\ShekelLib\Enums\GateEnum;

trait GatePolicies
{
    public function registerShekelGates() {
        Gate::define(GateEnum::EDIT_ACTION, function($user, $post, $reference="user_id") {
            if ($this->gateIsAdmin($user)) {
                return true;
            }
            return $this->gateIsOwner($user, $post, $reference);
        });

        Gate::define(GateEnum::EDIT_ACTION_WITH_COMPANY, function($user, $post, $reference="user_id", $company_reference="company_id") {
            if ($this->gateIsAdmin($user)) {
                return true;
            }
            if ($this->gateBelongToCompany($user, $post, $company_reference)) {
                return true;
            }
            return $this->gateIsOwner($user, $post, $reference);
        });

        Gate::define(GateEnum::ADMIN_ONLY, function ($user){
            return $this->gateIsAdmin($user);
        });

        Gate::define(GateEnum::OWNER_ONLY, function ($user, $post, $reference="user_id") {
            return $this->gateIsOwner($user, $post, $reference);
        });

        Gate::define(GateEnum::OWNER_ONLY_WITH_COMPANY, function ($user, $post, $reference="user_id", $company_reference="company_id") {
            if ($this->gateBelongToCompany($user, $post, $company_reference)) {
                return true;
            }
            return $this->gateIsOwner($user, $post, $reference);
        });
    }

    private function gateIsAdmin($user): bool {
        return strtolower($user->user_type) == 'admin';
    }

    private function gateIsOwner($user, $post, $reference="user_id"): bool {
        return $user->id == $post->$reference;
    }

    private function gateBelongToCompany($user, $post, $company_reference="company_id"): bool {
        if (isset($user->company_id) && $post->$company_reference && $user->company_id == $post->$company_reference) {
            return true;
        }
        return false;
    }
}
