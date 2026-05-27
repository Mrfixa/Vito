@extends('adminmodule::layouts.master')

@section('title', translate('Edit Product'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-30 mb-3 gap-3">
                        <h2 class="fs-22 text-capitalize">{{translate('edit_product')}}</h2>
                        <a href="{{route('admin.mart.products.index')}}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> {{translate('back')}}
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form action="{{route('admin.mart.products.update', $product->id)}}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('product_name')}} *</label>
                                        <input type="text" name="name" class="form-control" required value="{{$product->name}}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('category')}} *</label>
                                        <select name="category" class="form-control" required>
                                            <option value="">{{ translate('select_category') }}</option>
                                            @foreach(['Food','Drinks','Snacks','Essentials','Personal Care','Household','Electronics','Other'] as $cat)
                                                <option value="{{ $cat }}"
                                                    @if(isset($product) && $product->category === $cat) selected @endif
                                                >{{ $cat }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('price')}} *</label>
                                        <input type="number" name="price" step="0.01" min="0" class="form-control" required value="{{$product->price}}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('stock')}} *</label>
                                        <input type="number" name="stock" min="0" class="form-control" required value="{{$product->stock}}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">{{translate('description')}}</label>
                                        <textarea name="description" class="form-control" rows="3">{{$product->description}}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('product_image')}}</label>
                                        <input type="file" name="image" class="form-control" accept="image/*">
                                        @if($product->image)
                                            <small class="text-muted">{{translate('current')}}: {{$product->image}}</small>
                                        @endif
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{translate('update_product')}}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
