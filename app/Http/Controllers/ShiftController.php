<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Models\Shift;
use App\Notifications\ShiftCreatedNotification;
use App\Services\ShiftCreationService;
use App\Http\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftCreationService $shiftCreationService,
    ) {
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftCreationService->create(
            $request->validated()
        );

        Notification::route(
            'mail',
            config('shift.notifications.email')
        )->notify(
            new ShiftCreatedNotification($shift)
        );

        return ShiftResource::make($shift)
            ->response()
            ->setStatusCode(201);
    }
}
