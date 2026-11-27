<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PhilippineLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The province, city and barangay lists behind the address dropdowns on the website and app.
 */
class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PhilippineLocations::provinces()])
            ->setPublic()
            ->setMaxAge(86400);
    }

    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(['data' => PhilippineLocations::barangays($data['province'], $data['city'])])
            ->setPublic()
            ->setMaxAge(86400);
    }
}
