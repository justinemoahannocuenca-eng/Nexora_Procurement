<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Compact "1k / 1M / 1B" currency format used across the dashboard
     * (Active POS spend, Spend by Brand) so large peso amounts stay
     * short instead of wrapping the stat cards.
     */
    public static function formatCompactCurrency(float $amount): string
    {
        $abs = abs($amount);

        if ($abs >= 1_000_000_000) {
            return '₱' . rtrim(rtrim(number_format($amount / 1_000_000_000, 1), '0'), '.') . 'B';
        }
        if ($abs >= 1_000_000) {
            return '₱' . rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($abs >= 1_000) {
            return '₱' . rtrim(rtrim(number_format($amount / 1_000, 1), '0'), '.') . 'k';
        }

        return '₱' . number_format($amount, 2);
    }

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
            ->limit(25)
            ->get();

        $totalSpend = DB::table('purchase_orders')
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        // Top suppliers by total PO spend — grouped/summed per supplier so the
        // same supplier never appears twice (previously missing entirely, which
        // is why the "Top Suppliers" panel always showed "No top suppliers to
        // display" no matter how much data was in the database).
        $topSuppliers = DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.id', 'suppliers.name', DB::raw('SUM(purchase_orders.amount) as total_spend'))
            ->where('purchase_orders.status', '!=', 'cancelled')
            ->where('purchase_orders.status', '!=', 'rejected')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_spend')
            ->limit(5)
            ->get()
            ->map(function ($supplier) {
                $supplier->formatted_total_spend = self::formatCompactCurrency((float) $supplier->total_spend);
                return $supplier;
            });

        $totalSpendFormatted = self::formatCompactCurrency($totalSpend);

        $spendByBrand = $spendByBrand->map(function ($item) {
            $item->formatted_total = self::formatCompactCurrency((float) $item->total);
            return $item;
        });

        // Top 5 shown directly in the panel; the rest is handed to the
        // "View all" modal so the panel never has to squeeze in every brand.
        $spendByBrandTop = $spendByBrand->take(5)->values();

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
            'spendByBrand' => $spendByBrandTop,
            'spendByBrandAll' => $spendByBrand,
            'totalSpend' => $totalSpend,
            'totalSpendFormatted' => $totalSpendFormatted,
            'topSuppliers' => $topSuppliers,
        ]);
    }
}
