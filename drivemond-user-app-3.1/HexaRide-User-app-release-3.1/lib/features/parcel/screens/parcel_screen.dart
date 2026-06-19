import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/home/controllers/category_controller.dart';
import 'package:ride_sharing_user_app/features/map/screens/map_screen.dart';
import 'package:ride_sharing_user_app/features/parcel/widgets/dotted_border_card.dart';
import 'package:ride_sharing_user_app/features/parcel/widgets/parcel_category_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/features/home/widgets/banner_view.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/map/controllers/map_controller.dart';
import 'package:ride_sharing_user_app/features/parcel/controllers/parcel_controller.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';

class ParcelScreen extends StatefulWidget {
  const ParcelScreen({super.key});

  @override
  State<ParcelScreen> createState() => _ParcelScreenState();
}

class _ParcelScreenState extends State<ParcelScreen> {
  bool _isOffline = false;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  @override
  void initState() {
    super.initState();

    Get.find<ParcelController>().getParcelCategoryList(notify: true);
    Get.find<RideController>().initData();
    Get.find<LocationController>().initAddLocationData();
    Get.find<LocationController>().initParcelData();
    Get.find<ParcelController>().initParcelData();
    Get.find<MapController>().initializeData();
    Get.find<CategoryController>().setCouponFilterIndex(1, isUpdate: false);

    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) {
      final offline = !results.contains(ConnectivityResult.wifi) &&
          !results.contains(ConnectivityResult.mobile) &&
          !results.contains(ConnectivityResult.ethernet);
      if (offline != _isOffline) setState(() => _isOffline = offline);
    });
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Scaffold(
        body: Stack(children: [
          BodyWidget(
            appBar: AppBarWidget(title: 'parcel_delivery', showLogo: true),
            body: Padding(padding: const EdgeInsets.all(Dimensions.paddingSizeDefault), child: Column(children: [

              if (_isOffline)
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeDefault,
                    vertical: Dimensions.paddingSizeSmall,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.offlineWarning,
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    boxShadow: [BoxShadow(color: AppColors.offlineWarning.withValues(alpha: 0.35), blurRadius: 6, offset: const Offset(0, 2))],
                  ),
                  child: Row(children: [
                    const Icon(Icons.wifi_off, color: Colors.white, size: 20),
                    const SizedBox(width: Dimensions.paddingSizeSmall),
                    Text('you_are_offline'.tr, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                  ]),
                ),

              const BannerView(),

              const DottedBorderCard(),

              const ParcelCategoryView(),

            ])),
          ),

          Positioned(
            bottom: Dimensions.paddingSizeDefault,
            left:  Dimensions.paddingSizeDefault,
            right:  Dimensions.paddingSizeDefault,
            child: ButtonWidget(
              buttonText: 'add_parcel'.tr,
              backgroundColor: _isOffline ? Theme.of(context).hintColor : null,
              onPressed: () {
                HapticFeedback.mediumImpact();
                if (_isOffline) {
                  showCustomSnackBar('you_are_offline'.tr);
                  return;
                }
                if(Get.find<ConfigController>().config!.maintenanceMode != null &&
                    Get.find<ConfigController>().config!.maintenanceMode!.maintenanceStatus == 1 &&
                    Get.find<ConfigController>().config!.maintenanceMode!.selectedMaintenanceSystem!.userApp == 1
                ){
                  showCustomSnackBar('maintenance_mode_on_for_parcel'.tr,isError: true);
                }else{
                  if(Get.find<ParcelController>().parcelCategoryList == null || Get.find<ParcelController>().parcelCategoryList!.isEmpty) {
                    showCustomSnackBar('no_parcel_category_found'.tr);
                  }else {
                    Get.find<ParcelController>().updateTabControllerIndex(0);
                    Get.find<ParcelController>().updateParcelState(ParcelDeliveryState.initial);
                    Get.to(() => const MapScreen(fromScreen: MapScreenType.parcel));
                  }
                }
              },
            ),
          ),

        ]),
      ),
    );
  }
}




