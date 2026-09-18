<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrder;
use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\Log;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with('quotation');
        
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }
        
        $salesOrders = $query->get();
        return view('sales_orders.index', compact('salesOrders'));
    }

    public function update(Request $request, string $id)
    {
        $so = SalesOrder::findOrFail($id);
        $so->delivery_date = $request->delivery_date;
        $so->location = $request->location;
        $so->notes = $request->notes;
        // If changed, reset PPIC status so it needs approval again
        $so->ppic_status = 'pending';
        $so->save();

        return redirect()->back()->with('success', 'Sales Order updated and waiting for PPIC re-approval.');
    }

    public function approve(Request $request, $id)
    {
        $so = SalesOrder::findOrFail($id);
        $so->ppic_status = 'approved';
        $so->ppic_notes = $request->notes;
        $so->save();

        // Create Delivery Order (Sent to SAP, pull back as DO)
        DeliveryOrder::create([
            'sales_order_id' => $so->id,
            'do_number' => 'DO-' . strtoupper(uniqid()),
            'delivery_date' => $so->delivery_date,
            'location' => $so->location,
            'notes' => $so->notes,
            'ppic_status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Sales Order approved and sent to SAP. Delivery Order created.');
    }

    public function reject(Request $request, $id)
    {
        $so = SalesOrder::findOrFail($id);
        $so->ppic_status = 'rejected';
        $so->ppic_notes = $request->notes;
        $so->save();

        Log::info("Sales Order {$so->so_number} rejected. Notes: {$request->notes}");

        return redirect()->back()->with('error', 'Sales Order rejected. Sales has been notified via Email.');
    }
}
