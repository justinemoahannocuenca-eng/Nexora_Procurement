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

            $requisitionCount = 0;
            try {
                $requisitionConnection = $this->resolveRequisitionConnection();
                if ($requisitionConnection && $requisitionConnection->getSchemaBuilder()->hasTable('requisitions')) {
                    if ($requisitionConnection->getSchemaBuilder()->hasColumn('requisitions', 'status')) {
                        $requisitionCount = $requisitionConnection->table('requisitions')
                            ->where(function ($query) {
                                $query->where('status', 'Pending')
                                    ->orWhere('status', 'pending');
                            })
                            ->count();
                    } else {
                        $requisitionCount = $requisitionConnection->table('requisitions')->count();
                    }
                }
            } catch (\Exception $e) {
                $requisitionCount = 0;
            }

            $pendingPoCount = DB::table('purchase_orders')->where('status', 'pending')->count();

            $view->with([
                'lowStockAlerts' => $alerts,
                'lowStockAlertCount' => $alerts->count(),
                'requisitionCount' => $requisitionCount,
                'pendingPoCount' => $pendingPoCount,
            ]);
        });
    }

    private function resolveRequisitionConnection()
    {
        foreach (['orderfullfillment', 'manufacturing'] as $connection) {
            try {
                if (DB::connection($connection)->getSchemaBuilder()->hasTable('requisitions')) {
                    return DB::connection($connection);
                }
            } catch (\Exception $e) {
                // ignore broken or unavailable external DB connections
            }
        }

        return DB::connection('manufacturing');
    }
}
