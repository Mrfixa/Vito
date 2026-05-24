<?php

namespace Modules\ZoneManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\ZoneManagement\Entities\Zone;
use Modules\ZoneManagement\Repository\ZoneRepositoryInterface;

class ZoneRepository extends BaseRepository implements ZoneRepositoryInterface
{
    public function __construct(Zone $model)
    {
        parent::__construct($model);
    }


    public function getByPoints($point)
    {
        return $this->model->where('is_active', 1);
    }

    public function findOne($id, array $relations = [], array $withAvgRelations = [],array $whereHasRelations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->selectRaw("*, NULL as center")
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })
            ->find($id);
    }

    public function findOneBy(array $criteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $withAvgRelations = [], array $relations = [],array $whereHasRelations = [], array $withCountQuery = [], array $orderBy = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->selectRaw("*, NULL as center")
            ->where($criteria)
            ->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })
            ->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })->when(!empty($orderBy), function ($query) use ($orderBy) {
                foreach ($orderBy as $column => $order) {
                    $query->orderBy($column, $order);
                }
            })
            ->first();
    }

    public function getAll(array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->selectRaw("*, NULL as center")
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
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
            return $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }

    public function getBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->selectRaw("*, NULL as center")
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

    public function getZoneContainingBothPoints(int|string $zoneId, $pickupPoint, $destinationPoint): ?Model
    {
        return $this->model->where('id', $zoneId)->first();
    }
}
