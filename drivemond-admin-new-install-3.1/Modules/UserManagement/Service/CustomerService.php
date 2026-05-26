<?php

namespace Modules\UserManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\TransactionManagement\Repository\TransactionRepositoryInterface;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;
use Modules\UserManagement\Repository\UserLevelRepositoryInterface;
use Modules\UserManagement\Repository\UserRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;

class CustomerService extends BaseService implements Interfaces\CustomerServiceInterface
{
    protected $userRepository;
    protected $userLevelRepository;
    protected $tripRequestRepository;
    protected $transactionRepository;

    public function __construct(UserRepositoryInterface        $userRepository, UserLevelRepositoryInterface $userLevelRepository,
                                TripRequestRepositoryInterface $tripRequestRepository, TransactionRepositoryInterface $transactionRepository)
    {
        parent::__construct($userRepository);
        $this->userRepository = $userRepository;
        $this->userLevelRepository = $userLevelRepository;
        $this->tripRequestRepository = $tripRequestRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('status', $criteria) && $criteria['status'] !== 'all') {
            $data['is_active'] = $criteria['status'] == 'active' ? 1 : 0;
        }
        if (array_key_exists('value', $criteria)) {
            $data['user_level_id'] = $criteria['value'];
        }
        $data['user_type'] = CUSTOMER;
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['full_name', 'first_name', 'last_name', 'email', 'phone'];
            $searchData['value'] = $criteria['search'];
        }
        return $this->baseRepository->getBy(criteria: $data, searchCriteria: $searchData, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery);
    }

    public function create(array $data): ?Model
    {
        $firstLevel = $this->userLevelRepository->findOneBy(criteria: ['user_type' => CUSTOMER, 'sequence' => 1]);
        $identityImages = [];
        if (array_key_exists('identity_images', $data)) {
            foreach ($data['identity_images'] as $image) {
                $identityImages[] = fileUploader('customer/identity/', APPLICATION_IMAGE_FORMAT, $image);
            }
        }

        if (array_key_exists('other_documents', $data)) {
            $otherDocuments = [];
            foreach ($data['other_documents'] as $document) {
                $otherDocuments[] = fileUploader('customer/document/', $document->getClientOriginalExtension(), $document);
            }
        }
        $customerData = array_merge($data, [
            'full_name' => $data['first_name'] . " " . $data['last_name'],
            'user_type' => CUSTOMER,
            'password' => array_key_exists('password', $data) ? bcrypt($data['password']) : null,
            'user_level_id' => $firstLevel?->id,
            'profile_image' => array_key_exists('profile_image', $data) ? fileUploader('customer/profile/', APPLICATION_IMAGE_FORMAT, $data['profile_image']) : null,
            'other_documents' => $otherDocuments ?? null,
            'identification_image' => $identityImages ?? null,
            'is_active' => 1,
            'ref_code' => generateReferralCode(),
        ]);
        DB::beginTransaction();

        $customer = $this->userRepository->create($customerData);

        $customer?->userAccount()->create();
        DB::commit();
        return $customer;
    }
    public function createAfterOtpMatch(array $data): ?Model {
        $firstLevel = $this->userLevelRepository->findOneBy(criteria: ['user_type' => CUSTOMER, 'sequence' => 1]);
        $identityImages = [];
        if (array_key_exists('identity_images', $data)) {
            foreach ($data['identity_images'] as $image) {
                $identityImages[] = fileUploader('customer/identity/', APPLICATION_IMAGE_FORMAT, $image);
            }
        }

        if (array_key_exists('other_documents', $data)) {
            $otherDocuments = [];
            foreach ($data['other_documents'] as $document) {
                $otherDocuments[] = fileUploader('customer/document/', $document->getClientOriginalExtension(), $document);
            }
        }
        $customerData =  [
            'full_name' => $data['first_name'],
            'user_level_id' => $firstLevel?->id,
            'first_name' => $data['first_name'],
            'phone' => $data['phone'],
            'phone_verified_at' => now(),
            'other_documents' => $otherDocuments ?? null,
            'identification_image' => $identityImages ?? null,
            'ref_code' => generateReferralCode(),
            'user_type' => CUSTOMER,
            'is_active' => 1,
            'logged_in_via' => 'otp'
        ];

        DB::beginTransaction();

        $customer = $this->userRepository->create($customerData);

        $customer?->userAccount()->create();
        DB::commit();
        return $customer;
    }

    public function createExternalCustomer(array $data): ?Model
    {
        $firstLevel = $this->userLevelRepository->findOneBy(criteria: ['user_type' => CUSTOMER, 'sequence' => 1]);

        $customerData = array_merge($data, [
            'full_name' => $data['first_name'] . " " . $data['last_name'],
            'user_type' => CUSTOMER,
            'user_level_id' => $firstLevel?->id,
            'is_active' => 1,
            'ref_code' => generateReferralCode(),
            'identification_image' => []
        ]);
        DB::beginTransaction();
        $customer = $this->userRepository->create($customerData);
        $customer?->userAccount()->create();
        DB::commit();
        return $customer;
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $customer = $this->userRepository->findOne(id: $id);
        $customerData = $data;
        if (array_key_exists('existing_identity_images', $data)) {
            $oldIdentityImages = $data['existing_identity_images'];
            foreach ($customer?->identification_image as $id_image) {
                if (!in_array($id_image, $oldIdentityImages)) {
                    fileRemover('customer/identity/', $id_image);
                }
            }
        }

        if (array_key_exists('identity_images', $data)) {
            foreach ($data['identity_images'] as $image) {
                $newIdentityImages[] = fileUploader('customer/identity/', APPLICATION_IMAGE_FORMAT, $image);
            }
        }
        $identityImages = array_merge($oldIdentityImages ?? [], $newIdentityImages ?? []);
        $existingDocuments = array_key_exists('existing_documents', $data) ? $data['existing_documents'] : [];

        // Remove deleted documents from the existing list
        $documents = $existingDocuments;
        // Handle new uploads
        if ($data['other_documents'] ?? null) {
            foreach ($data['other_documents'] as $doc) {
                $extension = $doc->getClientOriginalExtension();
                $documents[] = fileUploader('customer/document/', $extension, $doc);
            }
        }
        $customerData['other_documents'] = $documents;
        if (array_key_exists('profile_image', $data)) {
            $profile_image = fileUploader('customer/profile/', APPLICATION_IMAGE_FORMAT, $data['profile_image'], $customer?->profile_image);
        }
        if (array_key_exists('password', $data) && !is_null($data['password'])) {
            $password = bcrypt($data['password']);
            $customerData = array_merge($customerData, [
                'password' => $password
            ]);
        } else {
            unset($customerData['password']);
        }


        $customerData = array_merge($customerData, [
            'full_name' => $data['first_name'] . " " . $data['last_name'],
            'loyalty_points' => array_key_exists('decrease', $data) ? $customer->loyalty_points -= $data['decrease'] : (array_key_exists('increase', $data) ? $customer->loyalty_points += $data['increase'] : 0),
            'profile_image' => $profile_image ?? $customer?->profile_image,
            'identification_image' => $identityImages,
            'is_active' => $customer?->is_active ?? 1,
        ]);

        DB::beginTransaction();
        $customer = $this->userRepository->update(id: $id, data: $customerData);

        // Customer Address
        if (array_key_exists('address', $data)) {
            $address = $customer?->addresses()->where(['user_id' => $customer?->id, 'address_label' => 'default'])->first();
            if (is_null($address)) {
                $customer?->addresses()->create([
                    'address' => $data['address'],
                    'address_label' => 'default'
                ]);
            } else {
                $address->address = $data['address'];
                $address->save();
            }
        }
        DB::commit();
        return $customer;
    }
    public function updateLoyaltyPoint(int|string $id, array $data = []): ?Model
    {
       $this->userRepository->update(id: $id, data: $data);
       return $this->userRepository->findOne(id: $id);
    }

    public function updateExternalCustomer(int|string $id, array $data = []): ?Model
    {
        $customer = $this->userRepository->findOne(id: $id);
        $customerData = $data;
        $customerData = array_merge($customerData, [
            'full_name' => $data['first_name'] . " " . $data['last_name'],
            'is_active' => $customer?->is_active ?? 1,
        ]);
        DB::beginTransaction();
        $customer = $this->userRepository->update(id: $id, data: $customerData);
        DB::commit();
        return $customer;
    }

    public function show(int|string $id, array $data)
    {
        $customer = $this->userRepository
            ->findOneBy(criteria: ['id' => $id, 'user_type' => CUSTOMER], relations: ['customerTrips']);
        $otherData = [];
        $tab = $data['tab'] ?? 'overview';
        $reviewedBy = $data['reviewed_by'] ?? null;

        $customerRateInfoData = $this->customerRateInfo($customer);
        $customerTotalReviews = $customer->givenReviews()->count();
        $customerTotalReceivedReviews = $customer->receivedReviews()->count();
        $commonData = [
            'customer_total_review_count' => $customerTotalReviews,
            'customer_total_received_review_count' => $customerTotalReceivedReviews,
            'customer_lowest_fare' => $customerRateInfoData['customer_lowest_fare'],
            'customer_highest_fare' => $customerRateInfoData['customer_highest_fare'],
            'digitalPaymentPercentage' => $customerRateInfoData['digitalPaymentPercentage'],
            'total_success_request' => $customerRateInfoData['total_success_request'],
            'success_percentage' => $customerRateInfoData['success_percentage'],
            'cancel_percentage' => $customerRateInfoData['cancel_percentage'],
            'total_cancel_request' => $customerRateInfoData['total_cancel_request'],
            'tab' => $tab,
            'customer' => $customer,
        ];

        if ($tab == 'overview') {
            $overview_data = $this->overview($customer);
            $otherData = [
                'customer_level_point_goal' => $overview_data['customer_level_point_goal'],
                'targeted_ride_point' => $overview_data['targeted_ride_point'],
                'targeted_amount_point' => $overview_data['targeted_amount_point'],
                'targeted_cancel_point' => $overview_data['targeted_cancel_point'],
                'targeted_review_point' => $overview_data['targeted_review_point']
            ];
        } else if ($tab == 'trips') {
            $tripCriteria = [
                'customer_id' => $id,
                // 'type' => 'ride_request'
            ];
            $searchTripCriteria = [];
            if (array_key_exists('search', $data)) {
                $searchTripCriteria = [
                    'fields' => ['ref_id'],
                    'value' => $data['search'],
                ];
            }
            $customerTrips = $this->tripRequestRepository->getBy(criteria: $tripCriteria, searchCriteria: $searchTripCriteria, orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $data['page'] ?? 1);
            $otherData = [
                'trips' => $customerTrips,
                'search' => $data['search'] ?? '',
            ];
        } else if ($tab == 'transaction') {
            $transactionCriteria['user_id'] = $id;
            $searchTransactionCriteria = [];
            if (array_key_exists('search', $data)) {
                $searchTransactionCriteria = [
                    'fields' => ['id'],
                    'value' => $data['search'],
                ];
            }

            $transactions = $this->transactionRepository
                ->getBy(criteria: $transactionCriteria, searchCriteria: $searchTransactionCriteria, relations: ['user'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $data['page'] ?? 1);

            $otherData = [
                'transactions' => $transactions,
                'search' => $data['search'] ?? '',
            ];

        } else if ($tab == 'review') {
            $customer_given_reviews = $customer?->givenReviews()->orderBy('created_at', 'desc')->paginate(paginationLimit());
            $driver_reviews = $customer?->receivedReviews()->orderBy('created_at', 'desc')->paginate(paginationLimit());

            $otherData = [
                'customer_given_reviews' => $customer_given_reviews,
                'driver_reviews' => $driver_reviews,
                'reviewed_by' => $reviewedBy,
            ];
        }

        return [
            'customer' => $customer,
            'commonData' => $commonData,
            'otherData' => $otherData
        ];
    }

    private function customerRateInfo($customer)
    {
        $totalRequests = $customer->customerTrips()->count();
        $totalDigitalPayments = $customer->customerTrips()->whereNotIn('payment_method', ['cash', 'wallet'])->count();
        $digitalPaymentPercentage = $totalRequests == 0 ? 0 : ($totalDigitalPayments / $totalRequests) * 100;

        //customer completed review count
        $customerCompletedReviewCount = $customer->givenReviews()
            ->whereHas('trip', function ($query) {
                $query->where('current_status', 'completed');
            })
            ->whereNotNull('feedback')->count() ?? 0;

        //total success rate
        $totalSuccessRequest = $customer->customerTrips()->where('current_status', 'completed')->count();
        $successPercentage = $totalSuccessRequest == 0 ? 0 : ($totalSuccessRequest / $totalRequests) * 100;

        //total cancel rate
        $totalCancelRequest = $customer->customerTrips()->where('current_status', 'cancelled')->count();
        $cancelPercentage = $totalCancelRequest == 0 ? 0 : ($totalCancelRequest / $totalRequests) * 100;

        //trip info of customer details
        $customerLowestFare = $customer->customerTrips()->where('current_status', 'completed')->min('paid_fare');
        $customerHighestFare = $customer->customerTrips()->where('current_status', 'completed')->max('paid_fare');

        return [
            'customer_completed_review_count' => $customerCompletedReviewCount,
            'customer_lowest_fare' => $customerLowestFare,
            'customer_highest_fare' => $customerHighestFare,
            'digitalPaymentPercentage' => $digitalPaymentPercentage,
            'total_success_request' => $totalSuccessRequest,
            'success_percentage' => $successPercentage,
            'cancel_percentage' => $cancelPercentage,
            'total_cancel_request' => $totalCancelRequest,
        ];
    }

    private function overview($customer)
    {
        //customer level calculation
        $targetedAmountPoint = $targetedReviewPoint = $targetedCancelPoint = $targetedRidePoint = 0;
        $customerLevelPointGoal = $customer->level()->selectRaw('(targeted_ride_point + targeted_amount_point + targeted_cancel_point + targeted_review_point) as level_point')
            ->first()?->level_point;
//        dd($customerLevelPointGoal);
        $customerLevel = $customer->level()->first();
        $customer_level_history = $customer->latestLevelHistory()->first();
        if (!empty($customer_level_history)) {
            if ($customer_level_history->ride_reward_status == 1) {
                $targetedRidePoint = $customerLevel->targeted_ride_point;
            } else {
                $targetedRidePoint = 0;
            }
            if ($customer_level_history->amount_reward_status == 1) {
                $targetedAmountPoint = $customerLevel->targeted_amount_point;
            } else {
                $targetedAmountPoint = 0;
            }

            if ($customer_level_history->cancellation_reward_status == 1) {
                $targetedCancelPoint = $customerLevel->targeted_cancel_point;
            } else {
                $targetedCancelPoint = 0;
            }

            if ($customer_level_history->reviews_reward_status == 1) {
                $targetedReviewPoint = $customerLevel->targeted_review_point;
            } else {
                $targetedReviewPoint = 0;
            }
        }
        return [
            'customer_level_point_goal' => $customerLevelPointGoal,
            'targeted_ride_point' => $targetedRidePoint,
            'targeted_amount_point' => $targetedAmountPoint,
            'targeted_cancel_point' => $targetedCancelPoint,
            'targeted_review_point' => $targetedReviewPoint,
        ];
    }


    public function loyalCustomerCount($loyalLevelId)
    {
        return $this->userRepository->loyalCustomer($loyalLevelId)->count();
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            $identificationData = $item['identification_type'] && $item['identification_number'] ? ucwords($item['identification_type']) . ': ' . $item['identification_number'] : '-';
            return [
                'id' => $item['id'],
                'Name' => $item['first_name'] . ' ' . $item['last_name'],
                'Email' => $item['email'],
                'Phone' => $item['phone'],
                'Profile Status' => $item['completion_percent'] . '%',
                'Identification' => $identificationData,
                'Level' => $item->level->name ?? 'No level attached',
                'Total Trip' => $item->customerTrips->count(),
                'Status' => $item['is_active'] ? 'Active' : 'Inactive',
            ];
        });
    }

    public function trashedData(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator
    {
        $data['user_type'] = CUSTOMER;
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['full_name', 'first_name', 'last_name', 'email', 'phone'];
            $searchData['value'] = $criteria['search'];
        }
        return $this->userRepository->getBy(criteria: $data, searchCriteria: $searchData, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, onlyTrashed: true, withCountQuery: $withCountQuery);
    }

    public function changeLanguage(int|string $id, array $data = []): ?Model
    {
        return $this->userRepository->update(id: $id, data: $data);
    }

    #handshake mart
    public function walletTransfer($customer, array $data = [])
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $data) {
            $account = $customer->userAccount()->lockForUpdate()->first();
            if (array_key_exists('type', $data) && $data['type'] == 'debit') {
                if ($account->wallet_balance < $data['amount']) {
                    throw new \RuntimeException('insufficient_fund');
                }
                $account->decrement('wallet_balance', $data['amount']);
                $account->refresh();
                $transferData = [
                    'attribute' => 'wallet_transfer_vito_to_mart',
                    'debit' => $data['amount'],
                    'balance' => $account->wallet_balance,
                    'user_id' => $customer->id,
                    'account' => 'wallet_balance',
                ];
            } else {
                $account->increment('wallet_balance', $data['amount']);
                $account->refresh();
                $transferData = [
                    'attribute' => 'wallet_transfer_vito_from_mart',
                    'credit' => $data['amount'],
                    'balance' => $account->wallet_balance,
                    'user_id' => $customer->id,
                    'account' => 'wallet_balance',
                ];
            }
            $this->transactionRepository->create($transferData);
        });
    }
}
