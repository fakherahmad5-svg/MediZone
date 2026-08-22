<?php

namespace Database\Seeders;

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ClinicStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\DoctorVerificationStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ScheduleConfig;
use App\Models\ScheduleDay;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Payments\Services\TopUpService;
use App\Modules\Payments\Services\WithdrawalService;
use App\Modules\Scheduling\Services\SlotGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * WalletSeeder - small, realistic dataset for the wallet/payment system.
 *
 * Self-contained on purpose: UserSeeder is stale against the current
 * schema (it references a clinics.email column and a doctors
 * department_id/license_number/avg_rating set that no longer exist, and
 * a "guardians" table that was never migrated), which is presumably why
 * it's commented out of DatabaseSeeder already. Rather than resurrect
 * it, this seeder creates its own minimal, schema-accurate users so it
 * runs cleanly on its own.
 *
 * Uses the real WalletService/PaymentService/TopUpService/
 * WithdrawalService for every money movement, so every wallet balance,
 * ledger entry, and request status is exactly what the app itself would
 * have produced - not hand-computed numbers that could drift from the
 * business logic.
 *
 * Login as any seeded account with password "password":
 *   admin@vmc.sa      - admin
 *   doctor1@vmc.sa     - doctor (earned 332.50, one pending withdrawal)
 *   doctor2@vmc.sa     - doctor (earned 60.00, withdrawal already approved)
 *   patient1@vmc.sa     - patient (topped up 1000, one pending top-up request)
 *   patient2@vmc.sa     - patient (topped up 500, one rejected top-up request)
 */
