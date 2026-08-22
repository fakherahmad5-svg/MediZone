<?php

namespace App\Modules\Payments\Services;

use App\Core\Enums\AppointmentPaymentMethod;
use App\Core\Enums\AppointmentPaymentStatus;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\TopUpRequestStatus;
use App\Core\Enums\WalletTransactionType;
use App\Core\Enums\WithdrawalRequestStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\Patient;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;

/**
 * PaymentService - منطق نظام المحفظة/الدفع الخاص بالمواعيد بالكامل:
 * خيارات الدفع (أونلاين كامل / كاش+عربون)، نسبة العربون المتغيرة حسب
 * سجل الغياب (no-show) للمريض، وتسوية كل موعد حسب مصيره (اكتمل/انلغى/
 * ما حضر). كل قرش بيتحرك عبر WalletService (credit/debit) فقط.
 *
 * ⚠️ قرارات تصميم (لازم توثيقها للمستخدم):
 * - الدفع منفصل عن الحجز: الحجز (AppointmentBookingService::book) بيضل
 *   متل ما هو (يخلق موعد Scheduled بدون دفع)، والدفع الفعلي بيصير
 *   بطلب منفصل (pay()) بعدين. ما لمسنا شرط checkIn/start الحالي حتى ما
 *   نكسر مسارات الريسبشن/الووك-إن الموجودة.
 * - لو ما في AppointmentPayment للموعد أصلاً (موعد قديم أو ووك-إن)،
 *   دوال التسوية (settleOnCompletion/Cancellation/NoShow) بترجع فوراً
 *   بدون ما تعمل شي - ما بتعطل تدفق الموعد العادي.
 * - عمولة المنصة (٥٪) دايماً محسوبة على السعر الكامل (total_amount)،
 *   وبتضل بمحفظة المنصة نهائياً بكل الحالات (تسوية/استرجاع/غياب).
 */
class PaymentService extends BaseService
{
    public const PLATFORM_FEE_PERCENT = 5.0;
    public const DEPOSIT_PERCENT_DEFAULT = 25;
    public const DEPOSIT_PERCENT_ELEVATED = 50;
    public const NO_SHOW_THRESHOLD_ELEVATED_DEPOSIT = 2; // بعد ٢ غياب: العربون بيصير ٥٠٪
    public const NO_SHOW_THRESHOLD_ONLINE_ONLY = 3;      // من الغياب الثالث: أونلاين بس

    public function __construct(private readonly WalletService $wallets) {}

    public function noShowCount(Patient $patient): int
    {
        return Appointment::where('patient_id', $patient->id)
            ->where('status', AppointmentStatus::NoShow->value)
            ->count();
    }

    public function depositPercentFor(Patient $patient): int
    {
        return $this->noShowCount($patient) >= self::NO_SHOW_THRESHOLD_ELEVATED_DEPOSIT
            ? self::DEPOSIT_PERCENT_ELEVATED
            : self::DEPOSIT_PERCENT_DEFAULT;
    }

    /** @return AppointmentPaymentMethod[] */
    public function allowedMethodsFor(Patient $patient): array
    {
        if ($this->noShowCount($patient) >= self::NO_SHOW_THRESHOLD_ONLINE_ONLY) {
            return [AppointmentPaymentMethod::FullOnline];
        }

        return [AppointmentPaymentMethod::FullOnline, AppointmentPaymentMethod::CashWithDeposit];
    }

    public function paymentOptions(Patient $patient): array
    {
        return [
            'no_show_count'   => $this->noShowCount($patient),
            'allowed_methods' => array_map(fn ($m) => $m->value, $this->allowedMethodsFor($patient)),
            'deposit_percent' => $this->depositPercentFor($patient),
        ];
    }

