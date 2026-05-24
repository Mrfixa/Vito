<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Service\Interfaces\SupportSavedReplyServiceInterface;
use Modules\ChattingManagement\Http\Requests\StoreSendMessageRequest;
use Modules\ChattingManagement\Service\Interfaces\ChannelConversationServiceInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelListServiceInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelUserServiceInterface;
use Modules\ChattingManagement\Transformers\ChannelListResource;
use Modules\TransactionManagement\Service\Interfaces\TransactionServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Modules\UserManagement\Service\Interfaces\DriverServiceInterface;
use Modules\UserManagement\Service\Interfaces\EmployeeServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserAccountServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use Validator;

class DashboardController extends BaseController
{
    use AuthorizesRequests;

    protected $zoneService;
    protected $tripRequestService;
    protected $transactionService;
    protected $userAccountService;
    protected $driverService;
    protected $customerService;
    protected $employeeService;

    protected $channelListService;

    protected $channelUserService;

    protected $supportSavedReplyService;

    protected $channelConversationService;

    public function __construct(ZoneServiceInterface              $zoneService, TripRequestServiceInterface $tripRequestService,
                                TransactionServiceInterface       $transactionService, UserAccountServiceInterface $userAccountService,
                                DriverServiceInterface            $driverService, CustomerServiceInterface $customerService, EmployeeServiceInterface $employeeService,
                                ChannelListServiceInterface       $channelListService, ChannelUserServiceInterface $channelUserService,
                                SupportSavedReplyServiceInterface $supportSavedReplyService, ChannelConversationServiceInterface $channelConversationService,

    )
    {
        parent::__construct($zoneService);
        $this->zoneService = $zoneService;
        $this->tripRequestService = $tripRequestService;
        $this->transactionService = $transactionService;
        $this->userAccountService = $userAccountService;
        $this->driverService = $driverService;
        $this->customerService = $customerService;
        $this->employeeService = $employeeService;
        $this->channelListService = $channelListService;
        $this->channelUserService = $channelUserService;
        $this->supportSavedReplyService = $supportSavedReplyService;
        $this->channelConversationService = $channelConversationService;
    }

    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $zones = $this->zoneService->getBy(criteria: [
            'is_active' => 1
        ]);

        $totalTripsEarningCriteria = [
            'type' => RIDE_REQUEST,
            'payment_status' => PAID
        ];
        $totalParcelsEarningCriteria = [
            'type' => PARCEL,
            'payment_status' => PAID
        ];
        $whereHasRelations = [];

        // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
        $whereHasRelations['fee'] = function ($query) {
            $query->whereNull('cancelled_by')
                ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
        };
        $transactions = $this->transactionService->getBy(criteria: ['user_id' => \auth()->user()->id], orderBy: ['created_at' => 'desc'])->take(7);
        $superAdmin = $this->employeeService->findOneBy(criteria: ['user_type' => 'super-admin']);
        $superAdminAccount = $this->userAccountService->findOneBy(criteria: ['user_id' => $superAdmin?->id]);
        $customers = $this->customerService->getBy(criteria: ['user_type' => CUSTOMER, 'is_active' => true])->count();
        $drivers = $this->driverService->getBy(criteria: ['user_type' => DRIVER, 'is_active' => true])->count();
        $totalCouponAmountGiven = $this->tripRequestService->getBy(criteria: ['payment_status' => PAID])->SUM('coupon_amount');
        $totalDiscountAmountGiven = $this->tripRequestService->getBy(criteria: ['payment_status' => PAID])->SUM('discount_amount');
        $totalParcels = $this->tripRequestService->getBy(criteria: ['type' => PARCEL])->count();
        $totalEarning = $this->tripRequestService->getBy(criteria: ['payment_status' => PAID], whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
        $totalParcelsEarning = $this->tripRequestService->getBy(criteria: $totalParcelsEarningCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
        $totalRegularRide = $this->tripRequestService->getRidesBy(criteria: ['type' => RIDE_REQUEST, 'ride_request_type' => 'regular'])->count();
        $totalScheduledRide = $this->tripRequestService->getRidesBy(criteria: ['type' => RIDE_REQUEST, 'ride_request_type' => 'scheduled'])->count();
        $totalRegularRideEarning = $this->tripRequestService->getRidesBy(criteria: ['type' => RIDE_REQUEST, 'ride_request_type' => 'regular', 'payment_status' => true, 'whereHasRelation' => true])->sum('fee.admin_commission');
        $totalScheduledRideEarning = $this->tripRequestService->getRidesBy(criteria: ['type' => RIDE_REQUEST, 'ride_request_type' => 'scheduled', 'payment_status' => true, 'whereHasRelation' => true])->sum('fee.admin_commission');

        return view('adminmodule::dashboard', compact('zones', 'transactions', 'superAdminAccount', 'customers',
            'drivers', 'totalDiscountAmountGiven', 'totalCouponAmountGiven', 'totalRegularRide', 'totalScheduledRide', 'totalParcels', 'totalEarning', 'totalRegularRideEarning','totalScheduledRideEarning', 'totalParcelsEarning'));
    }

    public function recentTripActivity()
    {
        $trips = $this->tripRequestService->getBy(relations: ['customer', 'vehicle', 'vehicleCategory'], orderBy: ['created_at' => 'desc'], limit: 5, offset: 1);
        return response()->json(view('adminmodule::partials.dashboard._recent-trip-activity', compact('trips'))->render());
    }

    public function leaderBoardDriver(Request $request)
    {
        $request->merge(['user_type' => DRIVER]);
        $leadDriver = $this->tripRequestService->getLeaderBoard($request->all(), limit: 20);
        return response()->json(view('adminmodule::partials.dashboard._leader-board-driver', compact('leadDriver'))->render());
    }

    public function leaderBoardCustomer(Request $request)
    {
        $request->merge(['user_type' => CUSTOMER]);
        $leadCustomer = $this->tripRequestService->getLeaderBoard($request->all(), limit: 20);
        return response()->json(view('adminmodule::partials.dashboard._leader-board-customer', compact('leadCustomer'))->render());
    }

    public function adminEarningStatistics(Request $request)
    {
        $data = $this->tripRequestService->getAdminZoneWiseEarning($request->all());
        return response()->json($data);
    }


    public function zoneWiseStatistics(Request $request)
    {
        $data = $this->tripRequestService->getAdminZoneWiseStatistics(data: $request->all());
        return response()
            ->json(view('adminmodule::partials.dashboard._areawise-statistics', ['trips' => $data['zoneTripsByDate'], 'totalCount' => $data['totalTrips']])
                ->render());
    }

    public function heatMap(?Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_range' => [
                'nullable',
                'regex:/^\d{2}\/\d{2}\/\d{4}\s-\s\d{2}\/\d{2}\/\d{4}$/', // MM/DD/YYYY - MM/DD/YYYY
            ],
        ]);

        if ($validator->fails()) {
            Toastr::error("Please check your date format", "Error");
            return back()->withErrors($validator)->withInput();
        }
        $whereBetweenCriteria = [];
        if (array_key_exists('date_range', $request->all()) && $request['date_range']) {
            $date = getCustomDateRange($request['date_range']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
            $withCountQuery = [
                'tripRequest as ride_request' => [
                    ['type', '=', RIDE_REQUEST],
                    ['created_at', '>=', $date['start']], // Add your date range start
                    ['created_at', '<=', $date['end']],
                ],
                'tripRequest as parcel_request' => [
                    ['type', '=', PARCEL],
                    ['created_at', '>=', $date['start']], // Add your date range start
                    ['created_at', '<=', $date['end']],
                ]];
        } else {
            $withCountQuery = [
                'tripRequest as ride_request' => [
                    ['type', '=', RIDE_REQUEST]
                ],
                'tripRequest as parcel_request' => [
                    ['type', '=', PARCEL]
                ]];
        }

        $zones = $this->zoneService->index(criteria: $request?->all(), withCountQuery: $withCountQuery);
        $totalRideRequests = $zones->sum('ride_request');
        $totalParcelRequests = $zones->sum('parcel_request');
        $tripWhereInCriteria = [
            'zone_id' => $zones->pluck('id')->toArray(),
        ];
        $trips = $this->tripRequestService->getBy(whereInCriteria: $tripWhereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone']);
        $markers = $trips->map(function ($trip) {
            $url = route('admin.trip.show', $trip->id);
            return [
                'position' => [
                    'lat' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[0] ?? 0),
                    'lng' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[1] ?? 0),
                ],
                'title' => "<a href=\"{$url}\" target=\"_blank\">Trip #{$trip->ref_id}</a>",
            ];
        });

        $polygons = json_encode(formatZoneCoordinates($zones));

        $markers = json_encode($markers);
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;

        foreach ($zones as $zone) {
            $coords = json_decode($zone->coordinates, true);
            $ring = $coords['coordinates'][0] ?? [];
            if (count($ring) > 0) {
                $latSum += array_sum(array_column($ring, 1)) / count($ring);
                $lngSum += array_sum(array_column($ring, 0)) / count($ring);
                $totalPoints++;
            }
        }

        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
        return view('adminmodule::heat-map', compact('zones', 'totalRideRequests', 'totalParcelRequests', 'markers', 'polygons', 'centerLat', 'centerLng'));
    }

    public function heatMapOverview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_range' => [
                'nullable',
                'regex:/^\d{2}\/\d{2}\/\d{4}\s-\s\d{2}\/\d{2}\/\d{4}$/', // MM/DD/YYYY - MM/DD/YYYY
            ],
        ]);

        if ($validator->fails()) {
            Toastr::error("Please check your date format", "Error");
            return back()->withErrors($validator)->withInput();
        }
        $whereBetweenCriteria = [];
        if (array_key_exists('date_range', $request->all()) && $request['date_range']) {
            $date = getCustomDateRange($request['date_range']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        $whereInCriteria = [
            'id' => $request['zone_ids'] ?? []
        ];
        $zones = $this->zoneService->getBy(whereInCriteria: $whereInCriteria);
        $tripWhereInCriteria = [
            'zone_id' => $zones->pluck('id')->toArray(),
        ];
        $trips = $this->tripRequestService->getBy(whereInCriteria: $tripWhereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone']);
        $markers = $trips->map(function ($trip) {
            $url = route('admin.trip.show', $trip->id);
            return [
                'position' => [
                    'lat' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[0] ?? 0),
                    'lng' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[1] ?? 0),
                ],
                'title' => "<a href=\"{$url}\" target=\"_blank\">Trip #{$trip->ref_id}</a>",
            ];
        });
        $polygons = json_encode(formatZoneCoordinates($zones));
        $markers = json_encode($markers);
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;
        foreach ($zones as $zone) {
            $coords = json_decode($zone->coordinates, true);
            $ring = $coords['coordinates'][0] ?? [];
            if (count($ring) > 0) {
                $latSum += array_sum(array_column($ring, 1)) / count($ring);
                $lngSum += array_sum(array_column($ring, 0)) / count($ring);
                $totalPoints++;
            }
        }

        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
        return response()
            ->json(view('adminmodule::partials.heat-map._overview-map', compact('polygons', 'markers', 'centerLat', 'centerLng'))
                ->render());
    }

    public function heatMapCompare(Request $request)
    {
        $allZones = $this->zoneService->getAll();
        if (array_key_exists('zone_id', $request->all()) && $request['zone_id']) {
            $zone = $this->zoneService->findOne(id: $request['zone_id']);
        } else {
            $zone = count($allZones) ? $this->zoneService->findOne(id: $allZones[0]->id) : null;
        }

        $date = getDateRange(request: ALL_TIME);
        $beginningOfDate = Carbon::createFromDate($date['start'])->format('m/d/Y');
        $endOfDate = Carbon::createFromDate($date['end'])->format('m/d/Y');
        $dateRange = "{$beginningOfDate} - {$endOfDate}";

        if (array_key_exists('time_frame', $request->all()) && $request['time_frame'] && $request['time_frame'] != CUSTOM && $request['time_frame'] != ALL_TIME)
        {
            $date = getDateRange($request['time_frame']);
            $beginningOfDate = Carbon::createFromDate($date['start'])->format('m/d/Y');
            $endOfDate = Carbon::createFromDate($date['end'])->format('m/d/Y');
            $dateRange = "{$beginningOfDate} - {$endOfDate}";
        } else if (array_key_exists('time_frame', $request->all()) && array_key_exists('date_range', $request->all()) && $request['time_frame'] && $request['date_range'] && $request['time_frame'] == CUSTOM)
        {
            $dateRange = $request['date_range'];
            $date = getCustomDateRange($request['date_range']);
        }
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$startDate, $endDate],
        ];
        $tripCount = $this->tripRequestService->getBy(criteria: ['zone_id' => $zone?->id], whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone'])->count();
        $dateWiseTrips = $this->tripRequestService->getTripHeatMapCompareDataBy(data: ['zone_id' => $zone?->id, 'date_range' => $dateRange]);
        if ($dateWiseTrips->isNotEmpty()) {
            $markers = [];
            foreach ($dateWiseTrips as $dateWiseTrip) {
                if (isset($dateWiseTrip->month) && isset($dateWiseTrip->year)) {
                    $markerKey = $dateWiseTrip->month;
                    if ($startDate->month < $dateWiseTrip->month && $endDate->month == $dateWiseTrip->month) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay();
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay(), Carbon::create($endDate)],
                        ];
                    } elseif ($startDate->month < $dateWiseTrip->month && $endDate->month > $dateWiseTrip->month) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay();
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay(), Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay()],
                        ];
                    } elseif ($startDate->month == $dateWiseTrip->month && $endDate->month > $dateWiseTrip->month) {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay()],
                        ];
                    } else {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::create($endDate)],
                        ];
                    }
                } elseif (isset($dateWiseTrip->year)) {
                    $markerKey = $dateWiseTrip->year;

                    if ($startDate->year < $dateWiseTrip->year && $endDate->year == $dateWiseTrip->year) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay();
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay(), Carbon::create($endDate)],
                        ];
                    } elseif ($startDate->year < $dateWiseTrip->year && $endDate->year > $dateWiseTrip->year) {

                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay();
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay(), Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay()],
                        ];

                    } elseif ($startDate->year == $dateWiseTrip->year && $endDate->year > $dateWiseTrip->year) {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay()],
                        ];
                    } else {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::create($endDate)],
                        ];
                    }
                } elseif (isset($dateWiseTrip->hour)) {
                    $showStartDate = Carbon::create($dateWiseTrip->date)->setTime($dateWiseTrip->hour, 0);
                    $showEndDate = $showStartDate->copy()->addMinutes(59)->addSeconds(59);
                    $markerKey = $dateWiseTrip->hour;
                    $whereMarkerBetweenCriteria = [
                        'created_at' => [$showStartDate, $showEndDate],
                    ];
                } else {
                    $markerKey = $dateWiseTrip->date;
                    $showStartDate = Carbon::create($dateWiseTrip->date)->startOfDay();
                    $showEndDate = Carbon::create($dateWiseTrip->date)->endOfDay();
                    $whereMarkerBetweenCriteria = [
                        'created_at' => [Carbon::create($dateWiseTrip->date)->startOfDay(), Carbon::create($dateWiseTrip->date)->endOfDay()],
                    ];

                }
                $dateWiseTrip->startDate = $showStartDate;
                $dateWiseTrip->endDate = $showEndDate;
                $dateWiseTrip->markerKey = $markerKey;
                $trips = $this->tripRequestService->getBy(criteria: ['zone_id' => $zone?->id], whereBetweenCriteria: $whereMarkerBetweenCriteria, relations: ['coordinate', 'zone']);
                $mappedMarkers = $trips->map(function ($trip) {
                    $url = route('admin.trip.show', $trip->id);
                    return [
                        'position' => [
                            'lat' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[0] ?? 0),
                            'lng' => (float)(explode(',', $trip?->coordinate?->pickup_coordinates ?? '0,0')[1] ?? 0),
                        ],
                        'title' => "<a href=\"{$url}\" target=\"_blank\">Trip #{$trip->ref_id}</a>",
                    ];
                });
                $markers[$markerKey] = $mappedMarkers;
            }
        } else {
            $markers = [];
        }
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;
        $zoneGeo = $zone ? json_decode($zone->coordinates, true) : null;
        $zonePoly = $zoneGeo['coordinates'][0] ?? [];
        $polygons = $zone ? json_encode([formatCoordinates($zonePoly)]) : json_encode([[]]);
        if ($zone) {
            foreach (formatCoordinates($zonePoly) as $point) {
                $latSum += $point->lat;
                $lngSum += $point->lng;
                $totalPoints++;
            }
        }
        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $tripStatisticsData = $this->tripRequestService->getTripHeatMapCompareZoneDateWiseEarningStatistics(data: ['zone_id' => $zone?->id, 'date_range' => $dateRange]);

        return view('adminmodule::heat-map-compare',
            compact('allZones', 'zone',
                'dateRange', 'tripCount', 'polygons', 'markers', 'centerLat',
                'centerLng', 'dateWiseTrips', 'tripStatisticsData'));

    }

    public function chatting(Request $request)
    {
        $this->authorize('chatting_view');
        $driverList = $this->driverService->getChattingDriverList(data: $request->all());
        $savedReplies = $this->supportSavedReplyService->getBy(criteria: ['is_active' => 1]);

        return view('adminmodule::chatting', compact('driverList', 'savedReplies'));

    }

    public function getDriverConversation($channelId, Request $request)
    {
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $this->channelConversationService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $conversations = $this->channelConversationService->getBy(criteria: ['channel_id' => $channelId], relations: ['user', 'conversation_files'], orderBy: ['id' => 'desc']);
        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->driverId, 'user_type' => DRIVER], withTrashed: true);
        return response()
            ->json(view('adminmodule::partials.chatting._conversation', compact('conversations', 'driver', 'channelId'))
                ->render());
    }

    public function searchDriversList(Request $request)
    {
        $driverList = $this->driverService->getChattingDriverList(data: $request->all());

        return response()
            ->json(view('adminmodule::partials.chatting._search-drivers', compact('driverList'))
                ->render());
    }

    public function searchSavedTopicAnswer(Request $request)
    {

        $searchCriteria = [];
        if (array_key_exists('search', $request->all())) {
            $searchCriteria = [
                'fields' => ['topic'],
                'value' => $request->search,
            ];
        }

        $savedReplies = $this->supportSavedReplyService->getBy(criteria: ['is_active' => 1], searchCriteria: $searchCriteria);

        return response()
            ->json(view('adminmodule::partials.chatting._saved-answer', compact('savedReplies'))
                ->render());
    }

    public function sendMessageToDriver(StoreSendMessageRequest $request)
    {
        $fileImage = [];
        if ($request->has('file')) {
            $fileImage = array_merge($fileImage, $request->file('file'));
        }
        if ($request->has('image')) {
            $fileImage = array_merge($fileImage, $request->file('image'));
        }
        $data = [
            'channel_id' => $request->channelId,
            'user_id' => auth()->user()->id,
            'message' => $request->message,
            'is_read' => 0,
            'files' => $fileImage,
        ];
        $dataCreated = $this->channelConversationService->create(data: $data);
        $sentTime = pushSentTime($dataCreated->created_at);
        $channelDriver = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request->channelId, 'user_id' => $request->driverId], relations: ['user']);
        $driver = $this->driverService->findOne(id: $request->driverId);

        $push = getNotification('admin_message');
        sendDeviceNotification(fcm_token: $channelDriver?->user?->fcm_token,
            title: translate(key: $push['title'], locale: $driver->current_language_key),
            description: textVariableDataFormat(value: $push['description'], sentTime: $sentTime, driverName: $driver->first_name,  locale: $driver->current_language_key),
            status: 1,
            ride_request_id: $request->driverId,
            type: $request->channelId,
            notification_type: 'chatting',
            action: $push['action'],
            user_id: $request->driverId,
        );

        $channelId = $request->channelId;
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $this->channelConversationService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $conversations = $this->channelConversationService->getBy(criteria: ['channel_id' => $channelId], relations: ['user', 'conversation_files'], orderBy: ['created_at' => 'desc']);
        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->driverId, 'user_type' => DRIVER], withTrashed: true);


        return response()
            ->json(view('adminmodule::partials.chatting._conversation', compact('conversations', 'driver', 'channelId'))
                ->render());

    }

    public function createChannelWithAdmin(Request $request)
    {
        $channelIds = $this->channelUserService->getBy(criteria: ['user_id' => $request->driverId]);
        $channelIds = $channelIds->pluck('channel_id')->toArray();

        $whereInCriteria = [
            'channel_id' => $channelIds
        ];
        $criteria = [
            'user_id' => auth()->user()->id,
        ];

        $channelUser = $this->channelUserService->findOneBy(criteria: $criteria, whereInCriteria: $whereInCriteria);
        if ($channelUser) {
            $findChannel = $this->channelListService->findOne($channelUser?->channel_id);
            if ($findChannel) {
                $findChannel = $this->channelListService->update(id: $findChannel?->id, data: $request->all());
                return response()->json(responseFormatter(DEFAULT_200, ['user' => auth()->user(), 'channel' => ChannelListResource::make($findChannel)]), 200);
            }
        }
        $channel = $this->channelListService->createChannelWithAdmin(data: ['to' => $request->driverId]);
        return response()->json(responseFormatter(DEFAULT_STORE_200, ['user' => auth()->user(), 'channel' => ChannelListResource::make($channel)]), 200);
    }

}
