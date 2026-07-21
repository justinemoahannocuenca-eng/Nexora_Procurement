<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Supplier directory page (filters, sortable table, add supplier modal).
     */
    public function index(Request $request)
    {
        $suppliers = DB::table('suppliers')->orderBy('created_at', 'desc')->get();

        // If client expects JSON (AJAX), return suppliers as JSON with decoded product items
        if ($request->wantsJson() || $request->ajax()) {
            $data = $suppliers->map(function ($s) {
                $products = [];
                if (!empty($s->product_items)) {
                    $decoded = json_decode($s->product_items, true);
                    if (is_array($decoded)) $products = $decoded;
                }
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'brand' => $s->brand,
                    'products' => $products,
                ];
            });
            return response()->json(['status' => 'ok', 'data' => $data]);
        }

        return view('pages.suppliers', compact('suppliers'));
    }

    /**
     * Handle the "+ Add Supplier" modal submit (submitAddSupplier in app-forms.js).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sid'         => 'nullable|string|max:50',
            'name'        => 'required|string|max:150',
            'contact'     => 'required|string|max:150',
            'email'       => 'required|email|max:150',
            'phone'       => 'required|string|max:30',
            'address'     => 'required|string|max:255',
            'brand'       => 'nullable|string|max:100',
            'status'      => 'nullable|string|max:20',
            'productsJson'=> 'nullable|string',
        ]);

        $products = [];
        if ($request->filled('productsJson')) {
            $decoded = json_decode($request->input('productsJson'), true);
            if (is_array($decoded)) {
                $products = $decoded;
            }
        }

        $supplierId = DB::table('suppliers')->insertGetId([
            'name' => $validated['name'],
            'contact_person' => $validated['contact'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'brand' => $validated['brand'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'product_items' => json_encode($products),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($products as $product) {
            if (empty($product['name'])) {
                continue;
            }

            DB::table('supplier_products')->insert([
                'supplier_id' => $supplierId,
                'name' => $product['name'],
                'sku' => $product['sku'] ?? null,
                'unit_price' => $product['price'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok', 'data' => ['id' => $supplierId] + $validated]);
    }

    public function update(Request $request, $supplier)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'contact' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:100',
            'productsJson' => 'nullable|string',
        ]);

        $update = [
            'name' => $validated['name'] ?? DB::raw('name'),
            'contact_person' => $validated['contact'] ?? DB::raw('contact_person'),
            'email' => $validated['email'] ?? DB::raw('email'),
            'phone' => $validated['phone'] ?? DB::raw('phone'),
            'address' => $validated['address'] ?? DB::raw('address'),
            'brand' => $validated['brand'] ?? DB::raw('brand'),
            'updated_at' => now(),
        ];

        // Persist edited products (name/price) — previously this method
        // ignored productsJson entirely, so product/price edits from the
        // supplier edit modal silently did nothing.
        if ($request->has('productsJson')) {
            $decoded = json_decode($request->input('productsJson'), true);
            $products = is_array($decoded) ? $decoded : [];
            $update['product_items'] = json_encode($products);

            DB::table('supplier_products')->where('supplier_id', $supplier)->delete();
            foreach ($products as $product) {
                if (empty($product['name'])) {
                    continue;
                }
                DB::table('supplier_products')->insert([
                    'supplier_id' => $supplier,
                    'name' => $product['name'],
                    'sku' => $product['sku'] ?? null,
                    'unit_price' => $product['price'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('suppliers')->where('id', $supplier)->update($update);

        return response()->json(['status' => 'ok']);
    }

    public function destroy($supplier)
    {
        DB::table('suppliers')->where('id', $supplier)->delete();

        return response()->json(['status' => 'ok']);
    }
}
