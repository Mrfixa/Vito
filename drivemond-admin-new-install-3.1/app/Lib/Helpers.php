<?php

use Aws\Exception\AwsException;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Modules\BusinessManagement\Entities\ExternalConfiguration;
use Modules\BusinessManagement\Entities\LandingPageSection;
use Modules\BusinessManagement\Entities\ReferralEarningSetting;
use Modules\Gateways\Entities\Setting;
use Modules\UserManagement\Entities\User;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\BusinessManagement\Entities\FirebasePushNotification;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

if (!function_exists('translate')) {
    function translate(string $key, array $replace = [], ?string $locale = null): array|string|Translator|null
    {
        $locale = $locale ?? app()->getLocale();
        $normalizedKey = removeSpecialCharacters($key);

        try {
            $langFilePath = base_path("resources/lang/$locale/lang.php");
            $translations = include $langFilePath;

            $defaultValue = ucfirst(str_replace('_', ' ', $normalizedKey));
            $translatedValue = str_replace(['{', '}'], [':', ''], $defaultValue);

            if (!array_key_exists($normalizedKey, $translations)) {
                $translations[$normalizedKey] = $locale === 'en'
                    ? $translatedValue
                    : autoTranslator(q: $defaultValue, sl: 'en', tl: $locale);

                $exported = "<?php return " . var_export($translations, true) . ";";
                file_put_contents($langFilePath, $exported);
                $translation = $translations[$normalizedKey];
                foreach ($replace as $k => $v) {
                    $translation = str_replace(":$k", $v, $translation);
                }
                return $translation;
            }

            return trans("lang.$normalizedKey", $replace, $locale);
        } catch (Exception) {
            return trans("lang.$normalizedKey", $replace, $locale);
        }
    }
}
if (!function_exists('defaultLang')) {
    function defaultLang(): string
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif (session()->has('locale')) {
            $lang = session('locale');
        } elseif (businessConfig('system_language', 'language_settings')) {
            $data = businessConfig('system_language', 'language_settings')?->value;
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('locale', $code);
            session()->put('direction', $direction);
            $lang = $code;
        } else {
            $lang = App::getLocale();
        }
        return $lang;
    }
}
if (!function_exists('removeSpecialCharacters')) {
    function removeSpecialCharacters(string|null $text): ?string
    {
        return str_ireplace(['\'', '"', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $text));
    }
}
if (!function_exists('fileUploader')) {
    function fileUploader(string $dir, string $format= APPLICATION_IMAGE_FORMAT, ?UploadedFile $image = null, $oldImage = null): string|false
    {
        if ($image == null) {
            return 'def.png';
        }

        set_time_limit(300);
        $dir = rtrim($dir, '/') . '/';

        if(in_array($format, ['txt', 'rtf', 'doc', 'docx', 'pdf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'log', 'zip', 'mp4', 'mkv', 'avi', 'mov', 'webm']))
        {
            $fileName = date('Y-m-d') . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }

            Storage::disk('public')->putFileAs($dir, $image, $fileName);

            return $fileName;
        }

        $sourcePath = $image->getRealPath();
        $info = getimagesize($sourcePath);
        if (!$info || empty($info['mime'])) {
            return false;
        }
        $mime = strtolower($info['mime']);
        $format = match ($mime) {
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => $format,
        };

        $imageName = Carbon::now()->format('Y-m-d') . '-' . uniqid() . '.' . $format;

        if (!Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        $savePath = storage_path("app/public/{$dir}{$imageName}");

        if ($mime === 'image/gif') {
            return copy($sourcePath, $savePath) ? $imageName : false;
        }

        if ($mime === 'image/webp' && $format === 'webp') {
            return copy($sourcePath, $savePath) ? $imageName : false;
        }

        $gdImage = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default      => false,
        };

        if (!$gdImage) {
            return false;
        }

        if (!imageistruecolor($gdImage)) {
            imagepalettetotruecolor($gdImage);
        }

        if (in_array($mime, ['image/png', 'image/webp'])) {
            imagealphablending($gdImage, false);
            imagesavealpha($gdImage, true);
        }

        $maxSize = 2500;
        $width   = imagesx($gdImage);
        $height  = imagesy($gdImage);

        if ($width > $maxSize || $height > $maxSize) {
            $ratio = min($maxSize / $width, $maxSize / $height);
            $newW  = (int)($width * $ratio);
            $newH  = (int)($height * $ratio);

            $temp = imagecreatetruecolor($newW, $newH);

            if (in_array($mime, ['image/png', 'image/webp'])) {
                imagealphablending($temp, false);
                imagesavealpha($temp, true);
            }

            imagecopyresampled(
                $temp,
                $gdImage,
                0,
                0,
                0,
                0,
                $newW,
                $newH,
                $width,
                $height
            );

            imagedestroy($gdImage);
            $gdImage = $temp;
        }

        $saved = match ($format) {
            'jpg', 'jpeg' => imagejpeg($gdImage, $savePath, 85),
            'png'         => imagepng($gdImage, $savePath, -1),
            'webp'        => imagewebp($gdImage, $savePath, 78),
            default       => false,
        };

        imagedestroy($gdImage);

        if ($saved)
        {
            foreach ((array) $oldImage as $file) {
                if (!empty($file)) {
                    Storage::disk('public')->delete($dir . $file);
                }
            }

            return $imageName;
        }

        return false;
    }
}

if (!function_exists('fileRemover')) {
    function fileRemover(string $dir, ?string $image = null): bool
    {
        if (!isset($image)) return true;

        if (Storage::disk('public')->exists($dir . $image)) Storage::disk('public')->delete($dir . $image);

        return true;
    }
}
if (!function_exists('paginationLimit')) {
    function paginationLimit(): int|string
    {
        return !getSession('pagination_limit') ? 10 : getSession('pagination_limit');
    }
}
if (!function_exists('stepValue')) {
    function stepValue(): float
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;

        return 1 / pow(10, $points);
    }
}
if (!function_exists('businessConfig')) {
    function businessConfig(string $key, ?string $settingsType = null): ?BusinessSetting
    {
        try {
            $config = BusinessSetting::query()
                ->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first();
        } catch (Exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('landingPageConfig')) {
    function landingPageConfig(string $key, ?string $settingsType = null): ?LandingPageSection
    {
        try {
            $config = LandingPageSection::query()
                ->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first();
        } catch (Exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('newBusinessConfig')) {
    function newBusinessConfig($key, ?string $settingsType = null): string|object|array|null
    {
        $businessSettings = Cache::rememberForever(CACHE_BUSINESS_SETTINGS, function () {
            return BusinessSetting::all();
        });

        try {
            $config = $businessSettings->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first()?->value;
        } catch (Exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('referralEarningSetting')) {
    function referralEarningSetting(string $key, ?string $settingsType = null): ?ReferralEarningSetting
    {
        try {
            $config = ReferralEarningSetting::query()
                ->where('key_name', $key)
                ->when($settingsType, function ($query) use ($settingsType) {
                    $query->where('settings_type', $settingsType);
                })
                ->first();
        } catch (Exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('externalConfig')) {
    function externalConfig(string $key): ?ExternalConfiguration
    {
        try {
            $config = ExternalConfiguration::query()
                ->where('key', $key)
                ->first();
        } catch (Exception) {
            return null;
        }
        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('checkExternalConfiguration')) {
    function checkExternalConfiguration(string $externalBaseUrl, int|string $externalToken, int|string $vitoToken): bool
    {
        $activationMode = externalConfig('activation_mode')?->value;
        $martBaseUrl = externalConfig('mart_base_url')?->value;
        $martToken = externalConfig('mart_token')?->value;
        $systemSelfToken = externalConfig('system_self_token')?->value;
        return $activationMode == 1 && $martBaseUrl == $externalBaseUrl && $martToken == $externalToken && $systemSelfToken == $vitoToken;
    }
}
if (!function_exists('checkSelfExternalConfiguration')) {
    function checkSelfExternalConfiguration(): bool
    {
        $activationMode = externalConfig('activation_mode')?->value;
        $martBaseUrl = externalConfig('mart_base_url')?->value;
        $martToken = externalConfig('mart_token')?->value;
        $systemSelfToken = externalConfig('system_self_token')?->value;
        return $activationMode == 1 && $martBaseUrl != null && $martToken != null && $systemSelfToken != null;
    }
}
if (!function_exists('generateReferralCode')) {
    function generateReferralCode(?User $user = null): string
    {
        $refCode = strtoupper(Str::random(10));
        if (User::where('ref_code', $refCode)->exists()) {
            generateReferralCode($user);
        }
        if ($user) {
            $user->ref_code = $refCode;
            $user->save();
        }
        return $refCode;
    }
}
if (!function_exists('responseFormatter')) {
    function responseFormatter(array $constant, string|array|object|null $content = null, int|string|null $limit = null, int|string|null $offset = null, array $errors = []): array
    {
        $data = [
            'total_size' => isset($limit) ? $content->total() : null,
            'limit' => $limit,
            'offset' => $offset,
            'data' => $content,
            'errors' => $errors,
        ];
        $responseConst = [
            'response_code' => $constant['response_code'],
            'message' => translate($constant['message']),
        ];
        return array_merge($responseConst, $data);
    }
}
if (!function_exists('errorProcessor')) {
    function errorProcessor(Illuminate\Contracts\Validation\Validator $validator): array
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => translate($error[0])];
        }

        return $errors;
    }
}
if (!function_exists('autoTranslator')){
    function autoTranslator(string $q, string $sl, string $tl): string
    {
        $placeholders = [];
        $i = 0;
        $q = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)}/', function ($m) use (&$placeholders, &$i) {
            $token = '__PH_' . $i . '__';
            $placeholders[$token] = ":" . $m[1];
            $i++;
            return $token;
        }, $q);

        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=$sl&tl=$tl&dt=t&q=" . urlencode($q);
        $res = file_get_contents($url);
        $res = json_decode($res, true);
        $translated = $res[0][0][0] ?? '';

        foreach ($placeholders as $token => $original) {
            $translated = str_ireplace($token, $original, $translated);
        }

        return $translated;
    }
}
if (!function_exists('getLanguageCode')) {
    function getLanguageCode(string $countryCode): string
    {
        foreach (LANGUAGES as $locale) {
            if ($countryCode == $locale['code']) {
                return $countryCode;
            }
        }
        return "en";
    }

}
if (!function_exists('exportData')) {
    function exportData(array|Generator|\Illuminate\Support\Collection|Collection|Model|null $data, string $file, string $viewPath): View|BinaryFileResponse|StreamedResponse|Response
    {
        return match ($file) {
            'csv' => (new FastExcel($data))->download(time() . '-file.csv'),
            'excel' => (new FastExcel($data))->download(time() . '-file.xlsx'),
            'pdf' => Pdf::loadView($viewPath, ['data' => $data])->download(time() . '-file.pdf'),
            default => view($viewPath, ['data' => $data]),
        };
    }
}
if (!function_exists('logViewerNew')) {
    function logViewerNew(Collection|LengthAwarePaginator $logs, ?string $file = null): View|BinaryFileResponse|StreamedResponse|Response
    {
        if ($file) {
            $data = $logs->map(function ($item) {
                $objects = explode("\\", $item->logable_type);
                return [
                    'edited_date' => date('Y-m-d', strtotime($item->created_at)),
                    'edited_time' => date('h:i A', strtotime($item->created_at)),
                    'email' => $item->users?->email,
                    'edited_object' => end($objects),
                    'before' => json_encode($item?->before),
                    'after' => json_encode($item?->after)
                ];
            });
            return exportData($data, $file, 'adminmodule::log-print');
        }
        return view('adminmodule::activity-log', compact('logs'));
    }
}
if (!function_exists('get_cache')) {
    function get_cache(string $key): array|object|string|null
    {
        if (!Cache::has($key)) {
            $config = businessConfig($key)?->value;
            if (!$config) {
                return null;
            }
            Cache::put($key, $config);
        }
        return Cache::get($key);
    }
}
if (!function_exists('getSession')) {
    function getSession(string $key): array|object|string|bool
    {
        if (!Session::has($key)) {
            $config = businessConfig($key)?->value;
            if (!$config) {
                return false;
            }
            Session::put($key, $config);
        }
        return Session::get($key);
    }
}
if (!function_exists('haversineDistance')) {
    function haversineDistance(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo, int|float $earthRadius = 6371000): float
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}
if (!function_exists('getDateRange')) {
    function getDateRange(array|string $request): array
    {
        if (is_array($request)) {
            return [
                'start' => Carbon::parse($request['start'])->startOfDay(),
                'end' => Carbon::parse($request['end'])->endOfDay(),
            ];
        }

        return match ($request) {
            TODAY => [
                'start' => Carbon::parse(now())->startOfDay(),
                'end' => Carbon::parse(now())->endOfDay()
            ],
            PREVIOUS_DAY => [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            THIS_WEEK => [
                'start' => Carbon::parse(now())->startOfWeek(),
                'end' => Carbon::parse(now())->endOfWeek(),
            ],
            THIS_MONTH => [
                'start' => Carbon::parse(now())->startOfMonth(),
                'end' => Carbon::parse(now())->endOfMonth(),
            ],
            LAST_7_DAYS => [
                'start' => Carbon::today()->subDays(7)->startOfDay(),
                'end' => Carbon::parse(now())->endOfDay(),
            ],
            LAST_WEEK => [
                'start' => Carbon::now()->subWeek()->startOfWeek(),
                'end' => Carbon::now()->subWeek()->endOfWeek(),
            ],
            LAST_MONTH => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth(),
            ],
            THIS_YEAR => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            ALL_TIME => [
                'start' => Carbon::parse(BUSINESS_START_DATE),
                'end' => Carbon::now(),
            ]
        };
    }
}
if (!function_exists('getCustomDateRange')) {
    function getCustomDateRange(string $dateRange): array
    {
        [$startDate, $endDate] = explode(' - ', $dateRange);
        $start = Carbon::createFromFormat('m/d/Y', trim($startDate))->startOfDay();
        $end = Carbon::createFromFormat('m/d/Y', trim($endDate))->endOfDay();

        return ['start' => $start, 'end' => $end];

    }
}
if (!function_exists('configSettings')) {
    function configSettings(string $key, string $settingsType): ?stdClass
    {
        try {
            $config = DB::table('settings')->where('key_name', $key)
                ->where('settings_type', $settingsType)->first();
        } catch (Exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}
if (!function_exists('languageLoad')) {
    function languageLoad(): array
    {
        if (\session()->has(LANGUAGE_SETTINGS)) {
            $language = \session(LANGUAGE_SETTINGS);
        } else {
            $language = businessConfig(SYSTEM_LANGUAGE)?->value;
            \session()->put(LANGUAGE_SETTINGS, $language);
        }
        return $language;
    }

}
if (!function_exists('set_currency_symbol')) {
    function set_currency_symbol(float $amount): string
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        $symbol = getSession('currency_symbol') ?? '$';

        if ($position == 'left') {
            return $symbol . ' ' . number_format($amount, $points);
        }
        return number_format($amount, $points) . ' ' . $symbol;
    }
}
if (!function_exists('getCurrencyFormat')) {
    function getCurrencyFormat(?float $amount): string
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        if (session::has('currency_symbol')) {
            $symbol = session()->get('currency_symbol');
        } else {
            $symbol = businessConfig('currency_symbol', 'business_information')->value ?? "$";
        }

        if ($position == 'left') {
            return $symbol . ' ' . number_format($amount ?? 0, $points);
        } else {
            return number_format($amount ?? 0, $points) . ' ' . $symbol;
        }
    }
}
if (!function_exists('getNotification')) {
    function getNotification(string $key, ?string $group = null, ?string $type = null): array
    {
        $notification = FirebasePushNotification::query()
            ->where('name', $key)
            ->when($group, fn($q) => $q->where('group', $group))
            ->when($type, fn($q) => $q->where('type', $type))
            ->first();

        return [
            'title' => $notification['name'] ?? ' ',
            'description' => $notification['value'] ?? ' ',
            'status' => (bool)$notification['status'] ?? 0,
            'action' => $notification['action'] ?? ' ',
        ];
    }
}
if (!function_exists('getMainDomain')) {
    function getMainDomain(string $url): string
    {
        // Remove protocol from the URL
        $url = preg_replace('#^https?://#', '', $url);

        // Split the URL by slashes
        $parts = explode('/', $url);

        // Extract the domain part
        // Return the subdomain and domain
        return $parts[0];
    }
}
if (!function_exists('getRoutes')) {
    function getRoutes(array $originCoordinates, array $destinationCoordinates, array $intermediateCoordinates = [], array $drivingMode = ["DRIVE"]): array
    {
        $mapApiKey = businessConfig(GOOGLE_MAP_API)?->value['map_api_key_server'] ?? '';
        $url = "https://routes.googleapis.com/directions/v2:computeRoutes";

        $origin = [
            "location" => [
                "latLng" => [
                    "latitude" => $originCoordinates[0],
                    "longitude" => $originCoordinates[1]
                ]
            ]
        ];

        $destination = [
            "location" => [
                "latLng" => [
                    "latitude" => $destinationCoordinates[0],
                    "longitude" => $destinationCoordinates[1]
                ]
            ]
        ];

        // Format waypoints
        $waypoints = [];
        if (!empty($intermediateCoordinates) && !is_null($intermediateCoordinates[0][0])) {
            foreach ($intermediateCoordinates as $wp) {
                $waypoints[] = [
                    "location" => [
                        "latLng" => [
                            "latitude" => $wp[0],
                            "longitude" => $wp[1]
                        ]
                    ]
                ];
            }
        }

        $data = [
            "origin" => $origin,
            "destination" => $destination,
            "intermediates" => $waypoints,
            "travelMode" => $drivingMode[0], // DRIVE, TWO_WHEELER, etc.
            "routingPreference" => "TRAFFIC_AWARE", // Enables traffic-based duration
            "computeAlternativeRoutes" => false,
            "languageCode" => "en-US",
            "units" => "METRIC"
        ];


        // API Headers
        $headers = [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $mapApiKey,
            'X-Goog-FieldMask' => '*'
        ];

        // Send POST request
        $response = Http::withHeaders($headers)->post($url, $data);

        if (!isset($response['routes'][0])) {
            // Fallback to car route
            $data['travelMode'] = 'DRIVE';
            $response = Http::withHeaders($headers)->post($url, $data);
        }

        if ($response->successful()) {
            $result = $response->json();
            if (!isset($result['routes'][0])) {
                return ['error' => 'No route found'];
            }

            $route = $result['routes'][0];
            $encoded_polyline = $route['polyline']['encodedPolyline'] ?? null;
            $distance = $route['distanceMeters'] ?? 0;
            $duration = $route['duration'] ?? '0s';
            $durationInTraffic = $route['staticDuration'] ?? $duration; // Fallback to normal duration if no traffic data

            // Convert duration to seconds
            preg_match('/(\d+)s/i', $duration, $matches);
            $durationSec = isset($matches[1]) ? (int)$matches[1] : 0;

            // Convert traffic duration to seconds
            preg_match('/(\d+)s/i', $durationInTraffic, $trafficMatches);
            $durationInTrafficSec = isset($trafficMatches[1]) ? (int)$trafficMatches[1] : 0;

            $convert_to_bike = 1.2; // Adjustment factor for bike mode

            $responses[0] = [
                'distance' => (double)number_format(($distance / 1000), 2),
                'distance_text' => number_format(($distance / 1000), 2) . ' km',
                'duration' => number_format((($durationSec / 60) / $convert_to_bike), 2) . ' min',
                'duration_sec' => (int)($durationSec / $convert_to_bike),
                'duration_in_traffic' => number_format((($durationInTrafficSec / 60) / $convert_to_bike), 2) . ' min',
                'duration_in_traffic_sec' => (int)($durationInTrafficSec / $convert_to_bike),
                'status' => "OK",
                'drive_mode' => 'TWO_WHEELER',
                'encoded_polyline' => $encoded_polyline,
            ];

            $responses[1] = [
                'distance' => (double)number_format(($distance / 1000), 2),
                'distance_text' => number_format(($distance / 1000), 2) . ' km',
                'duration' => number_format(($durationSec / 60), 2) . ' min',
                'duration_sec' => $durationSec,
                'duration_in_traffic' => number_format(($durationInTrafficSec / 60), 2) . ' min',
                'duration_in_traffic_sec' => $durationInTrafficSec,
                'status' => "OK",
                'drive_mode' => 'DRIVE',
                'encoded_polyline' => $encoded_polyline,
            ];

            return $responses;
        } else {
            return ['error' => 'API request failed', 'status' => $response->status(), 'details' => $response];
        }
    }
}
if (!function_exists('onErrorImage')) {
    function onErrorImage(?string $data = null, ?string $src = null, ?string $error_src = null, ?string $path = null): ?string
    {
        if (isset($data) && strlen($data) > 1 && Storage::disk('public')->exists($path . $data)) {
            return $src;
        }
        return $error_src;
    }
}
if (!function_exists('checkReverbConnection')) {
    function checkReverbConnection(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember('reverb_up', 30, function () {
            $host = env('REVERB_HOST') ?? '127.0.0.1';
            $port = (int)(env('REVERB_PORT') ?? 6001);
            $connection = @fsockopen($host, $port, $errno, $errstr, 2);
            if (is_resource($connection)) {
                fclose($connection);
                return true;
            }
            return false;
        });
    }
}
if (!function_exists('spellOutNumber')) {
    function spellOutNumber(int|float|string $number): string
    {
        $number = strval($number);
        $digits = [
            "zero", "one", "two", "three", "four",
            "five", "six", "seven", "eight", "nine"
        ];
        $tens = [
            "", "", "twenty", "thirty", "forty",
            "fifty", "sixty", "seventy", "eighty", "ninety"
        ];
        $teens = [
            "ten", "eleven", "twelve", "thirteen", "fourteen",
            "fifteen", "sixteen", "seventeen", "eighteen", "nineteen"
        ];

        $result = '';

        if (strlen($number) > 15) {
            $quadrillions = substr($number, 0, -15);
            $number = substr($number, -15);
            $result .= spellOutNumber($quadrillions) . ' quadrillion ';
        }

        if (strlen($number) > 12) {
            $trillions = substr($number, 0, -12);
            $number = substr($number, -12);
            $result .= spellOutNumber($trillions) . ' trillion ';
        }

        if (strlen($number) > 9) {
            $billions = substr($number, 0, -9);
            $number = substr($number, -9);
            $result .= spellOutNumber($billions) . ' billion ';
        }

        if (strlen($number) > 6) {
            $millions = substr($number, 0, -6);
            $number = substr($number, -6);
            $result .= spellOutNumber($millions) . ' million ';
        }

        if (strlen($number) > 3) {
            $thousands = substr($number, 0, -3);
            $number = substr($number, -3);
            $result .= spellOutNumber($thousands) . ' thousand ';
        }

        if (strlen($number) > 2) {
            $hundreds = substr($number, 0, -2);
            $number = substr($number, -2);
            $result .= $digits[intval($hundreds)] . ' hundred ';
        }

        if ($number > 0) {
            if ($number < 10) {
                $result .= $digits[intval($number)];
            } elseif ($number < 20) {
                $result .= $teens[$number - 10];
            } else {
                $result .= $tens[$number[0]];
                if ($number[1] > 0) {
                    $result .= '-' . $digits[intval($number[1])];
                }
            }
        }

        return trim($result);
    }
}
if (!function_exists('abbreviateNumber')) {
    function abbreviateNumber(int|float|string $number): string
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $abbreviations = ['', 'K', 'M', 'B', 'T'];
        $abbreviated_number = $number;
        $abbreviation_index = 0;

        while ($abbreviated_number >= 1000 && $abbreviation_index < count($abbreviations) - 1) {
            $abbreviated_number /= 1000;
            $abbreviation_index++;
        }

        return round($abbreviated_number, $points) . $abbreviations[$abbreviation_index];
    }
}
if (!function_exists('abbreviateNumberWithSymbol')) {
    #TODO
    function abbreviateNumberWithSymbol(int|float|string $number): string
    {
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $position = getSession('currency_symbol_position') ?? 'left';
        if (session::has('currency_symbol')) {
            $symbol = session()->get('currency_symbol');
        } else {
            $symbol = businessConfig('currency_symbol', 'business_information')->value ?? "$";
        }
        $abbreviations = ['', 'K', 'M', 'B', 'T'];
        $abbreviated_number = $number;
        $abbreviation_index = 0;

        while ($abbreviated_number >= 1000 && $abbreviation_index < count($abbreviations) - 1) {
            $abbreviated_number /= 1000;
            $abbreviation_index++;
        }

        if ($position == 'left') {
            return $symbol . ' ' . round($abbreviated_number, $points) . $abbreviations[$abbreviation_index];
        } else {
            return round($abbreviated_number, $points) . $abbreviations[$abbreviation_index] . ' ' . $symbol;
        }

    }
}
if (!function_exists('removeInvalidCharacters')) {
    function removeInvalidCharacters(string $str): string
    {
        return str_ireplace(['\'', '"', ';', '<', '>'], ' ', $str);
    }
}
if (!function_exists('textVariableDataFormat')) {
    function textVariableDataFormat(string $value, int|float|string|null $tipsAmount = null, ?string $levelName = null, int|float|string|null $walletAmount = null, int|float|string|null $tripId = null,
                                    ?string $userName = null, ?string $withdrawNote = null, int|float|string|null $paidAmount = null, ?string $methodName = null,
                                    int|float|string|null $referralRewardAmount = null, int|float|string|null $otp = null, int|float|string|null $parcelId = null, int|float|string|null $approximateAmount = null,
                                    ?string $sentTime = null, ?string $vehicleCategory = null, ?string $reason = null, ?string $dropOffLocation = null,
                                    ?string $customerName = null, ?string $driverName = null, ?string $pickUpLocation = null, ?string $dueTime = null,
                                    int|float|string|null $bonusAmount = null, int|float|string|null $totalAmount = null,
                                    ?string $businessName = null, ?string $locale = null): array|string|Translator|null
    {
        $replace = compact(
            'tipsAmount', 'levelName', 'walletAmount', 'tripId', 'userName', 'withdrawNote',
            'paidAmount', 'methodName', 'referralRewardAmount', 'otp', 'parcelId', 'approximateAmount',
            'sentTime', 'vehicleCategory', 'reason', 'dropOffLocation', 'customerName', 'driverName', 'pickUpLocation', 'dueTime', 'bonusAmount', 'totalAmount', 'businessName'
        );
        return translate(key: $value, replace: array_filter($replace, fn($value) => $value !== null), locale: $locale);
    }
}
if (!function_exists('smsTemplateDataFormat')) {
    function smsTemplateDataFormat(string $value, ?string $customerName = null, int|string|null $parcelId = null, ?string $trackingLink = null): string
    {
        $data = $value;
        if ($value) {
            if ($customerName) {
                $data = str_replace("{CustomerName}", $customerName, $data);
            }
            if ($parcelId) {
                $data = str_replace("{ParcelId}", $parcelId, $data);
            }
            if ($trackingLink) {
                $data = str_replace("{TrackingLink}", $trackingLink, $data);
            }
        }

        return $data;
    }
}
if (!function_exists('checkMaintenanceMode')) {
    function checkMaintenanceMode(): array
    {
        $maintenanceSystemArray = ['user_app', 'driver_app'];
        $selectedMaintenanceSystem = businessConfig('maintenance_system_setup')?->value ?? [];

        $maintenanceSystem = [];
        foreach ($maintenanceSystemArray as $system) {
            $maintenanceSystem[$system] = in_array($system, $selectedMaintenanceSystem) ? 1 : 0;
        }

        $selectedMaintenanceDuration = businessConfig('maintenance_duration_setup')?->value ?? [];
        $maintenanceStatus = (integer)(businessConfig('maintenance_mode')?->value ?? 0);

        $status = 0;
        if ($maintenanceStatus == 1) {
            if (isset($selectedMaintenanceDuration['maintenance_duration']) && $selectedMaintenanceDuration['maintenance_duration'] == 'until_change') {
                $status = $maintenanceStatus;
            } else {
                if (isset($selectedMaintenanceDuration['start_date']) && isset($selectedMaintenanceDuration['end_date'])) {
                    $start = Carbon::parse($selectedMaintenanceDuration['start_date']);
                    $end = Carbon::parse($selectedMaintenanceDuration['end_date']);
                    $today = Carbon::now();
                    if ($today->between($start, $end)) {
                        $status = 1;
                    }
                }
            }
        }

        return [
            'maintenance_status' => $status,
            'selected_maintenance_system' => count($maintenanceSystem) > 0 ? $maintenanceSystem : null,
            'maintenance_messages' => businessConfig('maintenance_message_setup')?->value ?? null,
            'maintenance_type_and_duration' => count($selectedMaintenanceDuration) > 0 ? $selectedMaintenanceDuration : null,
        ];
    }
}
if (!function_exists('insertBusinessSetting')) {
    function insertBusinessSetting(string $keyName, ?string $settingType = null, string|array|null $value = null): bool
    {
        $data = BusinessSetting::where('key_name', $keyName)->where('settings_type', $settingType)->first();
        if (!$data) {
            BusinessSetting::updateOrCreate(['key_name' => $keyName, 'settings_type' => $settingType], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }
}

if (!function_exists('updateBusinessSetting')) {
    function updateBusinessSetting(string $keyName, ?string $settingType = null, string|array|null $value = null): bool
    {
        BusinessSetting::updateOrCreate(['key_name' => $keyName, 'settings_type' => $settingType], [
            'value' => $value,
        ]);

        return true;
    }
}

if (!function_exists('insertLandingPageSection')) {
    function insertLandingPageSection(string $keyName, ?string $settingType = null, string|array|null $value = null): bool
    {
        $data = LandingPageSection::where('key_name', $keyName)->where('settings_type', $settingType)->first();
        if (!$data) {
            LandingPageSection::updateOrCreate(['key_name' => $keyName, 'settings_type' => $settingType], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }
}
if (!function_exists('hexToRgb')) {
    function hexToRgb(string $hex): string
    {
        // Remove the hash at the start if it's there
        $hex = ltrim($hex, '#');

        // If the hex code is in shorthand (3 characters), convert to full form
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convert hex to RGB values
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "$r, $g, $b";
    }
}
if (!function_exists('formatCustomDate')) {
    function formatCustomDate(string|DateTimeInterface $date): string
    {
        $carbonDate = Carbon::parse($date);
        $now = Carbon::now();

        if ($carbonDate->isToday()) {
            return $carbonDate->format('g:i A'); // e.g., 3:53 PM
        } elseif ($carbonDate->isYesterday()) {
            return 'Yesterday';
        } elseif ($carbonDate->diffInDays($now) <= 5) {
            // Returns "X days ago" for dates within the last 5 days
            return (int)$carbonDate->diffInDays($now) . ' days ago';
        } else {
            return $carbonDate->format('d M Y'); // e.g., 17 Nov 2024
        }
    }
}
if (!function_exists('formatCustomDateForTooltip')) {
    function formatCustomDateForTooltip(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        $now = time();

        if (date('Y-m-d', $timestamp) === date('Y-m-d', $now)) {
            return date('h:i A', $timestamp); // Format as 01:43 PM
        }

        $oneWeekAgo = strtotime('-1 week', $now);
        if ($timestamp > $oneWeekAgo) {
            return date('l h:i A', $timestamp);
        }

        return date('d M Y', $timestamp);
    }
}
if (!function_exists('getExtensionIcon')) {
    function getExtensionIcon(string $document): string
    {
        $extension = pathinfo($document, PATHINFO_EXTENSION);
        $asset = dynamicAsset('public/assets/admin-module/img/file-format/svg');
        return match ($extension) {
            'pdf' => $asset . '/pdf.svg',
            'cvc' => $asset . '/cvc.svg',
            'csv' => $asset . '/csv.svg',
            'doc', 'docx' => $asset . '/doc.svg',
            'jpg' => $asset . '/jpg.svg',
            'jpeg' => $asset . '/jpeg.svg',
            'webp' => $asset . '/webp.svg',
            'png' => $asset . '/png.svg',
            'xls' => $asset . '/xls.svg',
            'xlsx' => $asset . '/xlsx.svg',
            default => dynamicAsset('public/assets/admin-module/img/document-upload.png'),
        };
    }
}
if (!function_exists('convertTimeToSecond')) {
    function convertTimeToSecond(int|float|string|null $time, ?string $type): ?float
    {
        if (empty($time))
        {
            return null;
        }

        $time = floatval($time);

        return match (strtolower($type)) {
            'second' => $time,
            'minute' => $time * 60,
            'hour' => $time * 3600,
            'day' => $time * 86400,
            default => null,
        };
    }
}
if (!function_exists('convertToSnakeCaseIfNeeded')) {
    function convertToSnakeCaseIfNeeded(string $string): string
    {
        if (str_contains($string, '-')) {
            return str_replace('-', '_', $string);
        }
        return $string;
    }
}
if (!function_exists('pushSentTime')){
    function pushSentTime(string|DateTimeInterface $time): string
    {
        return Carbon::parse($time)->format('d M Y - h:i A');
    }
}
if (!function_exists('distanceCalculator')) {
    function distanceCalculator(array $data, int|float|string $earthRadius = 6371): float
    {
        $fromLongitude = (float)$data['from_longitude'];
        $fromLatitude = (float)$data['from_latitude'];
        $toLongitude = (float)$data['to_longitude'];
        $toLatitude = (float)$data['to_latitude'];
        $latDifference = deg2rad($toLatitude - $fromLatitude);
        $lonDifference = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latDifference / 2) * sin($latDifference / 2) +
            cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) *
            sin($lonDifference / 2) * sin($lonDifference / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
if (!function_exists('enableCronJobs')) {
    function enableCronJobs(array $commands): void
    {
        try {
            $projectRoot = base_path();
            $existingCronJobs = trim(shell_exec('crontab -l 2>/dev/null'));

            $newCrons = [];
            foreach ($commands as $command) {
                $cron = "* * * * * cd $projectRoot && php artisan $command >> /dev/null 2>&1";
                if (!str_contains($existingCronJobs, "php artisan $command")) {
                    $newCrons[] = $cron;
                } else {
                    info("Cron for '$command' already exists");
                }
            }

            if (!empty($newCrons)) {
                $cronFile = tempnam(sys_get_temp_dir(), 'cron');
                $newCronJobs = $existingCronJobs
                    ? rtrim($existingCronJobs) . PHP_EOL . implode(PHP_EOL, $newCrons) . PHP_EOL
                    : implode(PHP_EOL, $newCrons) . PHP_EOL;

                file_put_contents($cronFile, $newCronJobs);
                $output = [];
                $returnVar = 0;
                exec("crontab $cronFile 2>&1", $output, $returnVar);
                if ($returnVar !== 0 || !empty($output)) {
                    info("crontab exec output: " . implode("\n", $output));
                    info("crontab exec return code: $returnVar");
                }
                unlink($cronFile);

                if ($returnVar === 0) {
                    info("Cron jobs added successfully");
                } else {
                    info("Failed to update cron jobs, return code: $returnVar");
                }
            }
        } catch (Throwable $e) {
            info("Failed to enable cron jobs, error: " . $e->getMessage());
        }
    }
}
if (!function_exists('setSymbol')) {
    function setSymbol(string $type, float $value): string
    {
        return $type == PERCENTAGE ? $value . '%' : set_currency_symbol($value);
    }
}
if (!function_exists('dynamicAsset')) {
    function dynamicAsset(string $path): string
    {
        if (DOMAIN_POINTED_DIRECTORY == 'public') {
            $position = strpos($path, 'public/');
            $result = $path;
            if ($position === 0) {
                $result = preg_replace('/public/', '', $path, 1);
            }
        } else {
            $result = $path;
        }
        return asset($result);
    }
}
if (!function_exists('dynamicStorage')) {
    function dynamicStorage(string $path): string
    {
        if (DOMAIN_POINTED_DIRECTORY == 'public') {
            $result = str_replace('storage/app/public', 'storage', $path);
        } else {
            $result = $path;
        }
        return asset($result);
    }
}
if (!function_exists('convertToBytes')){
    function convertToBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;
        $multipliers = ['g' => 1073741824, 'm' => 1048576, 'k' => 1024];

        return $num * ($multipliers[$unit] ?? 1);
    }
}

if (!function_exists('convertBytesToKiloBytes')){
    function convertBytesToKiloBytes(string $value): int
    {
        return $value / 1024;
    }
}

if (!function_exists('convertToReadableSize')) {
    function convertToReadableSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . 'GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
}
if (!function_exists('maxUploadSize'))
{
    function maxUploadSize(string $fileType): int
    {
        $phpLimit = convertToBytes(ini_get('upload_max_filesize'));
        $appLimit = env('APP_MODE') === 'demo' ? convertToBytes('1M') : convertToBytes($fileType === 'image' ? '20M' : '50M');

        return min($phpLimit, $appLimit);
    }
}
if (!function_exists('readableUploadMaxFileSize'))
{
     function readableUploadMaxFileSize(string $fileType): string
    {
        return  convertToReadableSize(maxUploadSize($fileType));
    }
}
if (!function_exists('showValidationMessageForUploadMaxSize')) {
    function showValidationMessageForUploadMaxSize(array $files, bool $isAjax, bool $doesExpectJson)
    {
        $maximumSize = readableUploadMaxFileSize('image');

        foreach (flattenFiles($files) as $key => $file)
        {
            $fieldName = str_contains($key, '.') ? explode('.', $key)[0] : $key;
            $items = is_array($file) ? $file : [$file];
            foreach ($items as $item)
            {
                if ($item->getError() == 0) continue;
                $fileExtension = $item->getClientOriginalExtension();
                if(in_array($fileExtension, ['txt', 'rtf', 'doc', 'docx', 'pdf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'log', 'zip', 'mp4' ,'mkv' , 'avi', 'mov', 'webm']))
                {
                    $maximumSize = readableUploadMaxFileSize('file');
                }

                $message = translate(key: '{imageName} must be less than {maxSize}', replace: ['imageName' => $item->getClientOriginalName(), 'maxSize' => $maximumSize]);

                if ($isAjax || $doesExpectJson) {
                    throw new HttpResponseException(response()->json([
                        'errors' => [['error_code' => $fieldName, 'message' => $message]]
                    ], 403));
                }

                throw new HttpResponseException(Redirect::back()->withErrors([$fieldName => $message])->withInput());
            }
        }
    }
}
if (!function_exists('flattenFiles')) {
    function flattenFiles(array $files): array
    {
        $result = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $result[$key] = $file;
            } elseif (is_array($file)) {
                foreach ($file as $index => $nestedFile) {
                    if ($nestedFile instanceof UploadedFile) {
                        $result[$key][$index] = $nestedFile;
                    }
                }
            }
        }

        return $result;
    }
}


function mapRekognitionError(AwsException $e): array
{
    $code = $e->getAwsErrorCode();

    return match ($code) {

        'InvalidImageFormatException' => [
            'user' => 'Invalid Image Format (JPEG or PNG only)',
            'internal' => $e->getAwsErrorMessage(),
        ],

        'InvalidParameterException' => [
            'user' => 'No clear face detected.',
            'internal' => $e->getAwsErrorMessage(),
        ],

        'AccessDeniedException' => [
            'user' => 'Face verification service is temporarily unavailable.',
            'internal' => $e->getAwsErrorMessage(),
        ],

        'UnrecognizedClientException',
        'InvalidSignatureException' => [
            'user' => 'Face verification configuration error.',
            'internal' => $e->getAwsErrorMessage(),
        ],

        default => [
            'user' => 'Face verification failed.',
            'internal' => $e->getAwsErrorMessage(),
        ],
    };
}


if (!function_exists('change_text_color_or_bg')) {
    function change_text_color_or_bg(?string $data): string
    {
        $data = $data ?? '';
        // Replace ##text## with <span class="bg-primary text-white">text</span>
        $data = preg_replace('/##([^#]+)##/', '<span class="bg-primary text-white">$1</span>', $data);

        // Replace **text** with <span class="text--base">text</span>
        $data = preg_replace('/\*\*([^*]+)\*\*/', '<span class="text--base">$1</span>', $data);

        // Replace %% with </br>
        $data = str_replace('%%', '</br>', $data);

        // Replace @@text@@ with <b>text</b>
        $data = preg_replace('/@@([^@]+)@@/', '<b>$1</b>', $data);

        return $data;
    }
}

if (!function_exists('decodePolyline')) {
    function decodePolyline($encoded)
    {
        $points = [];
        $index = $lat = $lng = 0;

        while ($index < strlen($encoded)) {
            $shift = $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $shift = $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $points[] = [$lat * 1e-5, $lng * 1e-5];
        }

        return $points;
    }
}

if (!function_exists('projectVehicleOntoSegment')) {
    function projectVehicleOntoSegment(
        array $vehicleCoordinates,
        array $segmentStartPoint,
        array $segmentEndPoint
    ): array {
        $startX = $segmentStartPoint[1];
        $startY = $segmentStartPoint[0];

        $endX = $segmentEndPoint[1];
        $endY = $segmentEndPoint[0];

        $vehicleX = $vehicleCoordinates[1];
        $vehicleY = $vehicleCoordinates[0];

        $segmentDeltaX = $endX - $startX;
        $segmentDeltaY = $endY - $startY;

        // If both segment points are identical
        if ($segmentDeltaX == 0 && $segmentDeltaY == 0) {
            return $segmentStartPoint;
        }

        // Projection factor
        $projectionFactor =
            (
                ($vehicleX - $startX) * $segmentDeltaX +
                ($vehicleY - $startY) * $segmentDeltaY
            ) /
            ($segmentDeltaX * $segmentDeltaX + $segmentDeltaY * $segmentDeltaY);

        // Clamp between 0 and 1
        $projectionFactor = max(0, min(1, $projectionFactor));

        return [
            $startY + $projectionFactor * $segmentDeltaY,
            $startX + $projectionFactor * $segmentDeltaX
        ];
    }
}

if (!function_exists('slicePolylineFromVehiclePosition')) {
    function slicePolylineFromVehiclePosition(
        string $encodedPolyline,
        float $vehicleLatitude,
        float $vehicleLongitude
    ): string {

        // Decode full route
        $decodedRoutePoints = decodePolyline($encodedPolyline);

        $vehicleCoordinates = [
            'latitude' => $vehicleLatitude,
            'longitude' => $vehicleLongitude
        ];

        $shortestDistanceInMeters = INF;
        $closestSegmentIndex = 0;
        $projectedVehiclePoint = null;

        for ($segmentIndex = 0; $segmentIndex < count($decodedRoutePoints) - 1; $segmentIndex++) {

            $segmentStart = $decodedRoutePoints[$segmentIndex];
            $segmentEnd   = $decodedRoutePoints[$segmentIndex + 1];

            // Project vehicle onto this segment
            $projection = projectVehicleOntoSegment(
                [$vehicleLatitude, $vehicleLongitude],
                $segmentStart,
                $segmentEnd
            );

            // Use YOUR distanceCalculator (returns km)
            $distanceInKm = distanceCalculator([
                'from_latitude'  => $vehicleLatitude,
                'from_longitude' => $vehicleLongitude,
                'to_latitude'    => $projection[0],
                'to_longitude'   => $projection[1],
            ]);

            // Convert km → meters
            $distanceInMeters = $distanceInKm * 1000;

            if ($distanceInMeters < $shortestDistanceInMeters) {
                $shortestDistanceInMeters = $distanceInMeters;
                $closestSegmentIndex = $segmentIndex;
                $projectedVehiclePoint = $projection;
            }
        }

        // Build remaining route:
        // Start exactly from vehicle projection, then continue route
        $remainingRoutePoints = array_merge(
            [$projectedVehiclePoint],
            array_slice($decodedRoutePoints, $closestSegmentIndex + 1)
        );

        return encodePolyline($remainingRoutePoints);
    }
}


if (!function_exists('encodePolyline')) {
    function encodePolyline($points)
    {
        $result = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $p) {

            $lat = (int) round($p[0] * 1e5);
            $lng = (int) round($p[1] * 1e5);

            $result .= encodeValue($lat - $prevLat);
            $result .= encodeValue($lng - $prevLng);

            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $result;
    }
}


if (!function_exists('encodeValue')) {
    function encodeValue($v)
    {
        $v = $v < 0 ? ~($v << 1) : ($v << 1);

        $encoded = '';

        while ($v >= 0x20) {
            $encoded .= chr((0x20 | ($v & 0x1f)) + 63);
            $v >>= 5;
        }

        $encoded .= chr($v + 63);

        return $encoded;
    }
}

if (!function_exists('isDriverDeviated')) {
    function isDriverDeviated(
        string $encodedPolyline,
        float $vehicleLatitude,
        float $vehicleLongitude,
        float $thresholdMeters = 50
    ): bool {

        $decodedRoutePoints = decodePolyline($encodedPolyline);

        $shortestDistanceInMeters = INF;

        for ($i = 0; $i < count($decodedRoutePoints) - 1; $i++) {

            $segmentStart = $decodedRoutePoints[$i];
            $segmentEnd   = $decodedRoutePoints[$i + 1];

            $projection = projectVehicleOntoSegment(
                [$vehicleLatitude, $vehicleLongitude],
                $segmentStart,
                $segmentEnd
            );

            $distanceInKm = distanceCalculator([
                'from_latitude'  => $vehicleLatitude,
                'from_longitude' => $vehicleLongitude,
                'to_latitude'    => $projection[0],
                'to_longitude'   => $projection[1],
            ]);

            $distanceInMeters = $distanceInKm * 1000;

            if ($distanceInMeters < $shortestDistanceInMeters) {
                $shortestDistanceInMeters = $distanceInMeters;
            }
        }

        return $shortestDistanceInMeters > $thresholdMeters;
    }
}


if (!function_exists('deepLinkWellKnownPath')) {
    function deepLinkWellKnownPath($file): string
    {
        $webRoot = base_path(DOMAIN_POINTED_DIRECTORY == 'root' ? '' : DOMAIN_POINTED_DIRECTORY);

        $dir = $webRoot.'/.well-known';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.'/'.$file;
    }
}

if (!function_exists('isOtpEnabled')) {
    function isOtpEnabled(): bool
    {
        $smsConfigSettings = DB::table('settings')->where('settings_type', SMS_CONFIG)->get()->toArray();
        $isActiveGateway = false;
        foreach ($smsConfigSettings as $smsConfigSetting)
        {
            if ($smsConfigSetting->is_active == 1)
            {
                $isActiveGateway = true;
                break;
            }
        }

        $isFirebaseOtpEnabled = (bool) businessConfig(key: 'firebase_otp_verification_status', settingsType: FIREBASE_OTP)?->value;

        return $isActiveGateway || $isFirebaseOtpEnabled;
    }
}






