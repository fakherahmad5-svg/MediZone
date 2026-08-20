<?php

namespace App\Modules\DoctorEngagement\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\DoctorReport
 */
class DoctorReportResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'category'         => $this->category,
            'description'      => $this->description,
            'status'           => $this->status,
            'encounter_id'     => $this->encounter_id,
            'admin_action'     => $this->admin_action,
            'admin_notes'      => $this->admin_notes,
            'patient_feedback' => $this->patient_feedback,
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor?->id,
                'name' => trim("{$this->doctor?->user?->first_name} {$this->doctor?->user?->last_name}"),
            ]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id'   => $this->patient?->id,
                'name' => trim("{$this->patient?->user?->first_name} {$this->patient?->user?->last_name}"),
            ]),
            'reviewed_at' => $this->when($this->reviewed_at, fn () => $this->formatDate($this->reviewed_at)),
            'resolved_at' => $this->when($this->resolved_at, fn () => $this->formatDate($this->resolved_at)),
            'created_at'  => $this->formatDate($this->created_at),
        ];
    }
}
