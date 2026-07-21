<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the procurement dashboard (stat cards, category chart,
     * PO status donut, and recent deliveries preview).
     */
    public function index(Request $request)
    {
        $poCount = DB::table('purchase_orders')->count();
        $poStatusBreakdown = DB::table('purchase_orders')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $supplierCount = DB::table('suppliers')
            ->where('status', 'active')
            ->count();

        $requisitionCount = 0;
        try {
            $requisitionConnection = DB::connection('orderfullfillment');
            if ($requisitionConnection->getSchemaBuilder()->hasTable('requisitions')) {
                $requisitionCount = $requisitionConnection->table('requisitions')->count();
            }
        } catch (\Exception $e) {
            $requisitionCount = 0;
        }

        $deliveryCount = DB::table('deliveries')->count();
        $pendingDeliveries = DB::table('deliveries')
            ->whereIn('status', ['pending', 'scheduled', 'intransit'])
            ->count();

        $recentPOs = DB::table('purchase_orders')
            ->select('id', 'po_number', 'supplier_id', 'qty', 'amount', 'status', 'priority', 'order_date', 'expected_delivery_date', 'item', 'brand')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $supplierIds = $recentPOs->pluck('supplier_id')->filter()->unique()->toArray();
        $suppliersMap = [];
        if (!empty($supplierIds)) {
            $suppliersMap = DB::table('suppliers')
                ->whereIn('id', $supplierIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        $recentDeliveries = DB::table('deliveries')
            ->select('id', 'shipment_number', 'purchase_order_id', 'supplier_id', 'status', 'delivery_date', 'estimated_arrival', 'actual_arrival', 'carrier')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $deliverySupplierIds = $recentDeliveries->pluck('supplier_id')->filter()->unique()->toArray();
        $deliverySuppliersMap = [];
        if (!empty($deliverySupplierIds)) {
            $deliverySuppliersMap = DB::table('suppliers')
                ->whereIn('id', $deliverySupplierIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        $spendByBrand = DB::table('purchase_orders')
            ->select('brand', DB::raw('SUM(amount) as total'))
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->groupBy('brand')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $totalSpend = DB::table('purchase_orders')
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        return view('pages.dashboard', [
            'poCount' => $poCount,
            'poStatusBreakdown' => $poStatusBreakdown,
            'supplierCount' => $supplierCount,
            'requisitionCount' => $requisitionCount,
            'deliveryCount' => $deliveryCount,
            'pendingDeliveries' => $pendingDeliveries,
            'recentPOs' => $recentPOs,
            'suppliersMap' => $suppliersMap,
            'recentDeliveries' => $recentDeliveries,
            'deliverySuppliersMap' => $deliverySuppliersMap,
            'spendByBrand' => $spendByBrand,
            'totalSpend' => $totalSpend,
        ]);
    }
}
