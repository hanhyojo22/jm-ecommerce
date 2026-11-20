<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteCustomerAccount;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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

    /**
     * Every field is optional so older app versions can still send cart_reminders alone.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart_reminders' => ['required_without_all:name,birth_date,phone,sex', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'birth_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'sex' => ['sometimes', 'nullable', Rule::in(['female', 'male'])],
        ]);

        if (array_key_exists('name', $data)) {
            $data['name_confirmed_at'] = now();
        }

        $request->user()->update($data);

        return UserResource::make($request->user())->response($request);
    }

    public function destroy(Request $request, DeleteCustomerAccount $deleteAccount): Response
    {
        $deleteAccount->handle($request->user(), $request->ip());

        return response()->noContent();
    }
}
