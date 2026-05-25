<?php

namespace Modules\AiModule\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ZoneManagement\Entities\Zone;

interface AiServiceInterface
{
    public function generate(string $prompt, ?string $imageUrl = null, array $options = []): string;

    public function getName(): string;
}