    public function pay(Appointment $appointment, User $patientUser, AppointmentPaymentMethod $method): AppointmentPayment
    {
        $patient = $patientUser->patient;

        if (! $patient || $appointment->patient_id !== $patient->id) {
            throw new AuthorizationException('This appointment does not belong to you.');
        }

        if ($appointment->status !== AppointmentStatus::Scheduled) {
            throw new BusinessException('Only scheduled appointments can be paid for.');
        }

        $existing = AppointmentPayment::where('appointment_id', $appointment->id)->first();

        if ($existing && in_array($existing->status, [
            AppointmentPaymentStatus::FullyPaid,
            AppointmentPaymentStatus::DepositPaid,
            AppointmentPaymentStatus::Settled,
        ], true)) {
            throw new BusinessException('This appointment has already been paid for.');
        }

        if (! in_array($method, $this->allowedMethodsFor($patient), true)) {
            throw new BusinessException('This payment method is not available for your account right now.');
        }

        $total = round((float) $appointment->price, 2);

        if ($total <= 0) {
            throw new BusinessException('This appointment has no payable price set.');
        }

        $platformFee = round($total * self::PLATFORM_FEE_PERCENT / 100, 2);
        $doctorAmount = round($total - $platformFee, 2);

        return $this->transaction(function () use (
            $appointment, $patient, $patientUser, $method, $total, $platformFee, $doctorAmount, $existing
        ) {
            $patientWallet = $this->wallets->walletFor($patientUser);
            $platformWallet = $this->wallets->platformWallet();

            if ($method === AppointmentPaymentMethod::FullOnline) {
                $chargeAmount = $total;
                $depositPercent = null;
                $depositAmount = null;
                $status = AppointmentPaymentStatus::FullyPaid;
            } else {
                $depositPercent = $this->depositPercentFor($patient);
                $depositAmount = round($total * $depositPercent / 100, 2);
                $chargeAmount = $depositAmount;
                $status = AppointmentPaymentStatus::DepositPaid;
            }

            // خصم من محفظة المريض، وإيداعه بمحفظة المنصة كأمانة (escrow)
            // لحد ما الموعد يتسوّى (اكتمال/إلغاء/غياب).
            $this->wallets->debit(
                $patientWallet,
                $chargeAmount,
                WalletTransactionType::AppointmentCharge,
                $appointment,
                "Payment for appointment #{$appointment->id}",
                $patientUser
            );

            $this->wallets->credit(
                $platformWallet,
                $chargeAmount,
                WalletTransactionType::AppointmentCharge,
                $appointment,
                "Escrow hold for appointment #{$appointment->id}",
                $patientUser
            );

            $payment = $existing ?? new AppointmentPayment(['appointment_id' => $appointment->id]);
            $payment->fill([
                'method'              => $method->value,
                'status'              => $status->value,
                'total_amount'        => $total,
                'platform_fee_amount' => $platformFee,
                'doctor_amount'       => $doctorAmount,
                'deposit_percent'     => $depositPercent,
                'deposit_amount'      => $depositAmount,
                'amount_paid'         => round(($existing->amount_paid ?? 0) + $chargeAmount, 2),
                'paid_at'             => now(),
            ]);
            $payment->save();

            return $payment->fresh();
        });
    }

    public function settleOnCompletion(Appointment $appointment): void
    {
        $payment = AppointmentPayment::where('appointment_id', $appointment->id)->first();

        if (! $payment || ! in_array($payment->status, [
            AppointmentPaymentStatus::FullyPaid,
            AppointmentPaymentStatus::DepositPaid,
        ], true)) {
            return; // ما في دفع أونلاين مسجل لهالموعد - ما في شي نسوّيه.
        }

        $appointment->loadMissing('doctor.user');

        $this->transaction(function () use ($appointment, $payment) {
            $platformWallet = $this->wallets->platformWallet();
            $doctorWallet = $this->wallets->walletFor($appointment->doctor->user);

            // نصيب الطبيب = المبلغ يلي فعلياً اتقبض عبر المحفظة ناقص عمولة
            // المنصة (المتبقي - لو عربون بس - بيتقبض كاش مباشرة بالعيادة).
            $payout = max(0, round($payment->amount_paid - $payment->platform_fee_amount, 2));

            if ($payout > 0) {
                $this->wallets->debit(
                    $platformWallet, $payout, WalletTransactionType::DoctorEarning,
                    $appointment, "Payout for appointment #{$appointment->id}"
                );
                $this->wallets->credit(
                    $doctorWallet, $payout, WalletTransactionType::DoctorEarning,
                    $appointment, "Earnings for appointment #{$appointment->id}", $appointment->doctor->user
                );
            }

            $payment->update([
                'status'     => AppointmentPaymentStatus::Settled->value,
                'settled_at' => now(),
            ]);
        });
    }

