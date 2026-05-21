<?php

namespace Modules\ChattingManagement\Http\Controllers\Api;

use App\Events\CustomerMartOrderChatEvent;
use App\Events\CustomerRideChatEvent;
use App\Events\DriverMartOrderChatEvent;
use App\Events\DriverRideChatEvent;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\AdminModule\Service\Interfaces\AdminNotificationServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\QuestionAnswerServiceInterface;
use Modules\ChattingManagement\Http\Requests\StoreSendMessageRequest;
use Modules\ChattingManagement\Service\Interfaces\ChannelConversationServiceInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelListServiceInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelUserServiceInterface;
use Modules\ChattingManagement\Transformers\ChannelConversationResource;
use Modules\ChattingManagement\Transformers\ChannelListResource;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\UserService;

class ChattingController extends Controller
{
    protected $channelConversationService;
    protected $channelListService;
    protected $channelUserService;
    protected $tripRequestService;
    protected $userService;

    protected $adminNotificationService;
    protected $questionAnswerService;

    public function __construct(ChannelConversationServiceInterface $channelConversationService, ChannelListServiceInterface $channelListService,
                                ChannelUserServiceInterface         $channelUserService, TripRequestServiceInterface $tripRequestService, UserService $userService,
                                AdminNotificationServiceInterface   $adminNotificationService, QuestionAnswerServiceInterface $questionAnswerService
    )
    {
        $this->channelConversationService = $channelConversationService;
        $this->channelListService = $channelListService;
        $this->channelUserService = $channelUserService;
        $this->tripRequestService = $tripRequestService;
        $this->userService = $userService;
        $this->adminNotificationService = $adminNotificationService;
        $this->questionAnswerService = $questionAnswerService;
    }

    public function findChannel(Request $request)
    {
        $channel = $this->channelListService->findOne($request['channel_id']);
        if (!$channel) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }

