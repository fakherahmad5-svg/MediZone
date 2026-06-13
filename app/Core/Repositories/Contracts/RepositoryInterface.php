<?php

namespace App\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;


interface RepositoryInterface
{

    public function findById(int $id, array $relations = []): ?Model;


    public function findByIdOrFail(int $id, array $relations = []): Model;


    public function all(array $relations = []): Collection;


    public function paginate(int $perPage = 15, array $relations = []): LengthAwarePaginator;

    public function create(array $data): Model;


    public function update(Model $model, array $data): Model;


    public function delete(Model $model): bool;


    public function findWhere(array $conditions, array $relations = []): Collection;


    public function findFirstWhere(array $conditions, array $relations = []): ?Model;
}
