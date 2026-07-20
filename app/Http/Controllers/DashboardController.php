<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the procurement dashboard (stat cards, category chart,
     * PO status donut, and recent deliveries preview).
     */
    public function index(Request $request)
    {
        // TODO: replace with real Eloquent queries once models/migrations
        // for purchase_orders, suppliers, requisitions and deliveries exist.
        return view('pages.dashboard');
    }
}
