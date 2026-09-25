<?php

use App\Models\Shift;
use App\Notifications\ShiftCreatedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;

function fakePublicHolidayApi($pFailing = false): void
{
    Http::fake([
        'https://nagerholidays.com/api/v4/Holidays/*' => $pFailing ? Http::response([], 500) : Http::response([
            [
                'date' => '2026-01-01',
                'localName' => "New Year's Day",
            ],
            [
                'date' => '2026-02-16',
                'localName' => 'Day of Restoration of the State of Lithuania',
            ],
            [
                'date' => '2026-03-11',
                'localName' => 'Day of Restoration of Independence of Lithuania',
            ],
        ], 200),
    ]);
}

beforeEach(function () {
    Cache::flush();
    Notification::fake();
});

function validShiftPayload(array $overrides = []): array
{
    return array_merge([
        'employee_id' => 'employee-123',
        'date' => '2026-10-06',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'country_code' => 'LT',
    ], $overrides);
}

it('creates a shift', function () {
    fakePublicHolidayApi();

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload()
    );

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'employee_id',
                'date',
                'start_time',
                'end_time',
                'country_code',
            ],
        ])
        ->assertJsonPath(
            'data.employee_id',
            'employee-123'
        )
        ->assertJsonPath(
            'data.date',
            '2026-10-06'
        );

    $this->assertDatabaseHas('shifts', [
        'employee_id' => 'employee-123',
        'date' => '2026-10-06',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'country_code' => 'LT',
    ]);
});

it('requires all fields', function () {
    fakePublicHolidayApi();

    $response = $this->postJson('/api/shifts', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'employee_id',
            'date',
            'start_time',
            'end_time',
            'country_code',
        ]);

});

it('requires a non-empty employee id', function () {
    fakePublicHolidayApi();

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'employee_id' => '',
        ])

    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'employee_id',
        ]);

});

it('rejects a whitespace-only employee id', function () {
    fakePublicHolidayApi();

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'employee_id' => '   ',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'employee_id',
        ]);

});

it('requires the date in Y-m-d format', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'date' => '06-10-2026',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date',
        ]);
});

it('rejects an invalid calendar date', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'date' => '2026-02-30',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date',
        ]);
});

it('requires HH:mm time format', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '7:00',
            'end_time' => '8:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
            'end_time',
        ]);
});

it('rejects invalid time values', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '25:00',
            'end_time' => '26:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
            'end_time',
        ]);
});

it('only accepts Lithuania (LT) for the country code', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'country_code' => 'US',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'country_code',
        ]);
});

it('rejects lowercase country codes', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'country_code' => 'lt',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'country_code',
        ]);
});

it('rejects an end time before the start time', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '17:00',
            'end_time' => '09:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_time',
        ]);
});

it('rejects a zero duration shift', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '09:00',
            'end_time' => '09:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_time',
        ]);
});

it('allows a shift of exactly 12 hours', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '09:00',
            'end_time' => '21:00',
        ])
    );

    $response->assertCreated();
});

it('rejects a shift longer than 12 hours', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '09:00',
            'end_time' => '21:01',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'end_time',
        ]);
});

it('rejects a completely overlapping shift', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '10:00',
            'end_time' => '16:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
            'end_time',
        ]);
});

it('rejects a shift that ends during an existing shift', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'start_time' => '10:00',
        'end_time' => '17:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '08:00',
            'end_time' => '11:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
            'end_time',
        ]);
});

it('rejects a shift that completely contains an existing shift', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'start_time',
            'end_time',
        ]);
});

it('allows a shift to start exactly when another shift ends', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '17:00',
            'end_time' => '21:00',
        ])
    );

    $response->assertCreated();

    expect(Shift::count())->toBe(2);
});

it('allows a shift to end exactly when another shift starts', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'start_time' => '17:00',
        'end_time' => '21:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])
    );

    $response->assertCreated();

    expect(Shift::count())->toBe(2);
});

it('allows overlapping shifts for different employees', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'employee_id' => 'employee-123',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'employee_id' => 'employee-456',
            'start_time' => '10:00',
            'end_time' => '16:00',
        ])
    );

    $response->assertCreated();
});

it('allows the same employee to have shifts at the same time on different dates', function () {
    fakePublicHolidayApi();
    Shift::create(validShiftPayload([
        'date' => '2026-10-06',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]));

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'date' => '2026-10-07',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])
    );

    $response->assertCreated();
});

it('rejects a shift on a public holiday', function () {
    fakePublicHolidayApi();
    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'date' => '2026-01-01',
        ])
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date',
        ]);

    expect(Shift::count())->toBe(0);
});

it('returns 503 when the public holiday API is unavailable', function () {
    fakePublicHolidayApi(true);

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload()
    );


    $response
        ->assertStatus(503)
        ->assertJson([
            'message' => 'Public holiday service is currently unavailable.',
        ]);

    expect(Shift::count())->toBe(0);
});

it('sends a notification after creating a shift', function () {
    fakePublicHolidayApi();

    Notification::fake();

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload()
    );

    $response->assertCreated();

    Notification::assertSentOnDemand(
        ShiftCreatedNotification::class
    );
});

it('does not send a notification when shift creation fails', function () {
    fakePublicHolidayApi();

    $response = $this->postJson(
        '/api/shifts',
        validShiftPayload([
            'start_time' => '17:00',
            'end_time' => '09:00',
        ])
    );

    $response->assertUnprocessable();

    Notification::assertNothingSent();
});
