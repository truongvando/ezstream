<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ViewOrder;
use App\Models\ToolOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function viewOrder($orderId)
    {
        $viewOrder = ViewOrder::where('id', $orderId)
                             ->where('user_id', Auth::id())
                             ->with(['apiService', 'transaction'])
                             ->firstOrFail();

        $transaction = $viewOrder->transaction;

        if (!$transaction || $transaction->status !== 'PENDING') {
            return redirect()->route('view-services.index')
                           ->with('error', 'Đơn hàng không hợp lệ hoặc đã được xử lý.');
        }

        return view('payment.view-order', compact('viewOrder', 'transaction'));
    }

    public function toolOrder($orderId)
    {
        $toolOrder = ToolOrder::where('id', $orderId)
                             ->where('user_id', Auth::id())
                             ->with(['tool', 'transaction'])
                             ->firstOrFail();

        $transaction = $toolOrder->transaction;

        if (!$transaction || $transaction->status !== 'PENDING') {
            return redirect()->route('tools.index')
                           ->with('error', 'Đơn hàng không hợp lệ hoặc đã được xử lý.');
        }

        return view('payment.tool-order', compact('toolOrder', 'transaction'));
    }
}
