<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeliveryOrder;
use App\Models\Delivery;
use Illuminate\Support\Facades\Log;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        $deliveryOrders = DeliveryOrder::all();
        return view('delivery_orders.index', compact('deliveryOrders'));
    }

    public function update(Request $request, string $id)
    {
        $do = DeliveryOrder::findOrFail($id);
        $do->delivery_date = $request->delivery_date;
        $do->location = $request->location;
        $do->notes = $request->notes;
        // If changed, reset PPIC status so it needs approval again
        $do->ppic_status = 'pending';
        $do->save();

        return redirect()->back()->with('success', 'Delivery Order updated and waiting for PPIC re-approval.');
    }

    public function approve(Request $request, $id)
    {
        $do = DeliveryOrder::findOrFail($id);
        $do->ppic_status = 'approved';
        $do->ppic_notes = $request->notes;
        $do->save();

        // Create Delivery Record for actual tracking
        Delivery::create([
            'delivery_order_id' => $do->id,
            'driver_name' => null, // To be assigned later
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Delivery Order approved. It is now ready for tracking.');
    }

    public function reject(Request $request, $id)
    {
        $do = DeliveryOrder::findOrFail($id);
        $do->ppic_status = 'rejected';
        $do->ppic_notes = $request->notes;
        $do->save();

        Log::info("Delivery Order {$do->do_number} rejected. Notes: {$request->notes}");

        return redirect()->back()->with('error', 'Delivery Order rejected. Sales has been notified via Email.');
    }
}
