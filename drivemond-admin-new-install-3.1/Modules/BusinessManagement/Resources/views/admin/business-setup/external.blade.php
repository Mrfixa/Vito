@extends('adminmodule::layouts.master')

@section('title', translate('Ecommerce Setup and Integration'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="page-header-title m-0 mb-2">
                        <span>
                            {{translate('Ecommerce Setup and Integration')}}
                        </span>
                    </h3>
                    <p class="m-0">
                        {{translate('connect_6ammart_system_with_vito')}}
                    </p>
                </div>
                <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button"
                     data-bs-toggle="modal" data-bs-target="#how-it-works">
                    <strong class="mr-2">{{translate('how_the_setting_works')}}</strong>
                    <div>
                        <i class="tio-info-outined"></i>
                    </div>
                </div>
            </div>
            <!-- Page Header -->

            @can('business_edit')
                <div class="card mb-3">
                    <div class="card-body">
                        <form action="{{route('admin.business.external.store')}}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <div class="border rounded d-flex flex-wrap gap-2 align-items-center p-3 p-sm-4">
                                    <div class="w-160px flex-grow-1">
                                        <h5>{{translate('Activation Mode')}}</h5>
                                        <p class="fs-12 m-0">
                                            {{translate('Enable the switch to activate the purchased Software- 6amMart in the Vito system. You must input the correct information to make sure the functionality works properly.')}}
                                        </p>
                                    </div>
                                    <label class="switcher ml-auto mb-0">
                                        <input
                                            data-url="{{ route('admin.business.setup.info.maintenance') }}"
                                            type="checkbox" id="maintenance-mode-input" name="activation_mode"
                                            class="switcher_input" {{ ($settings->firstWhere('key', 'activation_mode')->value ?? 0) == 1 ? 'checked' : '' }} {{env('APP_MODE')=='demo' ? 'disabled' : ''}}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-12">
                                    <div class="p-3 p-sm-4 bg-body rounded">
                                        <label for="martBaseUrl"
                                               class="mb-2">{{ translate('Ecommerce System Base URL') }}
                                            <i
                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{translate("Need to get the purchased software - 6amMart‘s Base URL to insert it into this input field.")}}">
                                            </i>
                                        </label>
                                        <input type="url" name="mart_base_url"
                                               value="{{ $settings->firstWhere('key', 'mart_base_url')?->value ?? old('mart_base_url') }}"
                                               id="martBaseUrl" class="form-control"
                                               placeholder="{{ translate('Ex: https://mart.com') }}" required>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="p-3 p-sm-4 bg-body rounded">
                                        <label for="martToken"
                                               class="mb-2">{{ translate('Ecommerce System Token') }}
                                            <i
                                                class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                data-bs-toggle="tooltip"
                                                data-bs-title="{{translate("From the purchased software - 6amMart  Admin panel’s Ride Sharing Setup & Integration page, Copy the System token and insert it into this input field.")}}">
                                            </i>
                                        </label>
                                        <input type="text" name="mart_token" maxlength="64" minlength="64"
                                               value="{{ $settings->firstWhere('key', 'mart_token')?->value ?? old('mart_token') }}"
                                               id="martToken" class="form-control"
                                               placeholder="{{ translate('enter_mart_self_token') }}">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="p-3 p-sm-4 bg-body rounded">
                                        <div class="d-flex justify-content-between">
                                            <label
                                                class="mb-2">{{ (businessConfig('business_name')?->value ?? "Vito"). ' ' .translate('System Token') }}
                                                <i
                                                    class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-title="{{translate("Click on the Generate Token button, It will automatically generate the Vito System Token and insert it into the input field.")}}">
                                                </i>
                                            </label>
                                        </div>
                                        <div class="input-group input-token-group">
                                            <div class="position-relative">
                                                <input id="systemSelfToken" maxlength="64" minlength="64" type="text"
                                                       value="{{$settings->firstWhere('key', 'system_self_token')?->value ?? old('system_self_token')}}"
                                                       name="system_self_token" class="form-control"
                                                       placeholder="{{ translate('messages.generate_system_self_token') }}"
                                                       required>
                                                <a href="javascript:void(0)" class="generate-code text-primary"
                                                   id="copyButton"><i class="tio-copy"></i> </a>
                                            </div>
                                            <a href="javascript:void(0)" class="btn btn-primary input-group-text"
                                               id="generateSystemSelfToken">{{translate("generate_token")}} <i
                                                    class="tio-code"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3 pt-4">
                                <button type="reset"
                                        class="btn btn-secondary  text-capitalize">{{translate("Reset")}}</button>
                                <button type="submit"
                                        class="btn btn-primary text-capitalize cmn_focus">{{ translate('save_information') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="modal fade how-it-works-modal" id="how-it-works">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header px-3 pt-3 border-0 justify-content-end">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pb-5 pt-0 px-lg-5">
                            <h4 class="mb-3">{{translate('How does it works')}} ?</h4>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="">
                                        <img src="{{dynamicAsset('public/assets/admin-module/img/how-it-works/Step-1.svg')}}"
                                             alt=""
                                             class="mb-20">
                                        <div class="how-it-count">
                                            <span>1</span>
                                        </div>
                                        <h5 class="mb-2">{{translate('Ecommerce System Base URL Insertion')}}</h5>
                                        <p>
                                            {{translate("At first, Need to insert the Base URL of the deploy Software- 6amMart.")}}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="">
                                        <img src="{{dynamicAsset('public/assets/admin-module/img/how-it-works/step-2.svg')}}"
                                             alt=""
                                             class="mb-20">
                                        <div class="how-it-count">
                                            <span>2</span>
                                        </div>
                                        <h5 class="mb-2">{{translate('Ecommerce System Token Input')}}</h5>
                                        <p>
                                            {{translate("Visit the 6amMart")}} <a
                                                href="{{externalConfig('mart_base_url')?->value? (externalConfig('mart_base_url')?->value.'/login/admin') :"#"}}"
                                                target="_blank"
                                                class="text-underline text-primary">{{translate("Admin Panel")}}</a>
                                            <br>
                                            {{translate('Go to “Settings → Ride Sharing Setup & Integration”')}}
                                            <br>
                                            {{translate("Copy the Generated ")}}
                                            <strong>{{translate("System Token ")}}</strong>{{translate("and paste it here to the Ecommerce System Token input field.")}}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="">
                                        <img src="{{dynamicAsset('public/assets/admin-module/img/how-it-works/step-2.svg')}}"
                                             alt=""
                                             class="mb-20">
                                        <div class="how-it-count">
                                            <span>3</span>
                                        </div>
                                        <h5 class="mb-2">{{(businessConfig('business_name')?->value ?? "Vito" ) . ' ' .translate('System Token Generate')}}</h5>
                                        <p>
                                            {{translate("At last,  Click on the  ")}}
                                            <strong>{{translate("Generate Token ")}}</strong>
                                            {{translate("button for automatic token generation & paste it Into the input field of ")}}
                                            {{(businessConfig('business_name')?->value ?? "Vito") . ' ' .translate('System Token Generate')}}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <br>
                            <div class="pb-1">
                                <i class="text-dark">{{translate('Note :  Follow the same steps on 6amMart to successfully connect Vito with 6amMart')}}</i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')

    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan
        $("#generateSystemSelfToken").on("click", function () {
            generateRandomToken(64);
        });

        document.getElementById('copyButton').addEventListener('click', function () {
            const input = document.getElementById('systemSelfToken');

            // Select the input field text
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices

            // Copy the text inside the input field to the clipboard
            navigator.clipboard.writeText(input.value).then(function () {
                toastr.success('Text copied to clipboard: ' + input.value);
            }).catch(function (error) {
                toastr.error('Failed to copy text: ', error);
            });
        });

        function generateRandomToken(length) {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let token = '';
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * characters.length);
                token += characters.charAt(randomIndex);
            }
            $('#systemSelfToken').val(token)
        }

        $('#notification_setup_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });
    </script>
@endpush
