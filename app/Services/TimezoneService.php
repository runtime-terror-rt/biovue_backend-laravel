<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TimezoneService
{
    /**
     * Map of US State abbreviations and names to timezones
     */
    protected static array $usStateTimezones = [
        // Pacific
        'CA' => 'America/Los_Angeles', 'CALIFORNIA' => 'America/Los_Angeles',
        'WA' => 'America/Los_Angeles', 'WASHINGTON' => 'America/Los_Angeles',
        'OR' => 'America/Los_Angeles', 'OREGON' => 'America/Los_Angeles',
        'NV' => 'America/Los_Angeles', 'NEVADA' => 'America/Los_Angeles',

        // Mountain / Arizona
        'AZ' => 'America/Phoenix',     'ARIZONA' => 'America/Phoenix',
        'CO' => 'America/Denver',      'COLORADO' => 'America/Denver',
        'UT' => 'America/Denver',      'UTAH' => 'America/Denver',
        'NM' => 'America/Denver',      'NEW MEXICO' => 'America/Denver',
        'WY' => 'America/Denver',      'WYOMING' => 'America/Denver',
        'MT' => 'America/Denver',      'MONTANA' => 'America/Denver',
        'ID' => 'America/Denver',      'IDAHO' => 'America/Denver',

        // Central
        'TX' => 'America/Chicago',     'TEXAS' => 'America/Chicago',
        'IL' => 'America/Chicago',     'ILLINOIS' => 'America/Chicago',
        'MN' => 'America/Chicago',     'MINNESOTA' => 'America/Chicago',
        'MO' => 'America/Chicago',     'MISSOURI' => 'America/Chicago',
        'WI' => 'America/Chicago',     'WISCONSIN' => 'America/Chicago',
        'LA' => 'America/Chicago',     'LOUISIANA' => 'America/Chicago',
        'AL' => 'America/Chicago',     'ALABAMA' => 'America/Chicago',
        'MS' => 'America/Chicago',     'MISSISSIPPI' => 'America/Chicago',
        'AR' => 'America/Chicago',     'ARKANSAS' => 'America/Chicago',
        'KS' => 'America/Chicago',     'KANSAS' => 'America/Chicago',
        'NE' => 'America/Chicago',     'NEBRASKA' => 'America/Chicago',
        'IA' => 'America/Chicago',     'IOWA' => 'America/Chicago',
        'OK' => 'America/Chicago',     'OKLAHOMA' => 'America/Chicago',
        'ND' => 'America/Chicago',     'NORTH DAKOTA' => 'America/Chicago',
        'SD' => 'America/Chicago',     'SOUTH DAKOTA' => 'America/Chicago',

        // Eastern
        'NY' => 'America/New_York',    'NEW YORK' => 'America/New_York',
        'FL' => 'America/New_York',    'FLORIDA' => 'America/New_York',
        'PA' => 'America/New_York',    'PENNSYLVANIA' => 'America/New_York',
        'OH' => 'America/New_York',    'OHIO' => 'America/New_York',
        'GA' => 'America/New_York',    'GEORGIA' => 'America/New_York',
        'NC' => 'America/New_York',    'NORTH CAROLINA' => 'America/New_York',
        'MI' => 'America/New_York',    'MICHIGAN' => 'America/New_York',
        'NJ' => 'America/New_York',    'NEW JERSEY' => 'America/New_York',
        'VA' => 'America/New_York',    'VIRGINIA' => 'America/New_York',
        'MA' => 'America/New_York',    'MASSACHUSETTS' => 'America/New_York',
        'IN' => 'America/New_York',    'INDIANA' => 'America/New_York',
        'TN' => 'America/Chicago',     'TENNESSEE' => 'America/Chicago',
        'MD' => 'America/New_York',    'MARYLAND' => 'America/New_York',
        'SC' => 'America/New_York',    'SOUTH CAROLINA' => 'America/New_York',
        'KY' => 'America/New_York',    'KENTUCKY' => 'America/New_York',
        'CT' => 'America/New_York',    'CONNECTICUT' => 'America/New_York',
        'DC' => 'America/New_York',    'DISTRICT OF COLUMBIA' => 'America/New_York',
        'DE' => 'America/New_York',    'DELAWARE' => 'America/New_York',
        'RI' => 'America/New_York',    'RHODE ISLAND' => 'America/New_York',
        'NH' => 'America/New_York',    'NEW HAMPSHIRE' => 'America/New_York',
        'ME' => 'America/New_York',    'MAINE' => 'America/New_York',
        'VT' => 'America/New_York',    'VERMONT' => 'America/New_York',
        'WV' => 'America/New_York',    'WEST VIRGINIA' => 'America/New_York',

        // Alaska & Hawaii
        'AK' => 'America/Anchorage',   'ALASKA' => 'America/Anchorage',
        'HI' => 'Pacific/Honolulu',    'HAWAII' => 'Pacific/Honolulu',
    ];

    /**
     * Resolve timezone for a user based on explicit setting, request headers, profile location, or zipcode
     */
    public static function resolveUserTimezone(?User $user, ?Request $request = null): string
    {
        // 1. Check if passed via HTTP headers or request input
        if ($request) {
            $reqTz = $request->header('X-Timezone') 
                ?? $request->header('Timezone') 
                ?? $request->input('timezone');

            if ($reqTz && static::isValidTimezone($reqTz)) {
                // If user profile doesn't have timezone saved, persist it
                if ($user && $user->profile && empty($user->profile->timezone)) {
                    try {
                        $user->profile->updateQuietly(['timezone' => $reqTz]);
                    } catch (\Throwable $e) {}
                }
                return $reqTz;
            }
        }

        if (!$user) {
            return config('app.timezone', 'UTC');
        }

        $profile = $user->profile;

        // 2. Check user profile stored timezone
        if ($profile && !empty($profile->timezone) && static::isValidTimezone($profile->timezone)) {
            return $profile->timezone;
        }

        // 3. Resolve from profile zipcode or location
        $resolvedTz = null;
        if ($profile) {
            if (!empty($profile->zipcode)) {
                $resolvedTz = static::resolveByZipcode($profile->zipcode);
            }

            if (!$resolvedTz && !empty($profile->location)) {
                $resolvedTz = static::resolveByLocation($profile->location);
            }
        }

        // 4. Default for US users or system fallback
        if ($resolvedTz && static::isValidTimezone($resolvedTz)) {
            if ($profile && empty($profile->timezone)) {
                try {
                    $profile->updateQuietly(['timezone' => $resolvedTz]);
                } catch (\Throwable $e) {}
            }
            return $resolvedTz;
        }

        // Fallback default
        return config('app.timezone', 'America/New_York');
    }

    /**
     * Resolve timezone from location string (e.g. "Los Angeles, CA", "Miami, FL, USA", "Texas")
     */
    public static function resolveByLocation(string $location): ?string
    {
        $clean = strtoupper(trim($location));

        // Check for state names or abbreviations
        foreach (static::$usStateTimezones as $key => $tz) {
            // Match whole word abbreviation (e.g. ", CA" or " CA " or "NY")
            $pattern = '/\b' . preg_quote($key, '/') . '\b/i';
            if (preg_match($pattern, $clean)) {
                return $tz;
            }
        }

        // Check city names
        if (str_contains($clean, 'LOS ANGELES') || str_contains($clean, 'SAN FRANCISCO') || str_contains($clean, 'SEATTLE')) {
            return 'America/Los_Angeles';
        }
        if (str_contains($clean, 'CHICAGO') || str_contains($clean, 'DALLAS') || str_contains($clean, 'HOUSTON') || str_contains($clean, 'AUSTIN')) {
            return 'America/Chicago';
        }
        if (str_contains($clean, 'DENVER') || str_contains($clean, 'PHOENIX') || str_contains($clean, 'SALT LAKE')) {
            return 'America/Denver';
        }
        if (str_contains($clean, 'NEW YORK') || str_contains($clean, 'MIAMI') || str_contains($clean, 'ATLANTA') || str_contains($clean, 'BOSTON') || str_contains($clean, 'WASHINGTON')) {
            return 'America/New_York';
        }

        // If explicitly mentions USA / US / United States, default to Eastern Time
        if (str_contains($clean, 'USA') || str_contains($clean, 'UNITED STATES') || str_contains($clean, 'U.S.')) {
            return 'America/New_York';
        }

        return null;
    }

    /**
     * Resolve timezone from US 5-digit zipcode prefix
     */
    public static function resolveByZipcode(string $zipcode): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $zipcode);
        if (strlen($digits) < 3) {
            return null;
        }

        $prefix3 = (int) substr($digits, 0, 3);

        if ($prefix3 >= 995) return 'America/Anchorage'; // Alaska
        if ($prefix3 >= 967 && $prefix3 <= 968) return 'Pacific/Honolulu'; // Hawaii
        if ($prefix3 >= 900 && $prefix3 <= 994) return 'America/Los_Angeles'; // CA, OR, WA
        if ($prefix3 >= 890 && $prefix3 <= 898) return 'America/Los_Angeles'; // NV
        if ($prefix3 >= 850 && $prefix3 <= 865) return 'America/Phoenix';     // AZ
        if ($prefix3 >= 800 && $prefix3 <= 847) return 'America/Denver';      // CO, WY, UT, NM
        if ($prefix3 >= 870 && $prefix3 <= 884) return 'America/Denver';      // NM
        if ($prefix3 >= 500 && $prefix3 <= 799) return 'America/Chicago';     // Central
        if ($prefix3 >= 005 && $prefix3 <= 499) return 'America/New_York';    // Eastern

        return 'America/New_York';
    }

    /**
     * Check if a timezone identifier is valid
     */
    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Get current Carbon time for a user
     */
    public static function nowForUser(?User $user, ?Request $request = null): Carbon
    {
        $tz = static::resolveUserTimezone($user, $request);
        return Carbon::now($tz);
    }

    /**
     * Get today's date string (Y-m-d) in the user's timezone
     */
    public static function todayForUser(?User $user, ?Request $request = null): string
    {
        return static::nowForUser($user, $request)->toDateString();
    }
}
