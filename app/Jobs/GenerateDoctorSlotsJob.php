<?php

namespace App\Jobs;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Modules\Scheduling\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class GenerateDoctorSlotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Doctor $doctor,
        public readonly Clinic $clinic,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    public function handle(SlotGeneratorService $generator): void
    {
        $generator->generate($this->doctor, $this->clinic, $this->from, $this->to);
    }
}
