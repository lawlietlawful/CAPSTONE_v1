<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\BaseSeminarController;

/**
 * Guidance Counselor-side seminar management. All logic lives in
 * BaseSeminarController; this subclass only points the shared logic at the
 * 'counselor' route/view namespace.
 */
class SeminarController extends BaseSeminarController
{
    protected string $prefix = 'counselor';
}
