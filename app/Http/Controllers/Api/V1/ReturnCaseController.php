<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReturnCaseResource;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnCaseController extends Controller
{
    public function store(Request $request, SellerOrder $sellerOrder, ReturnCaseWorkflow $workflow): JsonResponse
    {
        $this->authorize('openReturn', $sellerOrder);

        $data = $request->validate([
            'reason' => ['required', 'in:damaged,defective,wrong_item,misdescribed'],
            'requested_remedy' => ['required', 'in:refund,replacement'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:order_item_id,quantity'],
            'items.*.order_item_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $case = $workflow->open($sellerOrder, $request->user(), $data);

        return $this->payload($case, $request)->setStatusCode(201);
    }

    public function show(Request $request, ReturnCase $returnCase): JsonResponse
    {
        $this->authorize('view', $returnCase);

        return $this->payload($returnCase, $request);
    }

    public function update(Request $request, ReturnCase $returnCase, ReturnCaseWorkflow $workflow): JsonResponse
    {
        $this->authorize('respond', $returnCase);

        $data = $request->validate([
            'action' => ['required', 'in:accept,appeal,handoff,confirm'],
            'note' => ['required_if:action,appeal', 'nullable', 'string', 'min:10', 'max:2000'],
            'proof' => ['required_if:action,handoff', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $workflow->act($returnCase, $request->user(), $data['action'], $data);

        return $this->payload($returnCase->fresh(), $request);
    }

    private function payload(ReturnCase $case, Request $request): JsonResponse
    {
        $case->load([
            'sellerOrder.store',
            'sellerOrder.order',
            'buyer',
            'items.orderItem',
            'events.actor',
            'evidence',
            'outcome',
        ]);

        return ReturnCaseResource::make($case)->response($request);
    }
}
