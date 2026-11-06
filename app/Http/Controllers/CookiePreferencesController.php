<?php

namespace App\Http\Controllers;

use App\Queries\RecommendationQuery;
use App\Support\CookiePreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookiePreferencesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $preferences = $request->validate([
            'functional' => ['required', 'boolean'],
            'analytics' => ['required', 'boolean', 'in:0'],
            'marketing' => ['required', 'boolean', 'in:0'],
        ]);

        if (! (bool) $preferences['functional']) {
            RecommendationQuery::clearHistory($request);
        }

        return response()->json(['saved' => true])->cookie(
            CookiePreferences::COOKIE_NAME,
            json_encode([
                'version' => CookiePreferences::VERSION,
                'functional' => (bool) $preferences['functional'],
                'analytics' => false,
                'marketing' => false,
            ], JSON_THROW_ON_ERROR),
            60 * 24 * 180,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax'),
        );
    }
}
