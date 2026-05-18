@extends('adminmodule::layouts.master')

@section('title', translate('QR Tokens'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-30 mb-3 gap-3">
                        <h2 class="fs-22 text-capitalize">{{translate('qr_invitation_tokens')}}</h2>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">{{translate('generate_new_token')}}</h5>
                            <form action="{{route('admin.qr-tokens.generate')}}" method="POST" class="d-flex gap-3 align-items-end">
                                @csrf
                                <div>
                                    <label class="form-label">{{translate('role')}}</label>
                                    <select name="role" class="form-select" required>
                                        <option value="customer">{{translate('customer')}} (1h {{translate('expiry')}})</option>
                                        <option value="driver">{{translate('driver')}} (7d {{translate('expiry')}})</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-qr-code"></i> {{translate('generate')}}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                <form action="{{url()->current()}}" class="search-form search-form_style-two">
                                    <div class="input-group search-form__input_group">
                                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                                        <input type="search" name="search" value="{{$search ?? ''}}" class="theme-input-style search-form__input" placeholder="{{translate('search_tokens')}}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">{{translate('search')}}</button>
                                </form>
                            </div>

                            <div class="table-responsive mt-3">
                                <table class="table table-borderless align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{translate('sl')}}</th>
                                            <th>{{translate('token')}}</th>
                                            <th>{{translate('role')}}</th>
                                            <th>{{translate('created_by')}}</th>
                                            <th>{{translate('expires_at')}}</th>
                                            <th>{{translate('status')}}</th>
                                            <th class="text-center">{{translate('actions')}}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($tokens as $key => $token)
                                            <tr>
                                                <td>{{$tokens->firstItem() + $key}}</td>
                                                <td><code class="small">{{Str::limit($token->token, 20)}}</code></td>
                                                <td><span class="badge bg-info">{{$token->role}}</span></td>
                                                <td>{{$token->creator?->first_name ?? translate('n_a')}}</td>
                                                <td>{{$token->expires_at?->format('M d, Y H:i')}}</td>
                                                <td>
                                                    @if($token->is_revoked)
                                                        <span class="badge bg-danger">{{translate('revoked')}}</span>
                                                    @elseif($token->redeemed_at)
                                                        <span class="badge bg-secondary">{{translate('redeemed')}}</span>
                                                    @elseif($token->expires_at && $token->expires_at->isPast())
                                                        <span class="badge bg-warning">{{translate('expired')}}</span>
                                                    @else
                                                        <span class="badge bg-success">{{translate('active')}}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if(!$token->is_revoked && !$token->redeemed_at && $token->expires_at?->isFuture())
                                                        <form action="{{route('admin.qr-tokens.revoke', $token->id)}}" method="POST" onsubmit="return confirm('{{translate('are_you_sure')}}')">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{translate('revoke')}}</button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">{{translate('no_tokens_found')}}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{$tokens->links()}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
