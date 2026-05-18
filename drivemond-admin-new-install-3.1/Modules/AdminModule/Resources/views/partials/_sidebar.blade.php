<aside class="aside">
    <!-- Aside Header -->
    <div class="aside-header">
        <!-- Logo -->
        <a href="{{route('admin.dashboard')}}" class="logo d-flex gap-2">
            <img width="115"
                 src="{{$logo ? dynamicStorage("storage/app/public/business/".$logo) : dynamicAsset('public/assets/admin-module/img/logo.png')}}"
                 alt="" class="main-logo">
        </a>
        <!-- End Logo -->

        <!-- Aside Toggle Menu Button -->
        <button class="toggle-menu-button aside-toggle bg-white p-0 dark-color">
            <i class="bi bi-chevron-left"></i>
        </button>
        <!-- End Aside Toggle Menu Button -->
    </div>
    <!-- End Aside Header -->

    <!-- Aside Body -->
    <div class="position-relative flex-grow-1">
        <div class="aside-body" data-trigger="scrollbar">
            <!-- User Profile -->
            <div class="user-profile media gap-3 align-items-center my-3">
                <div class="avatar rounded-circle">
                    <img class="fit-object dark-support rounded-circle"
                         src="{{ onErrorImage(
                        auth()->user()?->profile_image,
                        dynamicStorage('storage/app/public/employee/profile') . '/' . auth()->user()->profile_image,
                        dynamicAsset('public/assets/admin-module/img/user.png'),
                        'employee/profile/',
                    ) }}" alt="">
                </div>
                <div class="media-body ">
                    <div class="card-title word-break fw-bold">{{auth()->user()?->email}}</div>
                    <span class="card-text">{{auth()->user()?->user_type}}</span>
                </div>
            </div>
            <!-- End User Profile -->

            <div class="aside-search mb-3">
                <form action="#" class="search-form">
                    <div class="input-group search-form__input_group">
                            <span class="search-form__icon">
                                <i class="bi bi-search"></i>
                            </span>
                        <input type="search" id="search-bar-input" class="theme-input-style search-form__input"
                               placeholder="{{ translate('Search_Here') }}"/>
                    </div>
                </form>
            </div>

            <!-- Nav -->
            <ul class="main-nav nav">

                <li class="nav-category" title="{{ translate('dashboard') }}">
                    {{ translate('dashboard') }}
                </li>
                <li class="{{Request::is('admin') ?'active open':''}}">
                    <a href="{{route('admin.dashboard')}}">
                        <i class="bi bi-grid-fill"></i>
                        <span class="link-title">{{ translate('dashboard') }}</span>
                    </a>
                </li>
                @can('dashboard')
                    <li class="{{Request::is('admin/heat-map*') ?'active open':''}}">
                        <a href="{{route('admin.heat-map')}}">
                            <i class="bi bi-pin-map"></i>
                            <span class="link-title">{{ translate('Heat Map') }}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/fleet-map/*') ?'active open':''}}">
                        <a href="{{route('admin.fleet-map','all-driver')}}">
                            <i class="bi bi-map-fill"></i>
                            <span class="link-title">{{ translate('Fleet View') }}</span>
                        </a>
                    </li>
                @endcan

                @if(\Illuminate\Support\Facades\Gate::any(['zone_view', 'zone_add', 'zone_edit', 'zone_delete', 'zone_log', 'zone_export']))
                    <!---------- Start Zone Management --------------->
                    <li class="nav-category"
                        title="{{ translate('zone_management') }}">{{ translate('zone_management') }}</li>
                    <li class="{{Request::is('admin/zone')||Request::is('admin/zone/*')? 'active open' :''}}">
                        <a href="{{route('admin.zone.index')}}">
                            <i class="bi bi-map"></i>
                            <span class="link-title text-capitalize">{{ translate('zone_setup') }}</span>
                        </a>
                    </li>
                    <!---------- End Zone Management --------------->
                @endif


                @if(\Illuminate\Support\Facades\Gate::any(['trip_view', 'trip_edit', 'trip_delete', 'trip_log', 'trip_export']))
                    <!----------------- Start Trip Management ------------------------>
                    <li class="nav-category"
                        title="{{ translate('trip_management')}}">{{ translate('trip_management')}}</li>
                    <li class="{{ Request::is('admin/trip/log') || Request::is('admin/trip/list/*') || Request::is('admin/trip/details/*')?'active sub-menu-opened':'' }} text-capitalize">
                        <a href="#">
                            <i class="bi bi-car-front"></i>
                            <span class="link-title">{{ translate('trips') }}</span>
                        </a>
                        <ul class="nav flex-column sub-menu text-capitalize">
                            <li class="{{ Request::is('admin/trip/list/all') || (Request::is('admin/trip/details/*')  && Request::get('type') == 'all') ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', ['all'])}}">
                                    <i class="bi bi-car-front-fill"></i>
                                    <span class="link-title">{{ translate('all_trip')}}  </span> <span
                                        class="badge badge-primary float-end">{{$tripCount['all']}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/pending') || (Request::is('admin/trip/details/*')  && Request::get('type') == PENDING) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [PENDING])}}">
                                    <i class="bi bi-cloud-haze2-fill"></i>
                                    <span class="link-title">{{ translate(PENDING)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[PENDING]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/scheduled') || (Request::is('admin/trip/details/*')  && Request::get('type') == SCHEDULED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [SCHEDULED])}}">
                                    <i class="bi bi-cloud-haze2-fill"></i>
                                    <span class="link-title">{{ translate(SCHEDULED)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[SCHEDULED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/accepted') || (Request::is('admin/trip/details/*')  && Request::get('type') == ACCEPTED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [ACCEPTED])}}">
                                    <i class="bi bi-cloud-check-fill"></i>
                                    <span class="link-title">{{ translate(ACCEPTED)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[ACCEPTED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/ongoing') || (Request::is('admin/trip/details/*')  && Request::get('type') == ONGOING) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [ONGOING])}}">
                                    <i class="bi bi-clipboard-fill"></i>
                                    <span class="link-title">{{ translate(ONGOING)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[ONGOING]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/completed') || (Request::is('admin/trip/details/*')  && Request::get('type') == COMPLETED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [COMPLETED])}}">
                                    <i class="bi bi-clipboard-check-fill"></i>
                                    <span class="link-title">{{ translate(COMPLETED)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[COMPLETED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/cancelled') || (Request::is('admin/trip/details/*')  && Request::get('type') == CANCELLED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [CANCELLED])}}">
                                    <i class="bi bi-cloud-minus-fill"></i>
                                    <span class="link-title">{{ translate(CANCELLED)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[CANCELLED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/returning') || (Request::is('admin/trip/details/*')  && Request::get('type') == RETURNING) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [RETURNING])}}">
                                    <i class="bi bi-cloud-minus-fill"></i>
                                    <span class="link-title">{{ translate(RETURNING)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[RETURNING]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/list/returned') || (Request::is('admin/trip/details/*')  && Request::get('type') == RETURNED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.index', [RETURNED])}}">
                                    <i class="bi bi-cloud-minus-fill"></i>
                                    <span class="link-title">{{ translate(RETURNED)}}</span>
                                    <span class="badge badge-primary float-end">{{$tripCount[RETURNED]}}</span>
                                </a>
                            </li>
                        </ul>

                    </li>
                    <li class="{{ Request::is('admin/trip/refund/log') || Request::is('admin/trip/refund/list/*') || Request::is('admin/trip/refund/details/*')?'active sub-menu-opened':'' }} text-capitalize">
                        <a href="#">
                            <i class="bi bi-car-front"></i>
                            <span class="link-title">{{ translate('parcel_refund_request') }}</span>
                        </a>
                        <ul class="nav flex-column sub-menu text-capitalize">
                            <li class="{{ Request::is('admin/trip/refund/list/pending') || (Request::is('admin/trip/refund/details/*')  && Request::get('type') == PENDING) ?'active open':'' }}">
                                <a href="{{route('admin.trip.refund.index', [PENDING])}}">
                                    <i class="bi bi-cloud-haze2-fill"></i>
                                    <span class="link-title">{{ translate(PENDING)}}</span>
                                    <span class="badge badge-primary float-end">{{$parcelRefundCount[PENDING]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/refund/list/approved') || (Request::is('admin/trip/refund/details/*')  && Request::get('type') == APPROVED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.refund.index', [APPROVED])}}">
                                    <i class="bi bi-cloud-check-fill"></i>
                                    <span class="link-title">{{ translate(APPROVED)}}</span>
                                    <span class="badge badge-primary float-end">{{$parcelRefundCount[APPROVED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/refund/list/denied') || (Request::is('admin/trip/refund/details/*')  && Request::get('type') == DENIED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.refund.index', [DENIED])}}">
                                    <i class="bi bi-clipboard-fill"></i>
                                    <span class="link-title">{{ translate(DENIED)}}</span>
                                    <span class="badge badge-primary float-end">{{$parcelRefundCount[DENIED]}}</span>
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/trip/refund/list/refunded') || (Request::is('admin/trip/refund/details/*')  && Request::get('type') == REFUNDED) ?'active open':'' }}">
                                <a href="{{route('admin.trip.refund.index', [REFUNDED])}}">
                                    <i class="bi bi-clipboard-check-fill"></i>
                                    <span class="link-title">{{ translate(REFUNDED)}}</span>
                                    <span class="badge badge-primary float-end">{{$parcelRefundCount[REFUNDED]}}</span>
                                </a>
                            </li>
                        </ul>

                    </li>
                    <li class="{{ Request::is('admin/safety-alert/*')?'active open':'' }}">
                        <a href="{{route('admin.safety-alert.index', CUSTOMER)}}">
                            <i class="bi bi-shield-fill-check"></i>
                            <span class="link-title text-capitalize">{{ translate('Solved Alert List') }}</span>

                        </a>
                    </li>
                    <li class="{{ Request::is('admin/mart/products*')?'active open':'' }}">
                        <a href="{{route('admin.mart.products.index')}}">
                            <i class="bi bi-shop"></i>
                            <span class="link-title text-capitalize">{{ translate('VitoMart Products') }}</span>
                        </a>
                    </li>
                    <li class="{{ Request::is('admin/qr-tokens*')?'active open':'' }}">
                        <a href="{{route('admin.qr-tokens.index')}}">
                            <i class="bi bi-qr-code"></i>
                            <span class="link-title text-capitalize">{{ translate('QR Tokens') }}</span>
                        </a>
                    </li>
                    <!----------------- End Trip Management ------------------------>
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['promotion_view', 'promotion_add', 'promotion_edit', 'promotion_delete', 'promotion_log', 'promotion_export']))
                    <!---------- Start Promotion Management --------------->
                    <li class="nav-category"
                        title="{{ translate('promotion_management') }}">{{ translate('promotion_management') }}</li>
                    <li class="{{ Request::is('admin/promotion/banner-setup*')?'active open':'' }}">
                        <a href="{{route('admin.promotion.banner-setup.index')}}">
                            <i class="bi bi-flag-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('banner_setup') }}</span>

                        </a>
                    </li>
                    <!-- Coupon Setup -->
                    <li class="{{Request::is('admin/promotion/coupon-setup*')?'active sub-menu-opened':''}} text-capitalize">
                        <a href="#">
                            <i class="bi bi-ticket-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('coupon_setup') }}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav flex-column sub-menu text-capitalize">
                            <li class="{{Request::is('admin/promotion/coupon-setup') | Request::is('admin/promotion/coupon-setup/edit*')? 'active open' : ''}}">
                                <a href="{{ route('admin.promotion.coupon-setup.index')  }}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('coupon_list') }}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/promotion/coupon-setup/create') ? 'active open' : ''}}">
                                <a href="{{route('admin.promotion.coupon-setup.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('add_new_coupon') }}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <!-- Coupon Setup End-->
                    <!-- Discount Setup -->
                    <li class="{{Request::is('admin/promotion/discount-setup*')?'active sub-menu-opened':''}} text-capitalize">
                        <a href="#">
                            <i class="bi bi-ticket-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('discount_setup') }}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav flex-column sub-menu text-capitalize">
                            <li class="{{Request::is('admin/promotion/discount-setup') | Request::is('admin/promotion/discount-setup/edit*')? 'active open' : ''}}">
                                <a href="{{ route('admin.promotion.discount-setup.index')  }}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('discount_list') }}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/promotion/discount-setup/create') ? 'active open' : ''}}">
                                <a href="{{route('admin.promotion.discount-setup.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('add_new_discount') }}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <!-- Coupon Setup End-->
                    <li class="{{ Request::is('admin/promotion/send-notification*')?'active open':'' }}">
                        <a href="{{route('admin.promotion.send-notification.index')}}">
                            <i class="bi bi-bell-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('send_notification') }}</span>
                        </a>
                    </li>
                    <!---------- End Promotion Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['user_view', 'user_add', 'user_edit', 'user_delete', 'user_log', 'user_export']))
                    <!---------- Start User Management --------------->
                    <li class="nav-category"
                        title="{{ translate('user_management')}}">{{ translate('user_management')}}</li>
                    <li class="{{Request::is('admin/driver/level*')? 'active sub-menu-opened' : ''}}">
                        <a href="">
                            <i class="bi bi-people-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('driver_level_setup')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/driver/level') || Request::is('admin/driver/level/edit/*') || Request::is('admin/driver/level/log*') ? 'active open' : ''}}">
                                <a href="{{route('admin.driver.level.index')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('driver_levels')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/level/create') ? 'active open' : ''}}">
                                <a href="{{route('admin.driver.level.create')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('add_driver_level')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <li class="has-sub-item {{Request::is('admin/driver') || Request::is('admin/driver/create') || Request::is('admin/driver/edit/*') || Request::is('admin/driver/show*') || Request::is('admin/driver/trash')
                    || Request::is('admin/driver/edit/*') || Request::is('admin/driver/profile-update-request-list') || Request::is('admin/driver/log*') || Request::is('admin/driver/cash*') || Request::is('admin/driver/verification*')  ? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-people-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('driver_setup')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/driver') || Request::is('admin/driver/edit/*') || Request::is('admin/driver/log*') || Request::is('admin/driver/cash*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('driver_list')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/create') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('add_new_driver')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/profile-update-request-list') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.profile-update-request-list')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('driver_identity_request_list')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/verification/unverified-list') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.verification.unverified-list')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('unverified_list')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <li class="has-sub-item{{Request::is('admin/driver/withdraw*') ? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-cash-coin"></i>
                            <span class="link-title text-capitalize">{{translate('withdraw')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/driver/withdraw-method') || Request::is('admin/driver/withdraw-method/edit/*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.withdraw-method.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('method_list')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/withdraw-method/create') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.withdraw-method.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('add_method')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/driver/withdraw/request*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.driver.withdraw.requests')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('withdraw_requests')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <li class="{{Request::is('admin/customer/level*')? 'active sub-menu-opened' : ''}}">
                        <a href="">
                            <i class="bi bi-person-fill-add"></i>
                            <span class="link-title text-capitalize">{{translate('customer_level_setup')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/customer/level') || Request::is('admin/customer/level/edit*') || Request::is('admin/customer/level/log*')? 'active open' : ''}}">
                                <a href="{{route('admin.customer.level.index')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('customer_levels')}}
                                </a>
                            </li>
                            @can('add', \Modules\UserManagement\Entities\UserLevel::class)
                                <li class="{{Request::is('admin/customer/level/create') ? 'active open' : ''}}">
                                    <a href="{{route('admin.customer.level.create')}}" class="text-capitalize">
                                        <i class="bi bi-dash-lg"></i>
                                        {{translate('add_customer_level')}}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                        <!-- End Sub Menu -->
                    </li>

                    <li class="has-sub-item {{Request::is('admin/customer/show*') || Request::is('admin/customer') || Request::is('admin/customer/create') || Request::is('admin/customer/edit/*') || Request::is('admin/customer/log*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-person-plus-fill"></i>
                            <span class="link-title text-capitalize">{{translate('customer_setup')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/customer') || Request::is('admin/customer/edit/*') || Request::is('admin/customer/show*') || Request::is('admin/customer/log*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.customer.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('customer_list')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/customer/create') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.customer.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('add_new_customer')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>

                    <li class="{{Request::is('admin/customer/wallet*')? 'active open' : ''}}">
                        <a href="{{route('admin.customer.wallet.index')}}">
                            <i class="bi bi-wallet-fill"></i>
                            <span class="link-title text-capitalize">{{translate('customer_wallet')}}</span>
                        </a>
                    </li>

                    <li class="{{Request::is('admin/wallet-bonus*')? 'active open' : ''}}">
                        <a href="{{route('admin.wallet-bonus.index')}}">
                            <i class="bi bi-wallet-fill"></i>
                            <span class="link-title text-capitalize">{{translate('wallet_bonus')}}</span>
                        </a>
                    </li>

                    <li class="has-sub-item {{Request::is('admin/employee*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-person-square"></i>
                            <span class="link-title text-capitalize">{{translate('employee_setup')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/employee/role*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.employee.role.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('attribute_setup')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/employee') || Request::is('admin/employee/edit*') || Request::is('admin/employee/log*') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.employee.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('employee_list')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/employee/create') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.employee.create')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('add_new_employee')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>

                    <li class="{{Request::is('admin/newsletter*')? 'active open' : ''}}">
                        <a href="{{route('admin.newsletter.index')}}">
                            <i class="bi bi-wallet-fill"></i>
                            <span class="link-title text-capitalize">{{translate('Newsletter')}}</span>
                        </a>
                    </li>
                    <!---------- End user Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['parcel_view', 'parcel_add', 'parcel_edit', 'parcel_delete', 'parcel_log', 'parcel_export']))
                    <!---------- Start Parcel Management --------------->

                    <li class="nav-category"
                        title="{{translate('parcel_management')}}">{{translate('parcel_management')}}</li>
                    <!-- Parcel Attribute Setup -->

                    <!-- Sub Menu -->
                    <li class="has-sub-item {{Request::is('admin/parcel*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-patch-plus"></i>
                            <span class="link-title">{{ translate('parcel_Attributes') }}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav flex-column sub-menu">
                            <li class="{{ Request::is('admin/parcel/attribute/category') || Request::is('admin/parcel/attribute/category/edit*')?'active open':'' }}">
                                <a href="{{ route('admin.parcel.attribute.category.index') }}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('parcel_Categories') }}
                                </a>
                            </li>
                            <li class="{{ Request::is('admin/parcel/attribute/weight') || Request::is('admin/parcel/attribute/weight/edit*')?'active open':'' }}">
                                <a href={{ route('admin.parcel.attribute.weight.index') }}>
                                    <i class="bi bi-dash-lg"></i>
                                    {{ translate('parcel_Weights') }}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <!-- DParcel Attribute Setup-->

                    <!---------- End Parcel Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['vehicle_view', 'vehicle_add', 'vehicle_edit', 'vehicle_delete', 'vehicle_log', 'vehicle_export']))
                    <!---------- Start Vehicle Management --------------->

                    <li class="nav-category" title="{{ translate('vehicles_management') }}">
                        {{ translate('vehicles_management') }}
                    </li>
                    <li class="{{Request::is('admin/vehicle/attribute-setup/*')?'active open':''}}">
                        <a href="{{ route('admin.vehicle.attribute-setup.brand.index') }}">
                            <i class="bi bi-ev-front-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('vehicle_attribute_setup') }}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/vehicle/log') || Request::is('admin/vehicle') || Request::is('admin/vehicle/show*') || Request::is('admin/vehicle/edit*')?'active open':''}}">
                        <a href="{{ route('admin.vehicle.index') }}">
                            <i class="bi bi-car-front-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('vehicle_list') }}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/vehicle/request/*') ?'active open':''}}">
                        <a href="{{ route('admin.vehicle.request.list') }}">
                            <i class="bi bi-car-front-fill"></i>
                            <span
                                class="link-title text-capitalize">{{ translate('new_vehicle_request_list') }}</span>
                        </a>
                    </li>
                    @if(businessConfig('update_vehicle_status', DRIVER_SETTINGS)?->value == 1)
                        <li class="{{Request::is('admin/vehicle/update/*') ?'active open':''}}">
                            <a href="{{ route('admin.vehicle.update.list') }}">
                                <i class="bi bi-car-front-fill"></i>
                                <span
                                    class="link-title text-capitalize">{{ translate('update_vehicle_request_list') }}</span>
                            </a>
                        </li>
                    @endif
                    <li class="{{Request::is('admin/vehicle/create') ?'active open':''}}">
                        <a href="{{ route('admin.vehicle.create') }}">
                            <i class="bi bi-truck-front-fill"></i>
                            <span class="link-title text-capitalize">{{ translate('add_new_vehicle') }}</span>
                        </a>
                    </li>
                    <!---------- End Vehicle Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['fare_view', 'fare_add']))
                    <!---------- Start Fare Management --------------->
                    <li class="nav-category" title="{{translate('fare_management')}}">{{translate('fare_management')}}</li>
                    <li class="{{Request::is('admin/fare/trip*')? 'active open' : ''}}">
                        <a href="{{route('admin.fare.trip.index')}}">
                            <i class="bi bi-sign-intersection-y-fill"></i>
                            <span class="link-title text-capitalize">{{translate('trip_fare_setup')}}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/fare/parcel*')? 'active open' : ''}}">
                        <a href="{{route('admin.fare.parcel.index')}}">
                            <i class="bi bi-box"></i>
                            <span class="link-title text-capitalize">{{translate('parcel_delivery_fare_setup')}}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/fare/surge-pricing*')? 'active open' : ''}}">
                        <a href="{{ route('admin.fare.surge-pricing.index') }}">
                            <i class="bi bi-cash-stack"></i>
                            <span class="link-title text-capitalize">{{translate('surge_price')}}</span>
                        </a>
                    </li>
                    <!---------- End Fare Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['transaction_view', 'transaction_export']))
                    <!---------- Start Transaction Management --------------->
                    <li class="nav-category"
                        title="{{translate('transactions_&_reports')}}">{{translate('transactions_&_reports')}}</li>
                    <li class="{{Request::is('admin/transaction*')? 'active open' : ''}}">
                        <a href="{{route('admin.transaction.index')}}">
                            <i class="bi bi-cash-stack"></i>
                            <span class="link-title text-capitalize">{{translate('transactions')}}</span>
                        </a>
                    </li>
                    <li class="{{Request::is('admin/report*')? 'active open' : ''}}">
                        <a href="{{route('admin.report.earning')}}">
                            <i class="bi bi-cash-stack"></i>
                            <span class="link-title text-capitalize">{{translate('reports')}}</span>
                        </a>
                    </li>
                    <!---------- End Transaction Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['chatting_view']))
                    <!---------- Start Help and Support Management --------------->
                    <li class="nav-category"
                        title="{{ translate('help_&_support') }}">{{ translate('help_&_support') }}</li>
                    <li class="{{Request::is('admin/chatting*') ?'active open':''}}">
                        <a href="{{route('admin.chatting')}}">
                            <i class="bi bi-chat-left-dots"></i>
                            <span class="link-title">{{ translate('chatting') }}</span>
                        </a>
                    </li>
                    <!---------- End Help and Support Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['blog_view']))
                    <!---------- Start Help and Support Management --------------->
                    <li class="nav-category"
                        title="{{ translate('blog_management') }}">{{ translate('blog_management') }}</li>
                    <li class="{{ request()->routeIs('admin.blog.*') ? 'active open' : '' }}">
                        <a href="{{ route('admin.blog.index') }}">
                            <i class="bi bi-layout-text-window"></i>
                            <span class="link-title">{{ translate('blog_Setup') }}</span>
                        </a>
                    </li>
                    <!---------- End Help and Support Management --------------->
                @endif

                @if(\Illuminate\Support\Facades\Gate::any(['business_view', 'business_edit', 'business_delete']))
                    <!---------- Start Business Management --------------->
                    <li class="nav-category" title="Business Management">{{translate('business_management')}}</li>
                    <li class="
                {{Request::is('admin/business/setup*')? 'active sub-menu-opened' : ''}}">
                        <a href="{{route('admin.business.setup.info.index')}}">
                            <i class="bi bi-briefcase-fill"></i>
                            <span class="link-title text-capitalize">{{translate('business_setup')}}</span>
                        </a>

                    </li>
                    {{--                <li class="--}}
                    {{--                {{Request::is('admin/business/external*')? 'active sub-menu-opened' : ''}}">--}}
                    {{--                    <a href="{{route('admin.business.external.index')}}">--}}
                    {{--                        <i class="bi bi-gear-wide-connected"></i>--}}
                    {{--                        <span class="link-title text-capitalize">{{translate('Ecommerce Setup and Integration')}}</span>--}}
                    {{--                    </a>--}}

                    {{--                </li>--}}
                    <li class="has-sub-item {{Request::is('admin/business/pages-media/*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-file-earmark-break-fill"></i>
                            <span class="link-title text-capitalize">{{translate('pages_&_media')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/business/pages-media/business-page') ? 'active open' : ''}}">
                                <a class="text-capitalize"
                                   href="{{route('admin.business.pages-media.business-page.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('business_pages')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/pages-media/landing-page/*') ? 'active open' : ''}}">
                                <a class="text-capitalize"
                                   href="{{route('admin.business.pages-media.landing-page.intro-section.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('landing_Page_Setup')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/pages-media/social-media') ? 'active open' : ''}}">
                                <a class="text-capitalize" href="{{route('admin.business.pages-media.social-media')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('social_media_links')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <li class="has-sub-item  {{Request::is('admin/business/configuration*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-gear-wide-connected"></i>
                            <span class="link-title">{{translate('configurations')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/business/configuration/notification*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.configuration.notification.index', ['type' => 'schedule-trip'])}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('Notification')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/configuration/third-party/*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.configuration.third-party.payment-method.index')}}"
                                   class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('3rd_party')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/configuration/face-verification-api*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.configuration.face-verification-api.index')}}"
                                   class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('Face_Verification_API')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/configuration/ai-setup*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.configuration.ai-setup.index')}}"
                                   class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('AI_Setup')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/configuration/login-settings*') ? 'active open' : ''}}">
                                <a href="{{ route('admin.business.configuration.login-settings.index') }}"
                                   class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('Login_Settings')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <li class="has-sub-item
                {{Request::is('admin/business/environment-setup*') ||Request::is('admin/business/app-version-setup*') ||
                    Request::is('admin/business/clean-database*') || Request::is('admin/business/languages*') || Request::is('admin/business/deep-linking-setup*')? 'active sub-menu-opened' : ''}}">
                        <a href="#">
                            <i class="bi bi-sliders2-vertical"></i>
                            <span class="link-title text-capitalize">{{translate('system_settings')}}</span>
                        </a>
                        <!-- Sub Menu -->
                        <ul class="nav sub-menu">
                            <li class="{{Request::is('admin/business/environment-setup*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.environment-setup.index')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('environment_setup')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/app-version-setup*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.app-version-setup.index')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('app_version_setup')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/clean-database*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.clean-database.index')}}" class="text-capitalize">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('clean_database')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/languages*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.languages.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('languages')}}
                                </a>
                            </li>
                            <li class="{{Request::is('admin/business/deep-linking-setup*') ? 'active open' : ''}}">
                                <a href="{{route('admin.business.deep-linking.index')}}">
                                    <i class="bi bi-dash-lg"></i>
                                    {{translate('Deep Linking Setup')}}
                                </a>
                            </li>
                        </ul>
                        <!-- End Sub Menu -->
                    </li>
                    <!---------- End Business Management --------------->
                @endif
            </ul>
            <!-- End Nav -->
        </div>
    </div>
    <!-- End Aside Body -->
</aside>

@push('script')
    <script src="{{dynamicAsset('public/assets/admin-module/js/admin-module/sidebar.js')}}"></script>
@endpush
