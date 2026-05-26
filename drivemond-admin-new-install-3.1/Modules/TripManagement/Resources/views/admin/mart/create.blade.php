@extends('adminmodule::layouts.master')

@section('title', translate('Add Product'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-30 mb-3 gap-3">
                        <h2 class="fs-22 text-capitalize">{{translate('add_new_product')}}</h2>
                        <a href="{{route('admin.mart.products.index')}}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> {{translate('back')}}
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form action="{{route('admin.mart.products.store')}}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('product_name')}} *</label>
                                        <input type="text" name="name" class="form-control" required value="{{old('name')}}">
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
                                        <input type="number" name="price" step="0.01" min="0" class="form-control" required value="{{old('price')}}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('stock')}} *</label>
                                        <input type="number" name="stock" min="0" class="form-control" required value="{{old('stock', 0)}}">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">{{translate('description')}}</label>
                                        <textarea name="description" class="form-control" rows="3">{{old('description')}}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">{{translate('product_image')}}</label>
                                        <input type="file" name="image" class="form-control" accept="image/*">
                                        <div id="imagePreviewWrap" style="display:none;margin-top:8px">
                                            <img id="imagePreview" src="#" alt="Preview"
                                                 style="max-width:120px;max-height:120px;border-radius:6px;border:1px solid #dee2e6">
                                        </div>
                                        <script>
                                        (function(){
                                            var input = document.querySelector('input[name="image"]');
                                            if(input) {
                                                input.addEventListener('change', function(){
                                                    if(this.files && this.files[0]){
                                                        var reader = new FileReader();
                                                        reader.onload = function(e){
                                                            document.getElementById('imagePreview').src = e.target.result;
                                                            document.getElementById('imagePreviewWrap').style.display = 'block';
                                                        };
                                                        reader.readAsDataURL(this.files[0]);
                                                    }
                                                });
                                            }
                                        })();
                                        </script>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">{{translate('save_product')}}</button>
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
