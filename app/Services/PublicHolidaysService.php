<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Exceptions\PublicHolidayServiceUnavailableException;

class PublicHolidaysService
{
    public function isHoliday(
        string $countryCode,
        CarbonImmutable $date
    ) : bool {
        $holidays = $this->holidays(
            $countryCode,
            $date->year
        );

        return collect($holidays)
            ->contains(
                fn(array $holiday) => ($holiday['date'] ?? null) === $date->format('Y-m-d')
            );
    }

    private function holidays(
        string $countryCode,
        int $year
    ): array {
        return Cache::remember(
            "public-holidays-{$countryCode}-{$year}",
            now()->addDay(),
            function () use ($countryCode, $year) {
                try {
                $response = Http::acceptJson()
                    ->timeout(5)
                    ->retry(2, 100)
                    ->get(
                        "https://nagerholidays.com/api/v4/Holidays/{$countryCode}/{$year}"
                    );

                } catch (\Exception $e) {
                    logger()->error('Exception occurred while fetching public holidays.', [
                        'country_code' => $countryCode,
                        'year' => $year,
                        'message' => $e->getMessage(),
                    ]);
                    throw new PublicHolidayServiceUnavailableException();
                }
                if ($response->failed()) {
                    logger()->error('Failed to fetch public holidays.', [
                        'country_code' => $countryCode,
                        'year' => $year,
                        'status' => $response->status(),
                    ]);
                    throw new PublicHolidayServiceUnavailableException();
                }

                return $response->json();
            }
        );
    }
}
