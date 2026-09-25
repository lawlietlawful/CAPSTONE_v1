<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AbstractBehavioralReportController;

/**
 * Behavioral Reports for the admin pages. All behaviour lives in
 * AbstractBehavioralReportController; this only says which view set to use.
 */
class BehavioralReportController extends AbstractBehavioralReportController
{
    protected function area(): string
    {
        return 'admin';
    }
}
