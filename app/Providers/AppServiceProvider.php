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
            // A requisition is "Pending" only until a PO exists for it —
            // same rule the /nav-counts endpoint and the Requisitions page
            // use, so the initial server render matches the live JS poll
            // instead of flashing an inflated all-requisitions count.
            $allRefs = [];
            $totalRequisitions = 0;
            foreach (['orderfullfillment', 'manufacturing'] as $name) {
                try {
                    $connection = DB::connection($name);
                    if (! $connection->getSchemaBuilder()->hasTable('requisitions')) {
                        continue;
                    }
                    $refCol = $name === 'orderfullfillment' ? 'req_number' : 'req_id';
                    $refs = $connection->table('requisitions')->pluck($refCol)->filter()->all();
                    $totalRequisitions += count($refs);
                    $allRefs = array_merge($allRefs, $refs);
                } catch (\Exception $e) {
                    // ignore broken or unavailable external DB connections
                }
            }

            $matchedRefs = empty($allRefs) ? 0 : DB::table('purchase_orders')
                ->whereIn('requisition_reference', $allRefs)
                ->distinct()
                ->count('requisition_reference');
            $requisitionCount = max(0, $totalRequisitions - $matchedRefs);

            $pendingPoCount = DB::table('purchase_orders')->where('status', 'pending')->count();

            $view->with([
                'requisitionCount' => $requisitionCount,
                'pendingPoCount' => $pendingPoCount,
            ]);
        });
    }
}
