<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Deliveries tracking page (filters, sortable table, tracking modal).
     */
    public function index(Request $request)
    {
        $deliveries = DB::table('deliveries')
            ->leftJoin('suppliers', 'deliveries.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_orders', 'deliveries.purchase_order_id', '=', 'purchase_orders.id')
            ->select('deliveries.*', 'suppliers.name as supplier_name', 'purchase_orders.po_number as po_number')
            ->orderBy('deliveries.created_at', 'desc')
            ->limit(8)
            ->get();

        return view('pages.deliveries', compact('deliveries'));
    }

    /**
     * Handle the "+ Log Delivery" modal submit (submitAddDelivery in app-forms.js).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dr'      => 'required|string|max:50',
            'po'      => 'required|string|max:50',
            'supplier'=> 'required|string|max:150',
            'items'   => 'nullable|string|max:255',
            'qty'     => 'nullable|integer|min:1',
            'delDate' => 'required|date',
            'status'  => 'required|string|max:20',
            'remarks' => 'nullable|string',
        ]);

        $purchaseOrder = DB::table('purchase_orders')->where('po_number', $validated['po'])->first();
        $supplier = DB::table('suppliers')->where('name', $validated['supplier'])->first();

        $deliveryId = DB::table('deliveries')->insertGetId([
            'shipment_number' => $validated['dr'],
            'purchase_order_id' => $purchaseOrder?->id ?? null,
            'supplier_id' => $supplier?->id ?? null,
            'status' => in_array(strtolower($validated['status']), ['in-transit','delivered','delayed','scheduled','pending','cancelled','completed']) ? strtolower($validated['status']) : 'in-transit',
            'qty' => $validated['qty'] ?? null,
            'qty_expected' => $validated['qty'] ?? null,
            'items' => $validated['items'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'delivery_date' => $validated['delDate'],
            'estimated_arrival' => $validated['delDate'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'data' => $validated, 'id' => $deliveryId]);
    }

    public function update(Request $request, $delivery)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,scheduled,in-transit,delivered,delayed,cancelled,completed',
            'remarks' => 'nullable|string',
        ]);

        DB::table('deliveries')->where('id', $delivery)->update([
            'status' => $validated['status'] ?? DB::raw('status'),
            'remarks' => $validated['remarks'] ?? DB::raw('remarks'),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function destroy($delivery)
    {
        DB::table('deliveries')->where('id', $delivery)->delete();

        return response()->json(['status' => 'ok']);
    }
}
