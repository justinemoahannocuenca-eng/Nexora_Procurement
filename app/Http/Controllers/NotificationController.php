<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * External requisition connections that actually have a `requisitions`
     * table right now. Cheap to call repeatedly — each candidate connection
     * is only probed with hasTable(), no row scans.
     */
    private function requisitionConnections(): array
    {
        $connections = [];

        foreach (['orderfullfillment', 'manufacturing'] as $name) {
            try {
                $connection = DB::connection($name);
                if ($connection->getSchemaBuilder()->hasTable('requisitions')) {
                    $connections[] = $connection;
                }
            } catch (\Exception $e) {
                // ignore unreachable external DB connections
            }
        }

        return $connections;
    }

    /**
     * The column that holds a requisition's human-readable reference number
     * on each external connection — used to match against
     * purchase_orders.requisition_reference so "pending" can be derived the
     * same way RequisitionController@index derives it (no matching PO yet).
     */
    private function requisitionRefColumn($connection): string
    {
        return $connection->getName() === 'orderfullfillment' ? 'req_number' : 'req_id';
    }

    /**
     * Lightweight feed for the notification bell panel — the newest
     * purchase orders that have been approved or rejected.
     */
    public function index(Request $request)
    {
        $purchaseOrders = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->whereIn('purchase_orders.status', ['approved', 'rejected'])
            ->orderBy('purchase_orders.updated_at', 'desc')
            ->limit(8)
            ->select('purchase_orders.po_number', 'purchase_orders.status', 'purchase_orders.updated_at', 'suppliers.name as supplier_name')
            ->get();

        return response()->json([
            'purchaseOrders' => $purchaseOrders->map(function ($po) {
                return [
                    'po' => $po->po_number,
                    'status' => $po->status,
                    'supplier' => $po->supplier_name ?? '',
                    'updated_at' => $po->updated_at,
                ];
            })->values(),
        ]);
    }

    /**
     * Small counts payload for the sidebar nav badges, polled from the
     * client so counts update without a full page refresh.
     */
    public function counts(Request $request)
    {
        $pendingPOs = DB::table('purchase_orders')->where('status', 'pending')->count();
        $pendingDeliveries = DB::table('deliveries')->whereIn('status', ['pending', 'scheduled', 'intransit'])->count();

        // A requisition is "Pending" only until a PO exists for it — same
        // rule as the Requisitions page. The old check counted every row on
        // connections without a real `status` column (orderfullfillment),
        // wildly inflating the badge with already-processed requisitions.
        $allRefs = [];
        $totalRequisitions = 0;
        foreach ($this->requisitionConnections() as $connection) {
            $refCol = $this->requisitionRefColumn($connection);
            $refs = $connection->table('requisitions')->pluck($refCol)->filter()->all();
            $totalRequisitions += count($refs);
            $allRefs = array_merge($allRefs, $refs);
        }
        $matchedRefs = empty($allRefs) ? 0 : DB::table('purchase_orders')
            ->whereIn('requisition_reference', $allRefs)
            ->distinct()
            ->count('requisition_reference');
        $pendingRequisitions = max(0, $totalRequisitions - $matchedRefs);

        return response()->json([
            'purchaseOrders' => $pendingPOs,
            'requisitions' => $pendingRequisitions,
            'deliveries' => $pendingDeliveries,
        ]);
    }
}
