import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/image_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/loader_widget.dart';
import 'package:ride_sharing_user_app/features/realtime_location_trac/controllers/location_tracking_controller.dart';
import 'package:ride_sharing_user_app/helper/login_helper.dart';
import 'package:ride_sharing_user_app/theme/theme_controller.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class LiveLocationScreen extends StatefulWidget {
  const LiveLocationScreen({super.key, required this.trackingUrl});
  final String? trackingUrl;

  @override
  State<LiveLocationScreen> createState() => _LiveLocationScreenState();
}

class _LiveLocationScreenState extends State<LiveLocationScreen> {
  GoogleMapController? _mapController;
  Timer? _timer;
  String? trackingId;
  @override
  void initState() {
    super.initState();
    trackingId = widget.trackingUrl?.split('/').last;
    _timer = Timer.periodic(Duration(seconds: 10), (time){
      Get.find<LocationTrackingController>().getTrackingDetails(trackingId ?? '');
    });
  }

  @override
  void dispose() {
    _mapController?.dispose();
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GetBuilder<LocationTrackingController>(builder: (locationTrackingController) {
      return Scaffold(body: PopScope(
        canPop: false,
        onPopInvokedWithResult: (res,data){
          _onBackPress();
        },
        child: BodyWidget(
          appBar: AppBarWidget(
            title: 'live_location_tracking'.tr,
            onBackPressed: () {
              _onBackPress();
            },
          ),
          body: locationTrackingController.rideTrackDetailsModel == null ?
          LoaderWidget() :
          Stack(children: [
            GoogleMap(
              style: Get.isDarkMode
                  ? Get.find<ThemeController>().darkMap
                  : Get.find<ThemeController>().lightMap,
              initialCameraPosition: CameraPosition(
                target: locationTrackingController.currentPosition,
                zoom: 16,
              ),
              polylines: locationTrackingController.polylines,
              onMapCreated: (c) => _mapController = c,
              markers: locationTrackingController.markers,
              zoomControlsEnabled: false,
              compassEnabled: false,
              mapToolbarEnabled: false,
              minMaxZoomPreference: const MinMaxZoomPreference(0, AppConstants.mapZoom),
            ),

            DraggableScrollableSheet(
              initialChildSize: 0.22,
              minChildSize: 0.22,
              maxChildSize: 0.24,
              snap: true,
              snapSizes: const [0.22, 0.24],
              builder: (context, scrollController) {
                return _LiveLocationBottomSheet(
                  scrollController: scrollController,
                  driverName: locationTrackingController.rideTrackDetailsModel?.data?.driverName,
                  driverImageUrl: locationTrackingController.rideTrackDetailsModel?.data?.driverProfileImage,
                  vehicleModel: locationTrackingController.rideTrackDetailsModel?.data?.vehicleModelName,
                  vehiclePlate: locationTrackingController.rideTrackDetailsModel?.data?.licencePlateNumber,
                  vehicleImageUrl: locationTrackingController.rideTrackDetailsModel?.data?.vehicleImage,
                );
              },
            ),
          ]),
        ),
      ));
    });
  }
}

void _onBackPress(){
  LoginHelper().route(null);
}

class _LiveLocationBottomSheet extends StatelessWidget {
  const _LiveLocationBottomSheet({
    required this.scrollController,
    this.driverName,
    this.driverImageUrl,
    this.vehicleModel,
    this.vehiclePlate,
    this.vehicleImageUrl,
  });

  final ScrollController scrollController;
  final String? driverName;
  final String? driverImageUrl;
  final String? vehicleModel;
  final String? vehiclePlate;
  final String? vehicleImageUrl;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(Dimensions.radiusLarge)),
        boxShadow: [BoxShadow(
          color: Colors.black.withAlpha(25),
          blurRadius: 12,
          offset: const Offset(0, -4),
         )],
      ),
      child: ListView(
        controller: scrollController,
        padding: const EdgeInsets.symmetric(
          horizontal: Dimensions.paddingSizeDefault,
          vertical: Dimensions.paddingSizeDefault,
        ),
        children: [
          Center(child: Container(
            width: 40,
            height: 4,
            margin:
            const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
            decoration: BoxDecoration(
              color: Theme.of(context).hintColor.withAlpha(80),
              borderRadius: BorderRadius.circular(2),
            ),
          )),

          // Driver row
          Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              border: Border.all(color: Theme.of(context).hintColor.withAlpha(50)),
            ),
            child: Column(children: [
              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                child: Row(children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    child: ImageWidget(
                      image: driverImageUrl ?? '',
                      width: 40,
                      height: 40,
                      fit: BoxFit.cover,
                    ) ,
                  ),
                  const SizedBox(width: Dimensions.paddingSizeDefault),

                  Text(
                    driverName ?? '',
                    style: textSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                  ),
                ]),
              ),

              Divider(height: 0 ,color: Theme.of(context).hintColor.withAlpha(50)),
                
              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                child: Row(children: [
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                    if (vehicleModel != null) ...[
                      Text(
                        vehicleModel!,
                        style: textSemiBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                      ),
                    ],

                    if (vehiclePlate != null)
                      Text(
                        vehiclePlate!,
                        style: textRegular.copyWith(
                          fontSize: Dimensions.fontSizeSmall,
                          color: Theme.of(context).hintColor,
                        ),
                      ),
                  ]),

                  Spacer(),

                  if (vehicleImageUrl != null)
                    Container(
                      height: 40,
                      width: 40,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        color: Theme.of(context).hintColor.withAlpha(20),
                      ),
                      child: ImageWidget(
                        image: vehicleImageUrl!,
                        width: 24,
                        height: 24,
                        fit: BoxFit.contain,
                      ),
                    ),
                ]),
              )
            ]),
          ),
        ],
      ),
    );
  }
}