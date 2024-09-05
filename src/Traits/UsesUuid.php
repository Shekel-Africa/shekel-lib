<?php

namespace Shekel\ShekelLib\Traits;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait UsesUuid
{
  protected static function bootUsesUuid(): void
  {
    static::creating(function ($model) {
      if (! $model->getKey()) {
        $model->{$model->getKeyName()} = (string) Str::orderedUuid();
      }
    });
  }

  public function getIncrementing(): bool
  {
      return false;
  }

  public function getKeyType(): string
  {
      return 'string';
  }
}