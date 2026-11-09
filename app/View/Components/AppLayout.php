<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    private const PAGE_TITLES = [
        'dashboard' => 'Admin dashboard',
        'admin.moderation' => 'Moderation',
        'admin.chat-reports.index' => 'Chat reports',
        'admin.seller-applications.index' => 'Seller applications',
        'admin.seller-applications.show' => 'Review application',
        'admin.orders.index' => 'Marketplace orders',
        'admin.orders.show' => 'Order details',
        'admin.payouts.index' => 'Seller payouts',
        'admin.products.index' => 'Marketplace products',
        'admin.products.show' => 'Product moderation',
        'admin.stores.index' => 'Stores overview',
        'admin.stores.show' => 'Store details',
        'admin.customers.index' => 'Customers',
        'admin.customers.show' => 'Customer details',
        'admin.audit-logs.index' => 'Audit log',
        'admin.accounts.index' => 'Admin management',
        'admin.reviews.index' => 'Review moderation',
        'cart.index' => 'Shopping cart',
        'checkout.create' => 'Secure checkout',
        'orders.index' => 'My purchases',
        'orders.show' => 'Order details',
        'profile.edit' => 'Account settings',
        'seller-application.create' => 'Become a seller',
        'seller.dashboard' => 'Seller dashboard',
        'seller.products.index' => 'My products',
        'seller.products.create' => 'Add product',
        'seller.products.edit' => 'Edit product',
        'seller.orders.index' => 'Seller orders',
        'seller.orders.show' => 'Seller order details',
        'seller.earnings.index' => 'Earnings',
        'seller.reviews.index' => 'Product reviews',
    ];

    /**
     * Pages that render their own validation banner pass false so errors are not shown twice.
     */
    public function __construct(public bool $errorSummary = true) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $routeName = request()->route()?->getName() ?? '';

        return view('layouts.app', [
            'pageTitle' => self::PAGE_TITLES[$routeName] ?? null,
        ]);
    }
}
