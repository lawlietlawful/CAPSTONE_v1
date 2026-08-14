<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseSeminarController;

/**
 * Admin-side seminar management. All logic lives in BaseSeminarController;
 * this subclass only points the shared logic at the 'admin' route/view
 * namespace.
 */
class SeminarController extends BaseSeminarController
{
    protected string $prefix = 'admin';
}
