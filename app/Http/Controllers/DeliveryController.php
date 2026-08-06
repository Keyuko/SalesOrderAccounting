<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Delivery;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::with('deliveryOrder')->get();
        
        // Format for FullCalendar
        $events = [];
        foreach ($deliveries as $del) {
            $events[] = [
                'id' => $del->id,
                'title' => 'DO: ' . ($del->deliveryOrder->do_number ?? 'Unknown') . ' - ' . ucfirst($del->status),
                'start' => $del->deliveryOrder->delivery_date ?? date('Y-m-d'),
                'extendedProps' => [
                    'location' => $del->deliveryOrder->location ?? '-',
                    'driver' => $del->driver_name ?? '-',
                    'status' => $del->status
                ],
                'color' => $del->status == 'close' ? '#10B981' : ($del->status == 'canceled' ? '#EF4444' : '#0ea5e9')
            ];
        }

        return view('deliveries.index', compact('deliveries', 'events'));
    }

    public function close(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);
        $delivery->status = 'close';
        $delivery->save();

        return redirect()->back()->with('success', 'Delivery marked as Closed (Completed).');
    }

    public function cancel(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);
        $delivery->status = 'canceled';
        $delivery->save();

        return redirect()->back()->with('error', 'Delivery marked as Canceled.');
    }
}
