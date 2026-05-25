<?php

namespace Modules\TripManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Carbon\Carbon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;

class TripRequestRepository extends BaseRepository implements TripRequestRepositoryInterface
{
    public function __construct(TripRequest $model)
    {
        parent::__construct($model);
    }

    public function calculateCouponAmount($startDate = null, $endDate = null, $startTime = null, $month = null, $year = null): mixed
    {
        $query = $this->model->whereNotNull('coupon_amount');

        if ($startDate !== null && $endDate !== null) {
            $query->whereBetween('created_at', [
                "{$startDate->format('Y-m-d')} 00:00:00",
                "{$endDate->format('Y-m-d')} 23:59:59"
            ]);
        } elseif ($startTime !== null) {
            $query->whereBetween('created_at', [
                date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', $startTime),
                date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', strtotime('+2 hours', $startTime))
            ]);
        } elseif ($month !== null) {
            $query->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->format('Y'));
        } elseif ($year !== null) {
            $query->whereYear('created_at', $year);
        } else {
            $query->whereDay('created_at', now()->format('d'))
                ->whereMonth('created_at', now()->format('m'));
        }

        return $query->sum('coupon_amount');
    }

    public function fetchTripData($dateRange): Collection
    {
        $query = $this->model->whereNotNull('coupon_amount');

        switch ($dateRange) {
            case THIS_WEEK:
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                $query->whereBetween('created_at', [$startDate, $endDate]);
                break;

            case THIS_MONTH:
                $query->whereYear('created_at', Carbon::now()->year)
                    ->whereMonth('created_at', Carbon::now()->month);
                break;

            case THIS_YEAR:
                $query->whereYear('created_at', Carbon::now()->year);
                break;
            case TODAY:
                $query->whereDate('created_at', Carbon::today());
            default:
                $query;
                break;
        }

        return $query->get();
    }


    public function statusWiseTotalTripRecords(array $attributes): Collection
    {
        $base = $this->model->when(
            $attributes['from'] ?? null,
            fn ($q) => $q->whereBetween('created_at', [$attributes['from'], $attributes['to']])
        );

        $byStatus = (clone $base)
            ->where(function ($q) {
                $q->where('current_status', '!=', 'pending')
                    ->orWhere(function ($q2) {
                        $q2->where('current_status', 'pending')
                            ->where(function ($q3) {
                                $q3->where('ride_request_type', '!=', 'scheduled')
                                    ->orWhereNull('ride_request_type');
                            });
                    });
            })
            ->selectRaw("CASE
                WHEN current_status IN ('accepted', 'out_for_pickup') THEN 'accepted'
                ELSE current_status
            END AS current_status, COUNT(*) AS total_records")
            ->groupBy(DB::raw("
            CASE
                WHEN current_status IN ('accepted', 'out_for_pickup') THEN 'accepted'
                ELSE current_status
            END
        "));
        $scheduled = (clone $base)
            ->where('ride_request_type', 'scheduled')
            ->where('current_status', 'pending')
            ->selectRaw(DB::raw("'scheduled' AS current_status, COUNT(*) AS total_records"));

        return $byStatus->unionAll($scheduled)->get();
    }


    public function pendingParcelList(array $attributes)
    {
        return $this->model->query()
            ->with([
                'customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'
            ])
            ->where(['type' => 'parcel', $attributes['column'] => $attributes['value']])
            ->when($attributes['whereNotNull'] ?? null, fn($query) => $query->whereNotNull($attributes['whereNotNull']))
            ->whereNotIn('current_status', ['cancelled', 'completed'])
            ->paginate(perPage: $attributes['limit'], page: $attributes['offset']);
    }


    public function updateRelationalTable($attributes): mixed
    {
        $trip = $this->findOne(id: $attributes['value']);

        if ($attributes['trip_status'] ?? null) {
            $tripData['current_status'] = $attributes['trip_status'];

            $trip->update($tripData);
            $trip->tripStatus()->update([
                $attributes['trip_status'] => now()
            ]);
        }
        if ($attributes['driver_id'] ?? null) {
            $trip->driver_id = null;
            $trip->save();
        }

        if ($attributes['coordinate'] ?? null) {
            $trip->coordinate()->update($attributes['coordinate']);
        }
        if ($attributes['fee'] ?? null) {
            $trip->fee()->update($attributes['fee']);
        }
        return $trip->tripStatus;
    }


    public function findOneWithAvg(array $criteria = [], array $relations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false, array $withAvgRelation = []): ?Model
    {
        $data = $this->prepareModelForRelationAndOrder(relations: $relations)
            ->where($criteria)
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withAvgRelation), function ($query) use ($withAvgRelation) {
                $query->withAvg($withAvgRelation[0], $withAvgRelation[1]);
            })
            ->first();
        return $data;
    }


    public function getWithAvg(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $withAvgRelation = [], array $whereBetweenCriteria = [], array $whereNotNullCriteria = []): Collection|LengthAwarePaginator
    {

        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(!empty($whereBetweenCriteria), function ($whereQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $values) {
                    $whereQuery->whereBetween($column, $values);
                }
            })
            ->when(!empty($whereNotNullCriteria), function ($whereQuery) use ($whereNotNullCriteria) {
                foreach ($whereNotNullCriteria as $column) {
                    $whereQuery->whereNotNull($column);
                }
            })
            ->when(!empty($withAvgRelation), function ($query) use ($withAvgRelation) {
                $query->withAvg($withAvgRelation[0], $withAvgRelation[1]);
            });

        if ($limit) {
            return !empty($criteria) ? $model->paginate($limit)->appends($criteria) : $model->paginate($limit);
        }
        return $model->get();
    }


    public function getPendingRides($attributes): mixed
    {
        return $this->model->query()
            ->when($attributes['relations'] ?? null, fn($query) => $query->with($attributes['relations']))
            ->with([
                'fare_biddings' => fn($query) => $query->where('driver_id', auth()->id()),
                'coordinate',
            ])
            ->whereHas('coordinate')
            ->when($attributes['withAvgRelation'] ?? null,
                fn($query) => $query->withAvg($attributes['withAvgRelation'], $attributes['withAvgColumn']))
            ->whereDoesntHave('ignoredRequests', fn($query) => $query->where('user_id', auth()->id()))
            ->where(fn($query) => $query->where('vehicle_category_id', $attributes['vehicle_category_id'])
                ->orWhereNull('vehicle_category_id')
            )
            ->where(['zone_id' => $attributes['zone_id'], 'current_status' => PENDING,])
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $attributes['limit'], page: $attributes['offset']);
    }

    public function getZoneWiseStatistics(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            })->whereNotNull('zone_id')
            ->selectRaw('count(completed) as completed_trips,count(cancelled) as cancelled_trips,count(pending) as pending_trips,count(accepted) as accepted_trips,count(ongoing) as ongoing_trips,zone_id, count(*) as total_records')
            ->groupBy('zone_id')->orderBy('total_records', 'asc');
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }
        return $model->get();
    }

    public function getZoneWiseEarning(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], $startDate = null, $endDate = null, $startTime = null, $month = null, $year = null): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            });
        if ($startDate !== null && $endDate !== null) {
            $model->whereBetween('created_at', [
                "{$startDate->format('Y-m-d')} 00:00:00",
                "{$endDate->format('Y-m-d')} 23:59:59"
            ]);
        } elseif ($startDate !== null && $startTime !== null) {
            $model->whereBetween('created_at', [
                date('Y-m-d', strtotime($startDate)) . ' ' . date('H:i:s', $startTime),
                date('Y-m-d', strtotime($startDate)) . ' ' . date('H:i:s', strtotime('+2 hours', $startTime))
            ]);
        } elseif ($month !== null && $year) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
        } elseif ($month !== null && $year !== null) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
        } elseif ($month !== null) {
            $model->whereMonth('created_at', $month)
                ->whereYear('created_at', now()->format('Y'));
        } elseif ($year !== null) {
            $model->whereYear('created_at', $year);
        } else {
            $model->whereDay('created_at', now()->format('d'))
                ->whereMonth('created_at', now()->format('m'));
        }
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }
        return $model->get();
    }

    public function getLeaderBoard(string $userType, array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })
            ->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            })->whereNotNull($userType)
            ->selectRaw($userType . ', count(*) as total_records ,SUM(paid_fare) as income')
            ->groupBy($userType)
            ->orderBy('total_records', 'desc');
        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }
        return $model->get();
    }

    public function getPopularTips()
    {
        return $this->model->whereNot('tips', 0)->groupBy('tips')->selectRaw('tips, count(*) as total')->orderBy('total', 'desc')->first();
    }

    public function getTripHeatMapCompareDataBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], $startDate = null, $endDate = null): Collection|LengthAwarePaginator
    {
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $startDate)->setTime(0, 0); // Start at 6 AM
        $endDateTime = $startDateTime->copy()->endOfDay(); // End of the same day
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation['relation'], $relation['column']);
                }
            });

        if ($startDate->isSameDay($endDate)) {
            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('HOUR(created_at) AS hour'), // Get the hour part
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDateTime, $endDateTime]) // Full day range
                ->groupBy('date', 'hour')
                ->orderBy('hour', 'asc'); // Group by date and hour
        } elseif ($startDate->isSameWeek($endDate)) {
            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('DAYNAME(created_at) AS day'), // Get the hour part
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('date', 'day'); // Group by date and hour
        } elseif ($startDate->isSameMonth($endDate)) {

            $model->select(
                DB::raw('DATE(created_at) as date'), // Extract the date part from created_at
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('date')
                ->orderBy('date', 'asc');
        } elseif ($startDate->isSameYear($endDate)) {

            $model->select(
                DB::raw('MONTH(created_at) as month'), // Group by month (Year-Month format)
                DB::raw('YEAR(created_at) as year'), // Group by month (Year-Month format)
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('month', 'year')
                ->orderBy('month', 'asc');
        } else {

            $model->select(
                DB::raw('YEAR(created_at) as year'), // Group by year
                DB::raw('COUNT(CASE WHEN type = "parcel" THEN 1 END) as parcel_count'), // Count for parcel type
                DB::raw('COUNT(CASE WHEN type = "ride_request" THEN 1 END) as ride_count') // Count for ride type
            )
                ->whereBetween('created_at', [$startDate, $endDate]) // Full day range
                ->groupBy('year')
                ->orderBy('year', 'asc');
        }

        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }

    public function allRideList(array $criteria = [], array $relations = [], array $orderBy = []): mixed
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })
            ->where(function ($query) {
                $query->whereIn('current_status', ['ongoing', 'accepted', 'out_for_pickup'])
                    ->orWhere(fn($query1) => $query1->where('current_status', 'completed')->where('payment_status', 'unpaid'))
                    ->orWhere(fn($query2) => $query2->where('current_status', 'cancelled')->where('payment_status', 'unpaid')->whereHas('fee', fn($query3) => $query3->where('cancelled_by', 'customer')));
            })
            ->get();
    }

    public function getPendingParcel(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): mixed
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })
            ->when(isset($criteria['driver_id']), function ($query) { // Removed extra arrow here
                $query->where(function ($query) {
                    $query->where(function ($query1) {
                        $query1->where('current_status', COMPLETED)
                            ->where('payment_status', UNPAID);
                    })->orWhere(function ($query) {
                        $query->whereIn('current_status', [PENDING, ACCEPTED, ONGOING, RETURNING]);
                    });
                });
            })
            ->when(isset($criteria['customer_id']), function($query) {
                $query->whereNotIn('current_status', [CANCELLED, COMPLETED, RETURNED]);
            })
            ->paginate(perPage: $limit, page: $offset ?? 1);
    }


    public function getPendingRide(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $withAvgRelations = [], array $orderBy = [], array $attributes = []): mixed
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria) && isset($criteria['ride_request_type']), function ($query) use (&$criteria) {
                $query->where(function($q) use ($criteria){
                    $q->where('ride_request_type', $criteria['ride_request_type'])
                        ->orWhereNull('ride_request_type');
                });
                unset($criteria['ride_request_type']);
            })
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })
            ->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })
            ->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        if (is_callable($conditions)) {
                            $conditions($query); // It's a Closure
                        } else {
                            foreach ($conditions as $field => $value) {
                                if (is_array($value) && count($value) === 3) {
                                    [$field, $operator, $val] = $value;
                                    $query->where($field, $operator, $val);
                                } elseif (is_array($value)) {
                                    $query->where(function ($subQuery) use ($field, $value) {
                                        foreach ($value as $v) {
                                            $subQuery->orWhere($field, $v);
                                        }
                                    });
                                } else {
                                    $query->where($field, $value);
                                }
                            }
                        }
                    });
                }
            })
            ->whereDoesntHave('ignoredRequests', fn($query) => $query->where('user_id', auth()->id()))
            ->where(fn($query) => $query->where('vehicle_category_id', $attributes['vehicle_category_id'])
                ->orWhereNull('vehicle_category_id')
            )
            ->where(function ($query) use ($attributes) {
                if ($attributes['ride_count'] < 3) {
                    $query->where('type', RIDE_REQUEST);
                }

                // 2. Parcel request logic based on parcel follow status and parcel count
                $query->orWhere(function ($query) use ($attributes) {
                    if ($attributes['parcel_follow_status']) {
                        // Only include parcels if parcel_count < 2
                        if ($attributes['parcel_count'] < $attributes['max_parcel_request_accept_limit_count']) {
                            $query->where('type', PARCEL);
                        } else {
                            $query->whereNotIn('type', [PARCEL, RIDE_REQUEST]);
                        }
                    } else {
                        // Include all parcels when parcel_follow_status is false
                        $query->where('type', PARCEL);
                    }
                });
            });
        if ($attributes['limit']) {
            return !empty($appends) ? $model->paginate(perPage: $attributes['limit'], page: $attributes['offset'] ?? 1)->appends($appends) : $model->paginate(perPage: $attributes['limit'], page: $attributes['offset'] ?? 1);
        }
        return $model->get();
    }

    public function getLockedTrip(array $data = []): mixed
    {
        return $this->model->where($data)->lockForUpdate()->first();
    }

    /**
     * @param array $criteria
     * @param array $relations
     * @param array $orderBy
     * @return mixed
     */
    public function getIncompleteRide(array $criteria = []): mixed
    {
        return $this->model
            ->when(!empty($criteria) && isset($criteria['ride_request_type']), function ($query) use (&$criteria) {
                $query->where(function($q) use ($criteria){
                    $q->where('ride_request_type', $criteria['ride_request_type'])
                        ->orWhereNull('ride_request_type');
                });
                unset($criteria['ride_request_type']);
            })
            ->where(fn($query) => $query->whereNotIn('current_status', ['completed', 'cancelled'])
                ->orWhere(fn($query) => $query->whereNotNull('driver_id')
                    ->whereHas('fee', function ($query) {
                        $query->where('cancelled_by', '!=', 'driver');
                    })
                    ->whereIn('current_status', ['completed', 'cancelled'])
                    ->where('payment_status', 'unpaid')
                ))
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })
            ->first();
    }

    public function getRidesBy(array $data, array $whereBetweenCriteria = []): mixed
    {
        $query = $this->prepareModelForRelationAndOrder(relations: ['fee'])
            ->when($data && isset($data['whereHasRelation']), function ($whereHasRelationQuery){
                $whereHasRelationQuery->whereHas('fee', function($whereHasQuery){
                    $whereHasQuery->whereNull('cancelled_by')
                        ->orWhere('cancelled_by', '=', 'CUSTOMER');
                });
            })
            ->when($data && isset($data['payment_status']), function($paymentStatusQuery){
                $paymentStatusQuery->where('payment_status', PAID);
            });
        ;
        if (!empty($data['type']) && $data['type'] == RIDE_REQUEST && isset($data['ride_request_type'])) {
            $query->where('type', RIDE_REQUEST);

            if (!empty($data['ride_request_type'])) {
                if ($data['ride_request_type'] === 'regular') {
                    $query->where(function ($q) {
                        $q->where('ride_request_type', 'regular')
                            ->orWhereNull('ride_request_type');
                    });
                } elseif ($data['ride_request_type'] === 'scheduled') {
                    $query->where('ride_request_type', 'scheduled');
                }
            }
        }
        else if(!empty($data['type']) && $data['type'] == PARCEL) {
            $query->where('type', PARCEL);
        }

        $query->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
            foreach ($whereBetweenCriteria as $column => $range) {
                $whereBetweenQuery->whereBetween($column, $range);
            }
        });

        return $query->get();
    }

    public function getCustomerPendingRideList(array $data, array $relations, ?int $limit = null, ?int $offset = null): mixed
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations)
            ->when(!empty($data), function ($whereQuery) use ($data) {
                $whereQuery->where($data);
            })
        ->where(fn($query) => $query->whereNotIn('current_status', ['completed', 'cancelled'])
        ->orWhere(fn($query) => $query->whereNotNull('driver_id')
            ->whereHas('fee', function ($query) {
                $query->where('cancelled_by', '!=', 'driver');
            })
            ->whereIn('current_status', ['completed', 'cancelled'])
            ->where('payment_status', 'unpaid')
        ));
        if ($limit) {
            return $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }
    public function getBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(array_key_exists('get_upcoming_trips', $criteria), function ($query) use (&$criteria) {
                $query->where(function ($q) {
                    $q->where(function ($subquery1) {
                        $subquery1->where('type', PARCEL)
                            ->where('current_status', ACCEPTED);
                    })->orWhere(function ($subquery2) {
                        $subquery2->where('type', RIDE_REQUEST)
                            ->where('current_status', OUT_FOR_PICKUP);
                    });
                });
                unset($criteria['get_upcoming_trips']);
            })
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })
            ->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    if ($values instanceof \Illuminate\Support\Collection) {
                        $values = $values->toArray();
                    }
                    $whereInQuery->where(function ($q) use ($column, $values) {
                        $nonNullValues = array_filter($values, fn($val) => !is_null($val));
                        if (!empty($nonNullValues)) {
                            $q->whereIn($column, $nonNullValues);
                        }
                        if (in_array(null, $values, true)) {
                            $q->orWhereNull($column);
                        }
                    });
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {

                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        foreach ($conditions as $field => $value) {
                            if (is_array($value) && count($value) === 3) {
                                // Handle complex conditions with custom operators
                                [$field, $operator, $val] = $value;
                                $query->where($field, $operator, $val);
                            } elseif (is_array($value)) {
                                // Handle OR conditions for arrays (e.g., ['ongoing', 'accepted', 'completed'])
                                $query->where(function ($subQuery) use ($field, $value) {
                                    foreach ($value as $v) {
                                        $subQuery->orWhere($field, $v);
                                    }
                                });
                            } else {
                                // Handle single key-value pairs
                                $query->where($field, $value);
                            }
                        }
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation['relation'], $relation['column']);
                }
            })->when(!empty($groupBy), function ($query) use ($groupBy) {
                $selectFields = []; // Prepare an array to hold select fields
                foreach ($groupBy as $groupColumn) {
                    if (str_ends_with($groupColumn, 'created_at')) {
                        // Group by the date part of the created_at field
                        $query->groupBy(DB::raw('DATE(' . $groupColumn . ')'));
                        $selectFields[] = DB::raw('DATE(' . $groupColumn . ') as ' . $groupColumn); // Select the date part
                    } else {
                        $query->groupBy($groupColumn);
                        $selectFields[] = $groupColumn; // Select the original group column
                    }
                }

                // Update the select statement to include the group columns
                $query->select($selectFields);
            });
        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }
    public function getSurgePricingStatistics(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], $startDate = null, $endDate = null, $startTime = null, $month = null, $year = null): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {
                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        $query->where($conditions);
                    });
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation);
                }
            });
        if ($startDate !== null && $endDate !== null) {
            $model->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->where('ride_request_type', 'regular')
                            ->orWhereNull('ride_request_type');
                    })->whereBetween('created_at', [$startDate, $endDate]);
                })
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('ride_request_type', 'scheduled')
                            ->whereBetween('scheduled_at', [$startDate, $endDate]);
                    });
            });
        }

        if ($limit) {
            return !empty($appends) ? $model->paginate($limit)->appends($appends) : $model->paginate($limit);
        }
        return $model->get();
    }

}
