<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DiscountCardResource;
use App\Models\DiscountCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiscountCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return DiscountCardResource::collection(
            $request->user()->discountCards()->latest()->get(),
        )->response();
    }

    public function destroy(DiscountCard $discountCard): Response
    {
        $this->authorize('delete', $discountCard);

        $discountCard->delete();

        return response()->noContent();
    }
}
