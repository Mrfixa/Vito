<?php

namespace Modules\AuthManagement\Http\Controllers\Web;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\AuthManagement\Entities\QrToken;

class VitoQrAdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->search;
        $tokens = QrToken::with('creator')
            ->when($search, function ($q) use ($search) {
                $q->where('token', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('authmanagement::admin.qr-tokens.index', compact('tokens', 'search'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => 'required|in:driver,customer',
        ]);

        QrToken::create([
            'token' => Str::random(64),
            'role' => $request->role,
            'created_by' => auth()->id(),
            'expires_at' => $request->role === 'customer' ? now()->addHour() : now()->addDays(7),
        ]);

        Toastr::success(translate('qr_token_generated_successfully'));

        return redirect()->route('admin.qr-tokens.index');
    }

    public function revoke(string $id): RedirectResponse
    {
        $token = QrToken::findOrFail($id);
        $token->update(['is_revoked' => true]);

        Toastr::success(translate('qr_token_revoked'));

        return redirect()->route('admin.qr-tokens.index');
    }
}
