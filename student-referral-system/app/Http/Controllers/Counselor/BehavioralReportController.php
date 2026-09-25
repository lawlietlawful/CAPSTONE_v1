<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\AbstractBehavioralReportController;

/**
 * Behavioral Reports for the counselor pages. All behaviour lives in
 * AbstractBehavioralReportController; this only says which view set to use.
 */
class BehavioralReportController extends AbstractBehavioralReportController
{
    protected function area(): string
    {
        return 'counselor';
    }
}
