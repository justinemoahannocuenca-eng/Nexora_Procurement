<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.sidebar', function ($view): void {
            $alerts = DB::connection('inventory')
                ->table('stock_levels as sl')
                ->join('items as i', 'sl.item_id', '=', 'i.id')
                ->where('sl.stock', '<', 5)
                ->orderBy('sl.stock', 'asc')
                ->select('sl.stock', 'sl.reorder_threshold', 'i.name as item_name', 'i.sku')
                ->limit(5)
                ->get();

            $view->with([
                'lowStockAlerts' => $alerts,
                'lowStockAlertCount' => $alerts->count(),
            ]);
        });
    }
}
