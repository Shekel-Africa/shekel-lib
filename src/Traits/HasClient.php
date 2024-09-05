<?php

namespace Shekel\ShekelLib\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shekel\ShekelLib\Models\Client;
use Shekel\ShekelLib\Models\Scopes\ClientScope;
use Shekel\ShekelLib\Utils\TenantClient;

/**
 * @mixin Model
 */
trait HasClient
{
    protected static function bootHasClient(): void
    {
        static::creating(function ($model) {
            if (!isset($model->client_id)) {
                $clientId = TenantClient::getClientId();
                if (isset($clientId)) {
                    $model->client_id = $clientId;
                }
            }
        });
        static::addGlobalScope(new ClientScope);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function scopeClientId($q, $clientId)
    {
        return $q->where('client_id', $clientId);
    }

    /**
     * Get the query builder without the scope applied.
     *
     * @return Builder
     */
    public static function withClients(): Builder
    {
        return with(new static)->newQueryWithoutScope(new ClientScope());
    }
}