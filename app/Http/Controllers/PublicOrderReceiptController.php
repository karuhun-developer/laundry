<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;

class PublicOrderReceiptController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        return redirect()->to(URL::signedRoute('orders.public.show', ['order' => $order->order_number]));
    }
}
