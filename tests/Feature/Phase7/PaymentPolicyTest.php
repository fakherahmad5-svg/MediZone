<?php

namespace Tests\Feature\Phase7;

use App\Models\Payment;
use App\Models\Patient;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    public function test_patient_can_view_their_own_payment(): void
    {
        $patient = new Patient();
        $patient->id = 10;

        $user = $this->getMockBuilder(User::class)
            ->onlyMethods([
                'isSuperAdmin',
                'hasRole',
                'hasPermission',
            ])
            ->getMock();

        $user->method('isSuperAdmin')
            ->willReturn(false);

        $user->method('hasRole')
            ->with('patient')
            ->willReturn(true);

        $user->method('hasPermission')
            ->with('invoices.view')
            ->willReturn(true);

        $user->setRelation('patient', $patient);

        $payment = new Payment();
        $payment->patient_id = 10;

        $policy = new PaymentPolicy();

        $this->assertTrue(
            $policy->view($user, $payment)
        );
    }

    public function test_patient_cannot_view_another_patient_payment(): void
    {
        $patient = new Patient();
        $patient->id = 10;

        $user = $this->getMockBuilder(User::class)
            ->onlyMethods([
                'isSuperAdmin',
                'hasRole',
                'hasPermission',
            ])
            ->getMock();

        $user->method('isSuperAdmin')
            ->willReturn(false);

        $user->method('hasRole')
            ->with('patient')
            ->willReturn(true);

        $user->method('hasPermission')
            ->with('invoices.view')
            ->willReturn(true);

        $user->setRelation('patient', $patient);

        $payment = new Payment();
        $payment->patient_id = 99;

        $policy = new PaymentPolicy();

        $this->assertFalse(
            $policy->view($user, $payment)
        );
    }

    public function test_payment_cannot_be_updated_directly(): void
    {
        $user = new User();
        $payment = new Payment();

        $policy = new PaymentPolicy();

        $this->assertFalse(
            $policy->update($user, $payment)
        );
    }

    public function test_payment_cannot_be_deleted_directly(): void
    {
        $user = new User();
        $payment = new Payment();

        $policy = new PaymentPolicy();

        $this->assertFalse(
            $policy->delete($user, $payment)
        );
    }

    public function test_payment_cannot_be_restored_directly(): void
    {
        $user = new User();
        $payment = new Payment();

        $policy = new PaymentPolicy();

        $this->assertFalse(
            $policy->restore($user, $payment)
        );
    }

    public function test_payment_cannot_be_force_deleted_directly(): void
    {
        $user = new User();
        $payment = new Payment();

        $policy = new PaymentPolicy();

        $this->assertFalse(
            $policy->forceDelete($user, $payment)
        );
    }
}