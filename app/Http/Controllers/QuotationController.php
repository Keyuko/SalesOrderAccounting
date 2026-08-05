<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::all();
        return view('quotations.index', compact('quotations'));
    }

    public function create() {}
    public function store(Request $request) {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}

    public function approve(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);
        $quotation->ppic_status = 'approved';
        $quotation->ppic_notes = $request->notes;
        $quotation->save();

        // Create Sales Order
        SalesOrder::create([
            'quotation_id' => $quotation->id,
            'so_number' => 'SO-' . strtoupper(uniqid()),
            'delivery_date' => $quotation->requested_delivery_date,
            'location' => 'DKJ Warehouse', // Dummy initial location
            'ppic_status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Quotation approved and Sales Order created.');
    }

    public function reject(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);
        $quotation->ppic_status = 'rejected';
        $quotation->ppic_notes = $request->notes;
        $quotation->save();

        Log::info("Quotation {$quotation->quotation_number} rejected. Notes: {$request->notes}");

        return redirect()->back()->with('error', 'Quotation rejected. Sales has been notified via Email.');
    }
}
