<?php

namespace App\Http\Controllers;

use App\Support\SequenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Detect a unique-constraint violation (e.g. a duplicate shipment_number),
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
     * Insert the delivery under a server-generated shipment_number
     * (SHP-YYYY-NNNN, next free sequence for the year) so the format
     * always stays clean instead of falling back to a collision suffix.
     */
    private function insertDelivery(array $insert): int
    {
        $attempts = 0;

        while ($attempts < 3) {
            $currentInsert = $insert;
            $currentInsert['shipment_number'] = SequenceNumber::generate('deliveries', 'shipment_number', 'SHP');

            try {
                return DB::table('deliveries')->insertGetId($currentInsert);
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyException($e)) {
                    $attempts++;
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Unable to save delivery after retrying.');
    }

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

        $statusCounts = DB::table('deliveries')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->mapWithKeys(function ($total, $status) {
                return [strtolower(str_replace([' ', '_'], '-', $status ?? 'intransit')) => $total];
            });

        return view('pages.deliveries', compact('deliveries', 'statusCounts'));
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
            'deliverTo' => 'nullable|string|max:255',
        ]);

        $purchaseOrder = DB::table('purchase_orders')->where('po_number', $validated['po'])->first();
        $supplier = DB::table('suppliers')->where('name', $validated['supplier'])->first();

        // Business rule: a PO can only be logged in Deliveries once Finance
        // has approved it (pending/rejected/cancelled POs are not allowed).
        if (! $purchaseOrder || strtolower(trim($purchaseOrder->status ?? '')) !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only approved purchase orders can be logged in deliveries.',
            ], 422);
        }

        $insert = [
            'shipment_number' => $validated['dr'],
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier?->id ?? null,
            'status' => in_array(strtolower($validated['status']), ['intransit','delivered','delayed','scheduled','pending','cancelled','completed']) ? strtolower($validated['status']) : 'intransit',
            'qty' => $validated['qty'] ?? null,
            'qty_expected' => $validated['qty'] ?? null,
            'items' => $validated['items'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'deliver_to_warehouse' => $validated['deliverTo'] ?? null,
            'delivery_date' => $validated['delDate'],
            'estimated_arrival' => $validated['delDate'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $deliveryId = $this->insertDelivery($insert);

        // Logging a delivery moves the PO into Processing.
        DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);

        $savedShipmentNumber = DB::table('deliveries')->where('id', $deliveryId)->value('shipment_number');
        $validated['dr'] = $savedShipmentNumber;

        return response()->json(['status' => 'ok', 'data' => $validated, 'id' => $deliveryId, 'shipment_number' => $savedShipmentNumber]);
    }

    public function update(Request $request, $delivery)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,scheduled,intransit,delivered,delayed,cancelled,completed',
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
