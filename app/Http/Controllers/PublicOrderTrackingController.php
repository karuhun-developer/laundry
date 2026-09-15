<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class PublicOrderTrackingController extends Controller
{
    public function __invoke(Order $order): View
    {
        $order->load(['branch:id,name', 'statusEvents']);

        return view('orders.track', [
            'order' => $order,
            'canonicalUrl' => request()->fullUrl(),
        ]);
    }
}
