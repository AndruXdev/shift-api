<?php

namespace App\Services;

use App\Models\Shift;
use App\Services\PublicHolidaysService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftCreationService
{
    public function __construct(
        private readonly PublicHolidaysService $publicHolidays,
    ) {
    }

    public function create(array $data): Shift
    {
        $start = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$data['date']} {$data['start_time']}"
        );

        $end = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            "{$data['date']} {$data['end_time']}"
        );

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_time' => 'The end time must be after the start time.',
            ]);
        }

        if ($start->diffInMinutes($end) > 720) {
            throw ValidationException::withMessages([
                'end_time' => 'The shift cannot be longer than 12 hours.',
            ]);
        }

        if ($this->publicHolidays->isHoliday(
            $data['country_code'],
            $start
        )) {
            throw ValidationException::withMessages([
                'date' => 'The selected date is a public holiday.',
            ]);
        }

        $overlapExists = Shift::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_time' => 'The shift overlaps an existing shift.',
                'end_time' => 'The shift overlaps an existing shift.',
            ]);
        }

        return DB::transaction(
            fn () => Shift::create($data)
        );
    }
}
