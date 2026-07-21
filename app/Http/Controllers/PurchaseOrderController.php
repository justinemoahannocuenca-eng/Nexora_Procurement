<?php

namespace App\Http\Controllers;

use App\Support\SequenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * Detect a unique-constraint violation (e.g. a duplicate po_number),
     * regardless of which database driver raised it.
     */
    private function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'duplicate key')
            || str_contains($message, 'Unique violation')
            || str_contains($message, 'SQLSTATE[23505]')
            || str_contains($message, 'UNIQUE constraint failed');
    }

    /**
     * Insert the purchase order under a server-generated po_number
     * (PO-YYYY-NNNN, next free sequence for the year). The browser's
     * pre-filled number is only a placeholder — it's always regenerated
     * here so the format never drifts and never collides.
     */
    private function insertPurchaseOrder(array $insert): int
    {
        $attempts = 0;

        while ($attempts < 3) {
            $currentInsert = $insert;
            $currentInsert['po_number'] = SequenceNumber::generate('purchase_orders', 'po_number', 'PO');

            try {
                return DB::table('purchase_orders')->insertGetId($currentInsert);
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyException($e)) {
                    $attempts++;
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Unable to save purchase order after retrying.');
    }

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

        $statusCounts = DB::table('purchase_orders')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->mapWithKeys(function ($total, $status) {
                return [strtolower(str_replace([' ', '_'], '-', $status ?? 'pending')) => $total];
            });

        return view('pages.purchase-orders', compact('purchaseOrders', 'suppliers', 'statusCounts'));
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

        $insert = [
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
        ];

        $poId = $this->insertPurchaseOrder($insert);
        $savedPoNumber = DB::table('purchase_orders')->where('id', $poId)->value('po_number');

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

        $validated['po'] = $savedPoNumber;

        return response()->json(['status' => 'ok', 'data' => $validated, 'id' => $poId, 'po_number' => $savedPoNumber]);
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
