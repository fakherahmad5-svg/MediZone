<?php


namespace Tests\Feature\Phase6;

use App\Modules\Appointments\Requests\CancelAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CancelAppointmentRequestTest extends TestCase
{
    protected function rules(): array
    {
        return (new CancelAppointmentRequest())->rules();
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue((new CancelAppointmentRequest())->authorize());
    }

    public function test_passes_with_no_reason(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_null_reason(): void
    {
        $validator = Validator::make(['reason' => null], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_passes_with_valid_string_reason(): void
    {
        $validator = Validator::make(
            ['reason' => 'Patient requested cancellation due to schedule conflict.'],
            $this->rules()
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_reason_exceeds_max_length(): void
    {
        $validator = Validator::make(
            ['reason' => str_repeat('a', 1001)],
            $this->rules()
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }

    public function test_fails_when_reason_is_not_a_string(): void
    {
        $validator = Validator::make(
            ['reason' => ['not', 'a', 'string']],
            $this->rules()
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }
}