<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Services\AnalyticsService;
use Fleetbase\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Variance / exception analytics for delivery tickets in the session company.
     *
     * Query: days (default 30), operation (uuid|code), product (uuid|code)
     */
    public function variance(Request $request)
    {
        $company = (string) session('company');
        $days    = max(1, min((int) $request->input('days', 30), 365));

        return response()->json(app(AnalyticsService::class)->variance($company, [
            'days'      => $days,
            'operation' => $request->input('operation'),
            'product'   => $request->input('product'),
        ]));
    }

    /**
     * Per-vehicle utilisation of compartment capacity across recent load plans.
     */
    public function utilization(Request $request)
    {
        $company = (string) session('company');
        $days    = max(1, min((int) $request->input('days', 30), 365));

        return response()->json(app(AnalyticsService::class)->utilization($company, $days));
    }
}
