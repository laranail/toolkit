<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Support;

use Illuminate\Http\Request;

class QueryParameters
{
    /**
     * Parse query parameters from request.
     *
     * @return array
     */
    public static function parse(Request $request, array $allowedParameters)
    {
        $queryParams = [];

        foreach ($allowedParameters as $param) {
            if ($request->has($param)) {
                $queryParams[$param] = $request->input($param);
            }
        }

        return $queryParams;
    }
}
