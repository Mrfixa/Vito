@extends('adminmodule::layouts.master')

@section('title', translate('VitoMart Products'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-30 mb-3 gap-3">
                        <h2 class="fs-22 text-capitalize">{{translate('vitomart_products')}}</h2>
                        <a href="{{route('admin.mart.products.create')}}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> {{translate('add_product')}}
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                <form action="{{url()->current()}}" class="search-form search-form_style-two">
                                    <div class="input-group search-form__input_group">
                                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                                        <input type="search" name="search" value="{{$search ?? ''}}" class="theme-input-style search-form__input" placeholder="{{translate('search_by_name_or_category')}}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">{{translate('search')}}</button>
                                </form>
                            </div>

                            <div class="table-responsive mt-3">
                                <table class="table table-borderless align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{translate('image')}}</th>
                                            <th>{{translate('sl')}}</th>
                                            <th>{{translate('name')}}</th>
                                            <th>{{translate('category')}}</th>
                                            <th>{{translate('price')}}</th>
                                            <th>{{translate('stock')}}</th>
                                            <th>{{translate('status')}}</th>
                                            <th class="text-center">{{translate('actions')}}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($products as $key => $product)
                                            <tr>
                                                <td>
                                                    @if($product->image)
                                                        <img src="{{ asset('storage/'.$product->image) }}"
                                                             style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6">
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{$products->firstItem() + $key}}</td>
                                                <td>{{$product->name}}</td>
                                                <td>{{$product->category}}</td>
                                                <td>{{number_format($product->price, 2)}}</td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-secondary vito-stock-btn"
                                                                data-id="{{ $product->id }}"
                                                                data-action="decrement"
                                                                style="width:28px;padding:0;line-height:28px">−</button>
                                                        <span id="stock-{{ $product->id }}" style="min-width:32px;text-align:center;display:inline-block">
                                                            {{ $product->stock }}
                                                        </span>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-secondary vito-stock-btn"
                                                                data-id="{{ $product->id }}"
                                                                data-action="increment"
                                                                style="width:28px;padding:0;line-height:28px">+</button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{$product->is_active ? 'success' : 'danger'}}">
                                                        {{$product->is_active ? translate('active') : translate('inactive')}}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <a href="{{route('admin.mart.products.edit', $product->id)}}" class="btn btn-outline-info btn-sm">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form action="{{route('admin.mart.products.toggle-status', $product->id)}}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                                                <i class="bi bi-toggle-{{$product->is_active ? 'on' : 'off'}}"></i>
                                                            </button>
                                                        </form>
                                                        <form action="{{route('admin.mart.products.destroy', $product->id)}}" method="POST" onsubmit="return confirm('{{translate('are_you_sure')}}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">{{translate('no_products_found')}}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{$products->links()}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
document.querySelectorAll('.vito-stock-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        var id = this.dataset.id;
        var action = this.dataset.action;
        var span = document.getElementById('stock-' + id);
        fetch('{{ url('admin/mart/products') }}/' + id + '/stock-adjust', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({action: action})
        })
        .then(function(r){ return r.json(); })
        .then(function(data){ if(data.stock !== undefined) span.textContent = data.stock; })
        .catch(function(){});
    });
});
</script>
@endpush
