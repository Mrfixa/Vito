<?php

namespace Modules\ChattingManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\ChattingManagement\Repository\ChannelConversationRepositoryInterface;
use Modules\TripManagement\Entities\MartOrder;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;

class ChannelConversationService extends BaseService implements Interfaces\ChannelConversationServiceInterface
{
    protected $channelConversationRepository;
    protected $tripRequestRepository;
    public function __construct(ChannelConversationRepositoryInterface $channelConversationRepository, TripRequestRepositoryInterface $tripRequestRepository)
    {
        parent::__construct($channelConversationRepository);
        $this->channelConversationRepository = $channelConversationRepository;
        $this->tripRequestRepository = $tripRequestRepository;
    }

    public function create(array $data): ?Model
    {
        if (array_key_exists('order_id', $data) && $data['order_id']) {
            $order = MartOrder::find($data['order_id']);
            $conversation = $order?->conversations()->create($data);
        } elseif (array_key_exists('trip_id', $data) && $data['trip_id']) {
            $trip = $this->tripRequestRepository->findOne($data['trip_id']);
            $conversation = $trip?->conversations()->create($data);
        } else{
            $conversation = $this->channelConversationRepository->create($data);
        }
        if (array_key_exists('files', $data)) {
            foreach ($data['files'] as $file) {
                $extension = $file->getClientOriginalExtension();
                $conversation?->conversation_files()->create([
                    'file_name' => fileUploader('conversation/', $extension, $file),
                    'file_type' => $extension,
                ]);
            }
        }
        return $conversation;
    }

}
