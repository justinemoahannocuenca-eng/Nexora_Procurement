<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    private function getRequisitionConnection()
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

    private function requisitionHasColumn($connection, string $column): bool
    {
        try {
            return $connection->getSchemaBuilder()->hasColumn('requisitions', $column);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getRequisitionSelectFields($connection): array
    {
        if ($connection->getName() === 'orderfullfillment') {
            return [
                'id',
                'req_number as requisition_number',
                'item',
                'qty as qty',
                'department',
                'requested_by',
                'priority',
                DB::raw("'Pending' as status"),
                'date_requested as request_date',
                'notes',
                'created_at',
                'updated_at',
            ];
        }

        return [
            'id',
            'req_id as requisition_number',
            'part_name as item',
            'quantity as qty',
            'department',
            'requested_by',
            'priority',
            'status',
            'date_requested as request_date',
            'notes',
            'destination',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Requisitions list page (filters, sortable table, add requisition modal).
     */
    public function index(Request $request)
    {
        $connection = $this->getRequisitionConnection();
        $requisitions = $connection
            ->table('requisitions')
            ->select($this->getRequisitionSelectFields($connection))
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.requisitions', compact('requisitions'));
    }

    /**
     * Handle the "+ New Requisition" modal submit (submitAddReq in app-forms.js).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rq'        => 'required|string|max:50',
            'item'      => 'required|string|max:150',
            'qty'       => 'required|integer|min:1',
            'uom'       => 'nullable|string|max:20',
            'dept'      => 'required|string|max:100',
            'requester' => 'required|string|max:150',
            'dateReq'   => 'required|date',
            'notes'     => 'nullable|string',
        ]);

        $connection = $this->getRequisitionConnection();
        $insert = [
            'department' => $validated['dept'],
            'requested_by' => $validated['requester'],
            'priority' => 'Medium',
            'date_requested' => $validated['dateReq'],
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($connection->getName() === 'orderfullfillment') {
            $insert = array_merge($insert, [
                'req_number' => $validated['rq'],
                'item' => $validated['item'],
                'qty' => (int) $validated['qty'],
            ]);
        } else {
            $insert = array_merge($insert, [
                'req_id' => $validated['rq'],
                'part_name' => $validated['item'],
                'quantity' => (int) $validated['qty'],
                'status' => 'Pending',
                'destination' => 'Inventory',
            ]);
        }

        $reqId = $connection->table('requisitions')->insertGetId($insert);

        return response()->json(['status' => 'ok', 'data' => $validated, 'id' => $reqId]);
    }

    public function update(Request $request, $requisition)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $connection = $this->getRequisitionConnection();
        $update = ['updated_at' => now()];

        if ($this->requisitionHasColumn($connection, 'status') && ! empty($validated['status'])) {
            $update['status'] = $validated['status'];
        }

        if ($this->requisitionHasColumn($connection, 'notes')) {
            $update['notes'] = $validated['notes'] ?? DB::raw('notes');
        }

        $connection->table('requisitions')->where('id', $requisition)->update($update);

        return response()->json(['status' => 'ok']);
    }

    public function destroy($requisition)
    {
        $this->getRequisitionConnection()->table('requisitions')->where('id', $requisition)->delete();

        return response()->json(['status' => 'ok']);
    }
}
