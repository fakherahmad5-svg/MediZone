<?php

namespace App\Modules\Departments\Services;

use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;


class DepartmentService extends BaseService
{

    public function all(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Department::query()
            ->withCount(['doctors', 'clinics'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Department
    {
        $department = Department::find($id);

        if (! $department) {
            throw new NotFoundException('Department not found.');
        }

        return $department;
    }


    public function create(array $data): Department
    {
        return $this->transaction(function () use ($data) {
            if (Department::where('name', $data['name'])->exists()) {
                throw new ConflictException('A department with this name already exists.');
            }

            return Department::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
        });
    }


    public function update(Department $department, array $data): Department
    {
        return $this->transaction(function () use ($department, $data) {
            if (
                isset($data['name'])
                && $data['name'] !== $department->name
                && Department::where('name', $data['name'])->exists()
            ) {
                throw new ConflictException('A department with this name already exists.');
            }

            $department->update([
                'name'        => $data['name'] ?? $department->name,
                'description' => $data['description'] ?? $department->description,
            ]);

            return $department->fresh();
        });
    }


    public function delete(Department $department): void
    {
        $inUse = DB::table('clinic_departments')->where('department_id', $department->id)->exists()
            || DB::table('doctor_departments')->where('department_id', $department->id)->exists();

        if ($inUse) {
            throw new ConflictException(
                'This department is currently linked to clinics or doctors and cannot be deleted.'
            );
        }

        $department->delete();
    }
}
