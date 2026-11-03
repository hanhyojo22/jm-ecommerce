<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * The signed-in account. The first authenticated request also provisions the
     * local buyer row through the existing Clerk resolver.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response($request);
    }
}
