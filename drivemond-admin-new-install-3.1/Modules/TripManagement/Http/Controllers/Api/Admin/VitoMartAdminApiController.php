<?php
namespace Modules\TripManagement\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\TripManagement\Entities\MartProduct;

class VitoMartAdminApiController extends Controller
{
    public function index(Request $request)
    {
        $query = MartProduct::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        $products = $query->orderBy('created_at','desc')->paginate($request->input('limit', 20));
        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'required|string|max:100',
            'price'       => 'required|numeric|min:0.01|max:9999.99',
            'description' => 'nullable|string|max:1000',
            'stock'       => 'required|integer|min:0|max:99999',
            'is_active'   => 'boolean',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('mart/products', 'public');
        }

        $product = MartProduct::create($data);
        $this->auditLog(auth()->id(), 'create', MartProduct::class, $product->id, $data);
        return response()->json(['data' => $product], 201);
    }

    public function update(Request $request, string $id)
    {
        $product = MartProduct::findOrFail($id);
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'category'    => 'sometimes|required|string|max:100',
            'price'       => 'sometimes|required|numeric|min:0.01|max:9999.99',
            'description' => 'nullable|string|max:1000',
            'stock'       => 'sometimes|required|integer|min:0|max:99999',
            'is_active'   => 'boolean',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $data['image'] = $request->file('image')->store('mart/products', 'public');
        }

        $product->update($data);
        $this->auditLog(auth()->id(), 'update', MartProduct::class, $product->id, $data);
        return response()->json(['data' => $product]);
    }

    public function destroy(string $id)
    {
        $product = MartProduct::findOrFail($id);
        $product->delete();
        $this->auditLog(auth()->id(), 'delete', MartProduct::class, $product->id, []);
        return response()->json(['message' => 'Product deleted']);
    }

    private function auditLog(?string $userId, string $action, string $modelType, string $modelId, array $changes): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('vito_audit_log')->insert([
                'id'         => \Illuminate\Support\Str::uuid(),
                'user_id'    => $userId,
                'action'     => $action,
                'model_type' => $modelType,
                'model_id'   => $modelId,
                'changes'    => json_encode($changes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {}
    }
}