    public function settleOnCancellation(Appointment $appointment, string $cancelledByKind): void
    {
        $payment = AppointmentPayment::where('appointment_id', $appointment->id)->first();

        if (! $payment || ! in_array($payment->status, [
            AppointmentPaymentStatus::FullyPaid,
            AppointmentPaymentStatus::DepositPaid,
        ], true)) {
            return;
        }

        $appointment->loadMissing('patient.user');

        $this->transaction(function () use ($appointment, $payment, $cancelledByKind) {
            $platformWallet = $this->wallets->platformWallet();
            $patientWallet = $this->wallets->walletFor($appointment->patient->user);

            // المريض هو يلي ألغى بالوقت المسموح: بيرجعله المبلغ ناقص عمولة
            // المنصة الثابتة (٥٪ بتضل عند المنصة). أي جهة تانية ألغت
            // (طبيب/ريسبشن/أدمن): المريض مش المسؤول، استرجاع كامل.
            $refund = $cancelledByKind === 'patient'
                ? max(0, round($payment->amount_paid - $payment->platform_fee_amount, 2))
                : $payment->amount_paid;

            if ($refund > 0) {
                $this->wallets->debit(
                    $platformWallet, $refund, WalletTransactionType::AppointmentRefund,
                    $appointment, "Refund for cancelled appointment #{$appointment->id}"
                );
                $this->wallets->credit(
                    $patientWallet, $refund, WalletTransactionType::AppointmentRefund,
                    $appointment, "Refund for cancelled appointment #{$appointment->id}"
                );
            }

            $payment->update([
                'status'          => AppointmentPaymentStatus::Refunded->value,
                'refunded_amount' => $refund,
                'settled_at'      => now(),
            ]);
        });
    }

    public function settleOnNoShow(Appointment $appointment): void
    {
        $payment = AppointmentPayment::where('appointment_id', $appointment->id)->first();

        if (! $payment || ! in_array($payment->status, [
            AppointmentPaymentStatus::FullyPaid,
            AppointmentPaymentStatus::DepositPaid,
        ], true)) {
            return;
        }

        $appointment->loadMissing('doctor.user');

        $this->transaction(function () use ($appointment, $payment) {
            $platformWallet = $this->wallets->platformWallet();
            $doctorWallet = $this->wallets->walletFor($appointment->doctor->user);

            // المريض ما حضر: الطبيب بيحتفظ بكل يلي انقبض (عربون أو كامل)
            // ناقص عمولة المنصة - نفس حساب الاكتمال بالضبط.
            $payout = max(0, round($payment->amount_paid - $payment->platform_fee_amount, 2));

            if ($payout > 0) {
                $this->wallets->debit(
                    $platformWallet, $payout, WalletTransactionType::DoctorEarning,
                    $appointment, "No-show payout for appointment #{$appointment->id}"
                );
                $this->wallets->credit(
                    $doctorWallet, $payout, WalletTransactionType::DoctorEarning,
                    $appointment, "No-show earnings for appointment #{$appointment->id}", $appointment->doctor->user
                );
            }

            $payment->update([
                'status'     => AppointmentPaymentStatus::Forfeited->value,
                'settled_at' => now(),
            ]);
        });
    }

    /**
     * ملخص مالي شامل للأدمن - لوحة "Finance" بالويب. كل الأرقام محسوبة
     * لحظياً من AppointmentPayment/WalletTransaction/TopUpRequest/
     * WithdrawalRequest مباشرة، ما في جدول تجميع (aggregate) منفصل.
     */
    public function financialSummary(): array
    {
        $totalFeesCollected = (float) AppointmentPayment::whereIn('status', [
            AppointmentPaymentStatus::Settled->value,
            AppointmentPaymentStatus::Forfeited->value,
        ])->sum('platform_fee_amount');

        $totalPaidIn = (float) AppointmentPayment::sum('amount_paid');
        $totalRefunded = (float) AppointmentPayment::sum('refunded_amount');

        $totalDoctorPayouts = (float) WalletTransaction::where('type', WalletTransactionType::DoctorEarning->value)
            ->where('amount', '>', 0)
            ->sum('amount');

        $paymentsByStatus = AppointmentPayment::selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $pendingTopUps = TopUpRequest::where('status', TopUpRequestStatus::Pending->value);
        $pendingWithdrawals = WithdrawalRequest::where('status', WithdrawalRequestStatus::Pending->value);

        return [
            'platform_balance'     => (float) $this->wallets->platformWallet()->balance,
            'total_fees_collected' => $totalFeesCollected,
            'total_paid_in'        => $totalPaidIn,
            'total_refunded'       => $totalRefunded,
            'total_doctor_payouts' => $totalDoctorPayouts,
            'payments_by_status'   => $paymentsByStatus,
            'pending_top_up_requests' => [
                'count'  => (clone $pendingTopUps)->count(),
                'amount' => (float) (clone $pendingTopUps)->sum('amount'),
            ],
            'pending_withdrawal_requests' => [
                'count'  => (clone $pendingWithdrawals)->count(),
                'amount' => (float) (clone $pendingWithdrawals)->sum('amount'),
            ],
        ];
    }
}
