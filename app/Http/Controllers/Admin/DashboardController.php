<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminModerationQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $filters = $request->validate([
            'type' => ['nullable', 'in:all,application,appeal,product_report,chat_report'],
            'sort' => ['nullable', 'in:oldest,newest'],
        ]);
        $activeType = $filters['type'] ?? 'all';
        $activeSort = $filters['sort'] ?? 'oldest';
        $counts = AdminModerationQuery::counts();
        $queueItems = AdminModerationQuery::items($activeType, $activeSort)->map(function (array $item): array {
            [$url, $action] = match ($item['type']) {
                'application' => [route('admin.seller-applications.show', $item['id']), 'Review'],
                'appeal' => [route('admin.stores.show', $item['store_id']).'#suspension-appeals', 'Review appeal'],
                'product_report' => [route('admin.reviews.index', ['review' => $item['id']]).'#review-'.$item['id'], 'Review report'],
                'chat_report' => [route('admin.chat-reports.index', ['report' => $item['id']]).'#chat-report-'.$item['id'], 'Review report'],
            };

            return [...$item, 'url' => $url, 'action' => $action];
        });
        $dataCheckedAt = now();

        return view('dashboard', compact('activeSort', 'activeType', 'counts', 'dataCheckedAt', 'queueItems'));
    }
}
