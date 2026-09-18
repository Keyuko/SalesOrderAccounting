<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Delivery;
use Illuminate\Support\Facades\Log;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $deliveries = Delivery::with('deliveryOrder.salesOrder.quotation')->get();
        $viewType = $request->query('view', 'calendar');
        
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
                    'status' => $del->status,
                    'plat_kendaraan' => $del->plat_kendaraan
                ],
                'color' => $del->status == 'close' ? '#10B981' : ($del->status == 'canceled' ? '#EF4444' : '#EAB308') // Yellow for pending
            ];
        }

        return view('deliveries.index', compact('deliveries', 'events', 'viewType'));
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

    public function updateImei(Request $request, $id)
    {
        $request->validate([
            'plat_kendaraan' => 'required|string|max:255'
        ]);

        $delivery = Delivery::findOrFail($id);
        $delivery->plat_kendaraan = $request->plat_kendaraan;
        $delivery->save();

        return redirect()->back()->with('success', 'Plat Kendaraan updated successfully.');
    }

    public function getTrackingLink($id, \App\Services\GpsIdService $gpsService)
    {
        $delivery = Delivery::findOrFail($id);
        
        if (!$delivery->plat_kendaraan) {
            return response()->json(['error' => 'Plat Kendaraan is not set for this delivery.'], 400);
        }

        try {
            $imei = $gpsService->getImeiByPlate($delivery->plat_kendaraan);
            $link = $gpsService->getTrackingLink($imei);
            return response()->json(['link' => $link]);
        } catch (\Exception $e) {
            Log::error('GPS Tracking Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
