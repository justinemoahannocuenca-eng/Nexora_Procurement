<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    /**
     * List active warehouses from the Inventory service's `warehouses`
     * table, for the "Deliver To" dropdown on the Log Delivery form.
     */
    public function index(Request $request)
    {
        $warehouses = collect();

        try {
            $warehouses = DB::connection('inventory')
                ->table('warehouses')
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderBy('name')
                ->select('id', 'name', 'address')
                ->get();
        } catch (\Exception $e) {
            $warehouses = collect();
        }

        return response()->json(['status' => 'ok', 'data' => $warehouses]);
    }
}
