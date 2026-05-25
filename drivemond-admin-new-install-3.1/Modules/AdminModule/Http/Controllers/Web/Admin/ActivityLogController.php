<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;

class ActivityLogController extends BaseController
{
    protected $activityLogService;

    public function __construct(ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($activityLogService);
        $this->activityLogService = $activityLogService;
    }

    public function log(\Illuminate\Http\Request $request)
    {
        $logs = $this->activityLogService->log(data: $request->all());
        return view('adminmodule::activity-log', compact('logs'));
    }
}
