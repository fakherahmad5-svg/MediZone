<?php

namespace App\Modules\Payments\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\Payment;
use App\Modules\Payments\Resources\PaymentResource;
use App\Modules\Payments\Services\PaymentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PaymentController
 *
 * Read-only. Payment records are created and mutated exclusively by
 * StripePaymentService / StripeWebhookController — see PaymentPolicy,
 * which already forbids update/delete/restore/forceDelete entirely.
 *
 * Routes [auth:sanctum] — no role middleware, since PaymentPolicy
 * itself is the single source of truth for who can see what; scoping
 * by role happens inside PaymentQueryService.
 *
 * GET /payments      → index()
 * GET /payments/{id} → show()
 */
class PaymentController extends BaseController
{
    public function __construct(
        private readonly PaymentQueryService $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $filters = $request->only(['status']);

        return $this->paginatedResponse(
            $this->queries->forUser(
                $request->user(),
                $filters,
                paginate_per_page()
            ),
            'Payments retrieved successfully.',
            fn ($payment) => (new PaymentResource($payment))
                ->resolve($request)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payment = $this->queries->findForUser($request->user(), $id);

        $this->authorize('view', $payment);

        return $this->successResponse(
            new PaymentResource($payment),
            'Payment retrieved successfully.'
        );
    }
}