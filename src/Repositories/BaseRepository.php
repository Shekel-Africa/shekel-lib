<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;

/**
 * @phpstan-param
 * @template M of Model
 * @phpstan-template M of Model&Builder
 */
class BaseRepository {
    /**
     * @var M
     */
    protected $model;
    public function __construct(Model|Builder $model)
    {
        $this->model = $model;
    }

    public function create(array $data) {
        return $this->model->create(Arr::only($data, $this->model->getFillable()));
    }

    /**
     * @param $id
     * @return M
     */
    public function get($id) {
        $this->validateId($id);
        return $this->model->findOrFail($id);
    }

    public function edit($id, array $data) {
        $this->validateId($id);
        return $this->model->find($id)
            ->update(Arr::only($data, $this->model->getFillable()));
    }

    public function delete(string $id) {
        $entity = $this->get($id);
        return $entity->delete();
    }

    /**
     * @param string $id
     * @return M
     * @throws ModelNotFoundException|\Exception
     */
    public function lockForUpdate(string $id) {
        $this->validateId($id);
        return $this->model->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param $id
     * @return bool
     * @throws \Exception
     */
    private function validateId($id): bool
    {
        if (is_string($id) && !Str::isUuid($id)) {
            throw new ShekelInvalidArgumentException('Invalid Id', 400);
        }
        return true;
    }
}
