<?php

namespace App\Contracts;

use App\Models\Underlying;

interface AlertEngineInterface
{
    /**
     * Evaluate real-time conditions and dispatch alerts:
     * - Fresh Call/Put Writing
     * - Heavy OI Build-up / Unwinding
     * - Support / Resistance Shifts
     * - Possible Breakout / Breakdown
     */
    public function evaluateAndDispatch(Underlying $underlying, array $currentAnalysis, ?array $previousAnalysis = null): array;
}
