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
     * Lightweight feed for the notification bell panel. Previously the
     * panel called the full /requisitions and /deliveries index routes,
     * which scan and cross-join the entire table just to build a 5-item
     * preview — that's what made the panel feel slow to open. This only
     * ever pulls the newest 5 rows of each with the handful of columns
     * the panel actually renders.
     */
    public function index(Request $request)
    {
        $requisitions = collect();
        foreach ($this->requisitionConnections() as $connection) {
            $hasStatus = $connection->getSchemaBuilder()->hasColumn('requisitions', 'status');
            $rows = $connection->table('requisitions')
                ->when($hasStatus, fn ($q) => $q->orderByRaw("status ILIKE 'pending' desc"))
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
            foreach ($rows as $row) {
                $requisitions->push($row);
            }
        }
        $requisitions = $requisitions->sortByDesc('created_at')->take(5)->values();

        $deliveries = DB::table('deliveries')
            ->select('id', 'shipment_number', 'items', 'qty', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'requisitions' => $requisitions->map(function ($r) {
                return [
                    'rq' => $r->req_number ?? $r->req_id ?? $r->id,
                    'item' => $r->item ?? $r->part_name ?? '',
                    'qty' => $r->quantity ?? $r->qty ?? '',
                    'requester' => $r->requested_by ?? '',
                    'dept' => $r->department ?? '',
                ];
            })->values(),
            'deliveries' => $deliveries->map(function ($d) {
                return [
                    'dr' => $d->shipment_number,
                    'items' => $d->items,
                    'qty' => $d->qty,
                    'status' => $d->status,
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

        $pendingRequisitions = 0;
        foreach ($this->requisitionConnections() as $connection) {
            if ($connection->getSchemaBuilder()->hasColumn('requisitions', 'status')) {
                $pendingRequisitions += $connection->table('requisitions')
                    ->whereRaw('status ILIKE ?', ['pending'])
                    ->count();
            } else {
                $pendingRequisitions += $connection->table('requisitions')->count();
            }
        }

        return response()->json([
            'purchaseOrders' => $pendingPOs,
            'requisitions' => $pendingRequisitions,
            'deliveries' => $pendingDeliveries,
        ]);
    }
}
