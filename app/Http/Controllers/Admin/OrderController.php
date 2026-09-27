<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        return view('admin.orders.index', [
            'orders' => Order::query()->with('payment')->latest()->paginate(25),
        ]);
    }
}
