<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BaseRepository {
    private $model;
    public function __construct($model)
    {
        $this->model = $model;
    }

    public function create(array $data) {
        return $this->model->create(Arr::only($data, $this->model->getFillable()));
    }

    /**
     *
     *
     * @param $id
     * @return Collection
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

    public function lockForUpdate(string $id) {
        $this->validateId($id);
        return $this->model->lockForUpdate()->findOrFail($id);
    }

    private function validateId($id): bool
    {
        if (is_string($id) && !Str::isUuid($id)) {
            abort(400, "Invalid Id");
        }
        return true;
    }
}
