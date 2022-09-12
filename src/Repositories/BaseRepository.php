<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Support\Arr;

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
     * @param string $id
     * @return Collection
     */
    public function get(string $id) {
        return $this->model->findOrFail($id);
    }

    public function edit(string $id, array $data) {
        return $this->model->find($id)
            ->update(Arr::only($data, $this->model->getFillable()));
    }

    public function delete(string $id) {
        $entity = $this->get($id);
        return $entity->delete();
    }
}