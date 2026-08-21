<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Models\DeviceToken;
use App\Modules\Notifications\Requests\StoreDeviceTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DeviceTokenController extends BaseController
{

    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id'  => $request->user()->id,
                'platform' => $request->validated('platform'),
            ]
        );

        return $this->successResponse(['id' => $token->id], 'Device token registered successfully.');
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return $this->successResponse(message: 'Device token removed successfully.');
    }
}
