<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Family members whose medicine the buyer manages. Deleting a profile deletes its plans.
 */
class CareProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->careProfiles()->oldest()->get(['id', 'name', 'relation'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->careProfiles()->create($this->validated($request));

        return response()->json(['data' => $profile->only(['id', 'name', 'relation'])], 201);
    }

    public function update(Request $request, string $careProfile): JsonResponse
    {
        $profile = $request->user()->careProfiles()->findOrFail($careProfile);
        $profile->update($this->validated($request));

        return response()->json(['data' => $profile->only(['id', 'name', 'relation'])]);
    }

    public function destroy(Request $request, string $careProfile): Response
    {
        $request->user()->careProfiles()->findOrFail($careProfile)->delete();

        return response()->noContent();
    }

    /**
     * @return array{name: string, relation?: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:60'], 'relation' => ['nullable', 'string', 'max:30']]);
    }
}
