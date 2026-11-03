<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OpenOrderIssue;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderIssueResource;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderIssueController extends Controller
{
    public function index(Request $request, SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('view', $sellerOrder);

        return OrderIssueResource::collection(
            $sellerOrder->issues()->latest()->get(),
        )->response($request);
    }

    public function store(Request $request, SellerOrder $sellerOrder, OpenOrderIssue $openIssue): JsonResponse
    {
        $this->authorize('openIssue', $sellerOrder);

        $data = $request->validate([
            'type' => ['required', 'in:cancellation,delivery,other'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $issue = $openIssue->handle($sellerOrder, $request->user(), $data['type'], $data['details']);

        return OrderIssueResource::make($issue)->response($request)->setStatusCode(201);
    }
}