class WalletSeeder extends Seeder
{
    public function run(): void
    {
        // ⚠️ الطلب الأصلي (patient1@vmc.sa exists) كان بيوقف السكربت بالكامل
        // على أول تشغيل ثاني، حتى لو الجزء الناقص (جدول الطبيب/ربطه
        // بالعيادة) لسا ما انعمل. هلق منفصل: نداءات المال/المواعيد (مو
        // idempotent - بتكرر نفسها لو انعادت) بس هي يلي بتنوقف، أما ربط
        // الطبيب بالعيادة وجدوله (كلها firstOrCreate) بتنعاد كل مرة بأمان
        // حتى تصلح بيانات قديمة ناقصة زي هاي.
        $alreadySeeded = User::where('email', 'patient1@vmc.sa')->exists();

        $payments = app(PaymentService::class);
        $topUps = app(TopUpService::class);
        $withdrawals = app(WithdrawalService::class);

        // ✅ إصلاح: لازم تكون العيادة "active" وإلا الطبيب ما بيظهر أبداً
        // بلائحة الأطباء (DoctorSearchService بيفلتر
        // whereHas('clinics', status=active)) - أي عيادة تانية (pending
        // مثلاً) كانت ممكن تخلي كل شي تاني بالسيدر يشتغل بصمت بدون ما
        // الطبيب يظهر فعلياً.
        $clinic = Clinic::query()->where('status', ClinicStatus::Active->value)->first()
            ?? Clinic::query()->firstOrFail();

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();

        $admin = $this->makeUser('System', 'Admin', 'admin@vmc.sa', '+966500000001', '1985-01-15', 'male');
        $doctor1User = $this->makeUser('Ahmed', 'Al-Rashid', 'doctor1@vmc.sa', '+966500000002', '1980-06-20', 'male');
        $doctor2User = $this->makeUser('Sara', 'Al-Harbi', 'doctor2@vmc.sa', '+966500000003', '1988-03-10', 'female');
        $patient1User = $this->makeUser('Khalid', 'Al-Otaibi', 'patient1@vmc.sa', '+966500000004', '1992-11-05', 'male');
        $patient2User = $this->makeUser('Noura', 'Al-Harbi', 'patient2@vmc.sa', '+966500000005', '1995-08-22', 'female');

        ClinicUser::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'user_id' => $admin->id],
            ['role_id' => $adminRole->id]
        );
        ClinicUser::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'user_id' => $doctor1User->id],
            ['role_id' => $doctorRole->id]
        );
        ClinicUser::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'user_id' => $doctor2User->id],
            ['role_id' => $doctorRole->id]
        );

        $doctor1 = Doctor::query()->firstOrCreate(
            ['user_id' => $doctor1User->id],
            ['verification_status' => DoctorVerificationStatus::Verified->value, 'practice_start_date' => '2013-01-01']
        );
        $doctor2 = Doctor::query()->firstOrCreate(
            ['user_id' => $doctor2User->id],
            ['verification_status' => DoctorVerificationStatus::Verified->value, 'practice_start_date' => '2017-06-01']
        );

        $patient1 = Patient::query()->firstOrCreate(['user_id' => $patient1User->id], ['blood_type' => 'O+']);
        $patient2 = Patient::query()->firstOrCreate(['user_id' => $patient2User->id], ['blood_type' => 'A+']);

        // ── Doctor↔clinic pivot + weekly schedule + real bookable slots ──
        // This is what earlier runs of this seeder were missing entirely:
        // ClinicUser (above) is only an RBAC/membership row, not the
        // `doctor_clinics` pivot the public doctor list actually queries via
        // Doctor::clinics(); and without at least one active
        // ScheduleConfig+ScheduleDay+ScheduleSession, the listing's second
        // whereHas('scheduleConfigs', is_active) also excludes the doctor.
        // Always runs (every step is firstOrCreate-based) so it also repairs
        // a database that was already seeded before this fix existed.
        $this->setUpDoctorSchedule($doctor1, $clinic, 350.00, 'Cardiology');
        $this->setUpDoctorSchedule($doctor2, $clinic, 300.00, 'Pediatrics');

        if ($alreadySeeded) {
            $this->command?->info('WalletSeeder: wallet/appointment data already present - only re-checked doctor clinic/schedule setup.');
            $this->printCredentials();

            return;
        }

        // ── 1. Fund patient wallets via approved top-up requests ───────
        // Patient 1 gets enough to pay for two appointments below
        // (350 + 300); patient 2 gets enough for one deposit + one full
        // payment (75 + 350). Both also get a second request left in a
        // different state so the wallet/admin UIs have something to show
        // beyond a single pending row.
        $p1TopUp1 = $topUps->request($patient1User, 1000);
        $topUps->approve($p1TopUp1, $admin);

        $topUps->request($patient1User, 200); // left pending - for admin to approve/reject

        $p2TopUp1 = $topUps->request($patient2User, 500);
        $topUps->approve($p2TopUp1, $admin);

        $p2TopUp2 = $topUps->request($patient2User, 150);
        $topUps->reject($p2TopUp2, $admin, 'Amount does not match the bank transfer received.');

        // ── 2. Four appointments covering the four payment outcomes ────
        // A) Completed & settled — doctor keeps price minus the 5% fee.
        $apptCompleted = Appointment::create([
            'clinic_id'      => $clinic->id,
            'patient_id'     => $patient1->id,
            'doctor_id'      => $doctor1->id,
            'slot_id'        => null,
            'status'         => AppointmentStatus::Scheduled->value,
            'encounter_type' => ConsultationType::InPerson->value,
            'price'          => 350.00,
            'created_by'     => $patient1User->id,
            'notes'          => 'Seed data — completed & settled appointment.',
        ]);
        $payments->pay($apptCompleted, $patient1User, AppointmentPaymentMethod::FullOnline);
        $apptCompleted->update(['status' => AppointmentStatus::Completed->value]);
        $payments->settleOnCompletion($apptCompleted);

        // B) No-show & forfeited — cash-with-deposit, doctor keeps the
        // deposit minus the 5% fee, patient forfeits the rest (never
        // charged since it was cash at the clinic).
        $apptNoShow = Appointment::create([
            'clinic_id'      => $clinic->id,
            'patient_id'     => $patient2->id,
            'doctor_id'      => $doctor2->id,
            'slot_id'        => null,
            'status'         => AppointmentStatus::Scheduled->value,
            'encounter_type' => ConsultationType::InPerson->value,
            'price'          => 300.00,
            'created_by'     => $patient2User->id,
            'notes'          => 'Seed data — no-show, deposit forfeited.',
        ]);
        $payments->pay($apptNoShow, $patient2User, AppointmentPaymentMethod::CashWithDeposit);
        $apptNoShow->update(['status' => AppointmentStatus::NoShow->value]);
        $payments->settleOnNoShow($apptNoShow);

        // C) Patient-cancelled & refunded — refunded in full minus the
        // 5% platform fee, which stays with the app.
        $apptCancelled = Appointment::create([
            'clinic_id'      => $clinic->id,
            'patient_id'     => $patient1->id,
            'doctor_id'      => $doctor2->id,
            'slot_id'        => null,
            'status'         => AppointmentStatus::Scheduled->value,
            'encounter_type' => ConsultationType::InPerson->value,
            'price'          => 300.00,
            'created_by'     => $patient1User->id,
            'notes'          => 'Seed data — cancelled by patient, partially refunded.',
        ]);
        $payments->pay($apptCancelled, $patient1User, AppointmentPaymentMethod::FullOnline);
        $apptCancelled->update([
            'status'               => AppointmentStatus::Cancelled->value,
            'cancellation_reason'  => 'Patient requested cancellation (seed data).',
        ]);
        $payments->settleOnCancellation($apptCancelled, 'patient');

        // D) Still scheduled & fully paid — proves booking+payment is
        // atomic: this appointment exists only because payment succeeded.
        $apptUpcoming = Appointment::create([
            'clinic_id'      => $clinic->id,
            'patient_id'     => $patient2->id,
            'doctor_id'      => $doctor1->id,
            'slot_id'        => null,
            'status'         => AppointmentStatus::Scheduled->value,
            'encounter_type' => ConsultationType::Chat->value,
            'price'          => 350.00,
            'created_by'     => $patient2User->id,
            'notes'          => 'Seed data — upcoming appointment, already paid in full.',
        ]);
        $payments->pay($apptUpcoming, $patient2User, AppointmentPaymentMethod::FullOnline);

        // ── 3. Withdrawal requests (doctor side) ────────────────────────
        // Doctor 1 earned 332.50 from the completed appointment above;
        // doctor 2 earned 60.00 from the no-show forfeiture.
        $withdrawals->request($doctor1User, 100); // left pending - for admin to approve/reject

        $d2Withdrawal = $withdrawals->request($doctor2User, 30);
        $withdrawals->approve($d2Withdrawal, $admin);

        $this->command?->info('WalletSeeder: seeded 5 users, wallets, 4 appointment payments, top-up and withdrawal requests.');
        $this->printCredentials();
    }

    private function printCredentials(): void
    {
        $this->command?->newLine();
        $this->command?->info('Login credentials (password is the same for all of them):');
        $this->command?->table(
            ['Role', 'Email', 'Password'],
            [
                ['admin', 'admin@vmc.sa', 'password'],
                ['doctor', 'doctor1@vmc.sa', 'password'],
                ['doctor', 'doctor2@vmc.sa', 'password'],
                ['patient', 'patient1@vmc.sa', 'password'],
                ['patient', 'patient2@vmc.sa', 'password'],
            ]
        );
    }

    /**
     * يخلق للطبيب كل الصفوف يلي بدونها ما بيظهر بلائحة الأطباء العامة
     * ولا عنده أي وقت فعلي قابل للحجز:
     *   1) doctor_clinics (Doctor::clinics() pivot - مختلف كلياً عن
     *      ClinicUser يلي هو صلاحيات RBAC بس) مع سعر الكشف.
     *   2) schedule_configs + schedule_days (الاثنين-الجمعة) +
     *      schedule_sessions (صباحي/بعد ظهر) - جدول أسبوعي بسيط واقعي.
     *   3) doctor_time_slots الفعلية، عبر نفس SlotGeneratorService يلي
     *      الباك نفسه بيستخدمه لما الطبيب يحفظ جدوله - هيك أي سلوت
     *      ناتج مطابق تماماً لمنطق التطبيق الحقيقي، مش أرقام محسوبة يدوياً.
     *   4) (اختياري) doctor_departments - حتى فلتر التخصص بلائحة الأطباء
     *      يرجع نتيجة فعلية بدل صفر دايماً.
     */
    private function setUpDoctorSchedule(Doctor $doctor, Clinic $clinic, float $consultationFee, ?string $departmentName = null): void
    {
        $doctor->clinics()->syncWithoutDetaching([$clinic->id => ['consultation_fee' => $consultationFee]]);

        // ✅ بدون هاد الربط، فلتر التخصص (department_id) بالباك ما بيرجع
        // ولا طبيب مسيّد أبداً - Doctor::departments() هو pivot
        // doctor_departments مستقل تماماً عن كل شي تاني عم نربطه هون.
        if ($departmentName !== null) {
            $department = Department::query()->where('name', $departmentName)->first();
            if ($department) {
                $doctor->departments()->syncWithoutDetaching([$department->id]);
            }
        }

        $config = ScheduleConfig::query()->firstOrCreate(
            ['doctor_id' => $doctor->id, 'clinic_id' => $clinic->id],
            [
                'consultation_duration' => 30,
                'break_duration'        => 0,
                'buffer_enabled'        => false,
                'max_patients'          => null,
                'is_active'             => true,
            ]
        );

        // Carbon dayOfWeek: 0=Sunday ... 6=Saturday، فـ 1..5 = الاثنين-الجمعة.
        foreach (range(1, 5) as $dayOfWeek) {
            $day = ScheduleDay::query()->firstOrCreate(
                ['schedule_config_id' => $config->id, 'day_of_week' => $dayOfWeek],
                ['is_active' => true]
            );

            ScheduleSession::query()->firstOrCreate(
                ['schedule_day_id' => $day->id, 'session_type' => 'morning'],
                ['start_time' => '09:00', 'end_time' => '13:00', 'is_active' => true]
            );
            ScheduleSession::query()->firstOrCreate(
                ['schedule_day_id' => $day->id, 'session_type' => 'afternoon'],
                ['start_time' => '14:00', 'end_time' => '17:00', 'is_active' => true]
            );
        }

        app(SlotGeneratorService::class)->generate($doctor, $clinic, now(), now()->copy()->addDays(21));
    }

    private function makeUser(string $first, string $last, string $email, string $phone, string $dob, string $gender): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name'  => $last,
                'phone'      => $phone,
                'dob'        => $dob,
                'gender'     => $gender,
                'password'   => Hash::make('password'),
                'status'     => 'active',
            ]
        );
    }
}
