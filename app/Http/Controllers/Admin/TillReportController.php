<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TillSession;
use App\Services\TillService;

class TillReportController extends Controller
{
    public function show(TillSession $till, TillService $service)
    {
        return view('pos.closing-report', ['till' => $till->load(['closedBy', 'acknowledgedBy', 'location']), 'report' => $service->report($till)]);
    }
}