        $isMember = $this->channelUserService->findOneBy(criteria: [
            'channel_id' => $channel->id,
            'user_id' => $request->user()->id,
        ]);
        if (!$isMember) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }

        if ($channel->channelable_type === MartOrder::class) {
            $order = MartOrder::find($channel->channelable_id);
            return response()->json(responseFormatter(DEFAULT_200, $order?->status), 200);
        }
        $trip = $this->tripRequestService->findOne($channel->channelable?->id);
        return response()->json(responseFormatter(DEFAULT_200, $trip?->current_status), 200);
    }


    public function channelList(Request $request): JsonResponse
    {
        $type = $request->get('type', 'trip');
        $channelableType = $type === 'mart' ? MartOrder::class : TripRequest::class;
        $criteria = ['channelable_type' => $channelableType];
        $relation = [
            'channel_users.user', 'channel_conversations', 'last_channel_conversations.conversation_files',
        ];
        $whereHasRelation = [
            'channel_conversations' => [],
            'last_channel_conversations' => [],
            'channel_users' => [['user_id', '=', $request->user()->id]],
        ];
        $chatList = $this->channelListService->getBy(criteria: $criteria, whereHasRelations: $whereHasRelation, relations: $relation, orderBy: ['updated_at' => 'DESC'], limit: $request['limit'], offset: $request['offset']);
        $chatList = ChannelListResource::collection($chatList);
        return response()->json(responseFormatter(DEFAULT_200, $chatList, $request['limit'], $request['offset']));
    }

    public function createChannel(Request $request): JsonResponse
    {
        if ($request->has('order_id')) {
            return $this->createMartChannel($request);
        }

        $channelIds = $this->channelUserService->getBy(criteria: ['user_id' => $request->user()->id]);
        $channelIds = $channelIds->pluck('channel_id')->toArray();

        $whereInCriteria = [
            'channel_id' => $channelIds
        ];
        $criteria = [
            'user_id' => $request['to']
        ];
        $user = $this->userService->findOne(id: $request['to']);
        $channelUser = $this->channelUserService->findOneBy(criteria: $criteria, whereInCriteria: $whereInCriteria);
        if ($channelUser) {
            $findChannel = $this->channelListService->findOne($channelUser?->channel_id);
            if ($findChannel) {
                $request->merge(['channelable_id' => $request['trip_id']]);
                $findChannel = $this->channelListService->update(id: $findChannel?->id, data: $request->all());
                return response()->json(responseFormatter(DEFAULT_200, ['user' => $user, 'channel' => ChannelListResource::make($findChannel)]), 200);
            }
        }
        $channel = $this->channelListService->createChannelWithChannelUser($request->all());
        return response()->json(responseFormatter(DEFAULT_STORE_200, ['user' => $user, 'channel' => ChannelListResource::make($channel)]), 200);
    }

    private function createMartChannel(Request $request): JsonResponse
    {
        $order = MartOrder::find($request['order_id']);
        if (!$order) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $currentUser = $request->user();
        if ($currentUser->id !== $order->customer_id && $currentUser->id !== $order->driver_id) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }

        $toUserId = $currentUser->user_type === CUSTOMER ? $order->driver_id : $order->customer_id;
        if (!$toUserId) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $user = $this->userService->findOne(id: $toUserId);

        if ($order->channel) {
            return response()->json(responseFormatter(DEFAULT_200, ['user' => $user, 'channel' => ChannelListResource::make($order->channel)]), 200);
        }

        $channel = $order->channel()->create(['created_at' => now()]);
        $channel->channel_users()->createMany([
            ['user_id' => $order->customer_id],
            ['user_id' => $order->driver_id],
        ]);

        return response()->json(responseFormatter(DEFAULT_STORE_200, ['user' => $user, 'channel' => ChannelListResource::make($channel)]), 200);
    }


    public function sendMessage(StoreSendMessageRequest $request): JsonResponse
    {
        $channel = $this->channelListService->findOne($request['channel_id']);

        if ($channel && $channel->channelable_type === MartOrder::class) {
            return $this->sendMartOrderMessage($request);
        }

        $column = 'customer_id';
        if ($request->user()->user_type == 'driver') {
            $column = 'driver_id';
        }
        $attributes[$column] = auth()->user()->id;
        $relations = ['customer', 'driver'];
        $whereInCriteria = [
            'current_status' => [ONGOING, OUT_FOR_PICKUP, ACCEPTED]
        ];
        $trip = $this->tripRequestService->findOneBy(criteria: $attributes, whereInCriteria: $whereInCriteria, relations: $relations);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        $user = auth()->user();
        DB::beginTransaction();
        $this->channelListService->update(id: $request['channel_id'], data: ['updated_at' => now()]);
        $channelUserData = [
            'is_read' => false,
        ];

        $this->channelUserService->updatedBy(criteria: ['channel_id' => $request['channel_id'], 'user_id' => $user->id], data: $channelUserData);
        $attributes = [
            'channel_id' => $request['channel_id'],
            'message' => $request['message'],
            'user_id' => $user->id,
            'trip_id' => $request['trip_id'],
            'is_read' => 0,
        ];
        if ($request->has('files')) {
            $attributes['files'] = $request->file('files');
        }
        $channelConversation = $this->channelConversationService->create($attributes);

        $channelConversationWithFiles = $this->channelConversationService->findOne(id: $channelConversation?->id, relations: ['user', 'conversation_files', 'channel']);
        $to_user = $user->user_type == DRIVER ? $trip->customer : $trip->driver;
        $user_id = $to_user->id;

        if (checkReverbConnection()) {
            try {
                $user->user_type == DRIVER ? CustomerRideChatEvent::broadcast($trip, $channelConversationWithFiles) : DriverRideChatEvent::broadcast($trip, $channelConversationWithFiles);
            } catch (Exception $exception) {

            }
        }

        $this->channelConversationService->updatedBy(criteria: ['user_id' => $user_id, 'channel_id' => $request['channel_id']], data: ['is_read' => 1]);
        $sentTime = pushSentTime($channelConversation->created_at);
        DB::commit();

        $push = getNotification('new_message');

        sendDeviceNotification(
            fcm_token: $to_user->fcm_token,
            title: translate(key:$push['title'], locale: $user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, userName: $user?->full_name ?? $user?->first_name, sentTime: $sentTime, locale: $user?->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $request->channel_id,
            notification_type: 'chatting',
            action: $push['action'],
            user_id: $user_id,
            user_name: $user?->first_name . " " . $user?->last_name
        );
        return response()->json(responseFormatter(DEFAULT_STORE_200), 200);
    }

    private function sendMartOrderMessage(StoreSendMessageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $column = $user->user_type === DRIVER ? 'driver_id' : 'customer_id';

        $channel = $this->channelListService->findOne($request['channel_id']);
        $orderId = $channel?->channelable_id;

        $order = MartOrder::where($column, $user->id)
            ->whereIn('status', ['accepted', 'picked_up'])
            ->with(['customer', 'driver'])
            ->find($orderId);

        if (!$order) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }

        DB::beginTransaction();

        $this->channelListService->update(id: $request['channel_id'], data: ['updated_at' => now()]);
        $this->channelUserService->updatedBy(
            criteria: ['channel_id' => $request['channel_id'], 'user_id' => $user->id],
            data: ['is_read' => false]
        );

        $attributes = [
            'channel_id' => $request['channel_id'],
            'message' => $request['message'],
            'user_id' => $user->id,
            'order_id' => $order->id,
            'is_read' => 0,
        ];
        if ($request->has('files')) {
            $attributes['files'] = $request->file('files');
        }
        $channelConversation = $this->channelConversationService->create($attributes);
        $channelConversationWithFiles = $this->channelConversationService->findOne(id: $channelConversation?->id, relations: ['user', 'conversation_files', 'channel']);

        $to_user = $user->user_type == DRIVER ? $order->customer : $order->driver;
        $user_id = $to_user->id;

        if (checkReverbConnection()) {
            try {
                $user->user_type == DRIVER
                    ? CustomerMartOrderChatEvent::broadcast($order, $channelConversationWithFiles)
                    : DriverMartOrderChatEvent::broadcast($order, $channelConversationWithFiles);
            } catch (Exception $exception) {
                \Illuminate\Support\Facades\Log::warning('Mart chat broadcast failed: ' . $exception->getMessage());
            }
        }

        $this->channelConversationService->updatedBy(
            criteria: ['user_id' => $user_id, 'channel_id' => $request['channel_id']],
            data: ['is_read' => 1]
        );
        $sentTime = pushSentTime($channelConversation->created_at);
        DB::commit();

        $push = getNotification('new_message');
        sendDeviceNotification(
            fcm_token: $to_user->fcm_token,
            title: translate(key: $push['title'], locale: $user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $order->ref_id, userName: $user?->full_name ?? $user?->first_name, sentTime: $sentTime, locale: $user?->current_language_key),
            status: $push['status'],
            ride_request_id: $order->id,
            type: $request->channel_id,
            notification_type: 'chatting',
            action: $push['action'],
            user_id: $user_id,
            user_name: $user?->first_name . ' ' . $user?->last_name
        );

        return response()->json(responseFormatter(DEFAULT_STORE_200), 200);
    }


    public function conversation(Request $request): JsonResponse
    {

        $user = auth()->user();
        $channelUser = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request['channel_id'], ['user_id', '!=', $user->id]]);
        $channelUserData = [
            'is_read' => true,
        ];
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $request['channel_id'], 'user_id' => $user->id], data: $channelUserData);
        $attributes = [
            'channel_id' => $request['channel_id'],
        ];
        $whereHasRelations = [
            'channel.channel_users' => ['user_id' => $user->id]
        ];
        $this->channelConversationService->updatedBy(criteria: ['channel_id' => $request['channel_id'], 'user_id' => $channelUser->user_id], data: ['is_read' => 1]);
        $conversations = $this->channelConversationService->getBy(
            criteria: $attributes,
            whereHasRelations: $whereHasRelations,
            relations: ['user', 'conversation_files'],
            orderBy: ['id' => 'desc'],
            limit: $request['limit'],
            offset: $request['offset']
        );
        $conversations = ChannelConversationResource::collection($conversations);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $conversations, limit: $request['limit'], offset: $request['offset']));
    }

    public function createOrFindChannel(Request $request): JsonResponse
    {
        $channelIds = $this->channelUserService->getBy(criteria: ['user_id' => $request->user()->id]);

        return response()->json();
    }

    public function createChannelWithAdmin(Request $request): JsonResponse
    {
        $channelIds = $this->channelUserService->getBy(criteria: ['user_id' => $request->user()->id]);
        $channelIds = $channelIds->pluck('channel_id')->toArray();

        $whereInCriteria = [
            'channel_id' => $channelIds
        ];
        $superAdmin = $this->userService->findOneBy(criteria: [
            'user_type' => 'super-admin'
        ]);
        $criteria = [
            'user_id' => $superAdmin->id
        ];

        $channelUser = $this->channelUserService->findOneBy(criteria: $criteria, whereInCriteria: $whereInCriteria);

        if ($channelUser) {
            $findChannel = $this->channelListService->findOne($channelUser?->channel_id);
            if ($findChannel) {
                $findChannel = $this->channelListService->update(id: $findChannel?->id, data: $request->all());
                return response()->json(responseFormatter(DEFAULT_200, ['user' => $superAdmin, 'channel' => ChannelListResource::make($findChannel)]), 200);
            }
        }
        $channel = $this->channelListService->createChannelWithAdmin(data: ['to' => $superAdmin->id]);
        return response()->json(responseFormatter(DEFAULT_STORE_200, ['user' => $superAdmin, 'channel' => ChannelListResource::make($channel)]), 200);
    }

    public function sendMessageToAdminFromDriver(StoreSendMessageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $channel = $this->channelListService->findOneBy(criteria: ['id' => $request['channel_id']]);

        if (!$channel) {
            return response()->json(responseFormatter(constant: CHANNEL_NOT_FOUND_404), 403);
        }

        $channelUser = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request['channel_id'], 'user_id' => $user->id]);

        if (!$channelUser) {
            return response()->json(responseFormatter(constant: CHANNEL_NOT_FOUND_404), 403);
        }

        DB::beginTransaction();

        $channelUserData = [
            'is_read' => true,
        ];
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $request['channel_id'], ['user_id', '=', $user->id]], data: $channelUserData);

        $attributes = [
            'channel_id' => $request['channel_id'],
            'message' => $request['message'],
            'user_id' => $user->id,
            'is_read' => 0,
        ];
        if ($request->has('files')) {
            $attributes['files'] = $request->file('files');
        }
        $channelConversation = $this->channelConversationService->create($attributes);
        $adminNotificationData = [
            'model' => 'channel_conversation',
            'model_id' => $channelConversation->id,
            'message' => 'new_message_arrived'
        ];
        $this->adminNotificationService->create($adminNotificationData);
        DB::commit();

        return response()->json(responseFormatter(DEFAULT_STORE_200), 200);
    }

    public function sendPredefinedQuestionToAdminFromDriver(Request $request): JsonResponse
    {
        $user = auth()->user();
        $channel = $this->channelListService->findOneBy(criteria: ['id' => $request['channel_id']]);

        if (!$channel) {
            return response()->json(responseFormatter(constant: CHANNEL_NOT_FOUND_404), 403);
        }

        $channelUser = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request['channel_id'], 'user_id' => $user->id]);

        if (!$channelUser) {
            return response()->json(responseFormatter(constant: CHANNEL_NOT_FOUND_404), 403);
        }
        $channelUserAnother = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request['channel_id'], ['user_id', '!=', $user->id]]);

        $channelUserData = [
            'is_read' => true,
        ];
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $request['channel_id'], ['user_id', '=', $user->id]], data: $channelUserData);
        $questionAnswer = $this->questionAnswerService->findOneBy(criteria: ['id' => $request->question_id]);

        $attributes = [
            'channel_id' => $request['channel_id'],
            'message' => $questionAnswer?->question,
            'user_id' => $user->id,
            'is_read' => 1,
        ];
        $channelConversation = $this->channelConversationService->create($attributes);
        if ($channelConversation) {
            $attributes1 = [
                'channel_id' => $request['channel_id'],
                'message' => $questionAnswer?->answer,
                'user_id' => $channelUserAnother?->user_id,
                'is_read' => 1,
            ];
            $this->channelConversationService->create($attributes1);
        }
        return response()->json(responseFormatter(DEFAULT_STORE_200), 200);
    }

}
