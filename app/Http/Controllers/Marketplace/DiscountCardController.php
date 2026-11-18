<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\DiscountCard;
use Illuminate\Http\RedirectResponse;

class DiscountCardController extends Controller
{
    public function destroy(DiscountCard $discountCard): RedirectResponse
    {
        $this->authorize('delete', $discountCard);

        $discountCard->delete();

        return to_route('profile.edit')->with('status', 'Saved discount ID removed.');
    }
}
