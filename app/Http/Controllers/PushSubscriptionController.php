<?php

namespace App\Http\Controllers;

use App\Actions\PushSubscription\DeletePushSubscriptionAction;
use App\Actions\PushSubscription\StorePushSubscriptionAction;
use App\Http\Requests\PushSubscription\DeletePushSubscriptionRequest;
use App\Http\Requests\PushSubscription\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function __construct(
        private readonly StorePushSubscriptionAction $storePushSubscriptionAction,
        private readonly DeletePushSubscriptionAction $deletePushSubscriptionAction
    ) {
    }

    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        ($this->storePushSubscriptionAction)(
            Auth::user(),
            $request->validated(),
            $request->userAgent()
        );

        return response()->json(['success' => true]);
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        ($this->deletePushSubscriptionAction)(Auth::id(), (string) $request->validated()['endpoint']);

        return response()->json(['success' => true]);
    }
}
