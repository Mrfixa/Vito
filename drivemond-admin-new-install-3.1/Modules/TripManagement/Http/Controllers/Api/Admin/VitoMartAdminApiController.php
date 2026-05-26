<?php

namespace Modules\TripManagement\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\MartProduct;

/**
 * JSON CRUD API for VitoMart products, used by the admin panel and
 * future external integrations. Web/blade equivalents live in
 * Modules\TripManagement\Http\Controllers\Web\VitoMartAdminController.
 */
class VitoMartAdminApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = substr((string)($request->search ?? ''), 0, 100);
        $limit = min((int)$request->input('limit', 20), 100);

        $products = MartProduct::query()
            ->when($search, function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('category', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        return response()->json(responseFormatter(DEFAULT_200, $products));
    }

    public function show(string $id): JsonResponse
    {
        $product = MartProduct::find($id);
        if (!$product) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, $product));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
            'zone_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errorProcessor($validator)), 422);
        }

        $data = $validator->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('mart/products', 'public');
        }

        $product = MartProduct::create($data);

        return response()->json(responseFormatter(DEFAULT_STORE_200, $product->fresh()), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = MartProduct::find($id);
        if (!$product) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0.01|max:999999.99',
            'stock' => 'sometimes|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
            'zone_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errorProcessor($validator)), 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('mart/products', 'public');
        }

        $product->update($data);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $product->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $product = MartProduct::find($id);
        if (!$product) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(responseFormatter(DEFAULT_DELETE_200));
    }
}
