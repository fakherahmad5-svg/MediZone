<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Modules\Payments\Requests\PayAppointmentRequest;
use App\Modules\Payments\Resources\AppointmentPaymentResource;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PatientAppointmentPaymentController
 *
 * Routes [auth:sanctum, role:patient]:
 *   GET  /patient/appointments/{id}/payment-options → options()
 *   POST /patient/appointments/{id}/pay             → pay()
 */
class PatientAppointmentPaymentController extends BaseController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function options(Request $request, int $appointmentId): JsonResponse
    {
        $patient = $request->user()->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $this->successResponse(
            $this->payments->paymentOptions($patient),
            'Payment options retrieved successfully.'
        );
    }

    public function pay(PayAppointmentRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = Appointment::find($appointmentId);

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        $payment = $this->payments->pay(
            $appointment,
            $request->user(),
            AppointmentPaymentMethod::from($request->validated('method'))
        );

        return $this->successResponse(new AppointmentPaymentResource($payment), 'Payment recorded successfully.');
    }
}
