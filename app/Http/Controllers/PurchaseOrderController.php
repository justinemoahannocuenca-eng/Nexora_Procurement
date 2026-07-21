<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Purchase Orders list page (filters, sortable table, add PO modal).
     */
    public function index(Request $request)
    {
        $purchaseOrders = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name')
            ->orderBy('purchase_orders.created_at', 'desc')
            ->limit(8)
            ->get();

        $suppliers = DB::table('suppliers')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.purchase-orders', compact('purchaseOrders', 'suppliers'));
    }

    public function approved(Request $request)
    {
        $approvedPurchaseOrders = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name')
            ->where('purchase_orders.status', 'approved')
            ->orderBy('purchase_orders.order_date', 'desc')
            ->get();

        return response()->json($approvedPurchaseOrders);
    }

    /**
     * Handle the "+ New PO" modal submit (submitAddPO in app-forms.js).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'po' => 'required|string|max:50',
            'supplier' => 'required|string|max:150',
            'brand' => 'nullable|string|max:100',
            'item' => 'nullable|string|max:150',
            'qty' => 'nullable|integer|min:1',
            'unitPrice' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'priority' => 'nullable|string|max:20',
            'expected' => 'nullable|date',
            'createdBy' => 'nullable|string|max:150',
            'remarks' => 'nullable|string',
            'reqRef' => 'nullable|string|max:50',
        ]);

        $supplier = DB::table('suppliers')->where('name', $validated['supplier'])->first();
        $supplierId = $supplier?->id;

        if (! $supplierId) {
            $supplierId = DB::table('suppliers')->insertGetId([
                'name' => $validated['supplier'],
                'contact_person' => 'Auto-imported',
                'email' => 'auto@example.com',
                'phone' => 'N/A',
                'address' => 'Auto-imported',
                'brand' => $validated['brand'] ?? null,
                'status' => 'active',
                'product_items' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $poId = DB::table('purchase_orders')->insertGetId([
            'po_number' => $validated['po'],
            'supplier_id' => $supplierId,
            'qty' => (int) ($validated['qty'] ?? 1),
            'amount' => (float) ($validated['amount'] ?? 0),
            'status' => 'pending',
            'priority' => strtolower($validated['priority'] ?? 'normal'),
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => $validated['expected'] ?? null,
            'created_by' => $validated['createdBy'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'item' => $validated['item'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'unit_price' => (float) ($validated['unitPrice'] ?? 0),
            'requisition_reference' => $validated['reqRef'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_order_items')->insert([
            'purchase_order_id' => $poId,
            'supplier_product_id' => null,
            'name' => $validated['item'] ?? 'Item',
            'qty' => (int) ($validated['qty'] ?? 1),
            'unit_price' => (float) ($validated['unitPrice'] ?? 0),
            'amount' => (float) ($validated['amount'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok', 'data' => $validated, 'id' => $poId]);
    }

    public function update(Request $request, $purchaseOrder)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:20',
            'amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $status = $validated['status'] ?? null;
        if ($status !== null) {
            $status = strtolower(trim($status));
            $allowed = ['pending', 'approved', 'rejected', 'cancelled', 'processing', 'completed'];
            if (!in_array($status, $allowed, true)) {
                $status = null;
            }
        }

        DB::table('purchase_orders')->where('id', $purchaseOrder)->update([
            'status' => $status ?? DB::raw('status'),
            'amount' => $validated['amount'] ?? DB::raw('amount'),
            'remarks' => $validated['remarks'] ?? DB::raw('remarks'),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function destroy($purchaseOrder)
    {
        DB::table('purchase_orders')->where('id', $purchaseOrder)->delete();

        return response()->json(['status' => 'ok']);
    }
}
