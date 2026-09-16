<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Controllers;

use Illuminate\Routing\Controller;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Reusable controller base.
 *
 * Bundles the framework's authorization + validation traits with the toolkit's
 * {@see ApiResponseTrait} so subclasses get a consistent `authorize()`,
 * `validate()` and `successResponse()/errorResponse()` surface out of the box.
 */
abstract class BaseController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesRequests;
    use ValidatesRequests;
}
