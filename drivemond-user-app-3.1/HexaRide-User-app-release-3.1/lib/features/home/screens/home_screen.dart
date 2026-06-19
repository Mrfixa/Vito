import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:just_the_tooltip/just_the_tooltip.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';
import 'package:ride_sharing_user_app/features/home/widgets/banner_view.dart';
import 'package:ride_sharing_user_app/features/home/widgets/best_offers_widget.dart';
import 'package:ride_sharing_user_app/features/home/widgets/category_view.dart';
import 'package:ride_sharing_user_app/features/home/widgets/coupon_home_widget.dart';
import 'package:ride_sharing_user_app/features/home/widgets/home_map_view.dart';
import 'package:ride_sharing_user_app/features/home/widgets/home_search_widget.dart';
import 'package:ride_sharing_user_app/features/home/widgets/home_referral_view_widget.dart';
import 'package:ride_sharing_user_app/features/home/widgets/visit_to_mart_widget.dart';
import 'package:ride_sharing_user_app/features/my_offer/controller/offer_controller.dart';
import 'package:ride_sharing_user_app/features/parcel/controllers/parcel_controller.dart';
import 'package:ride_sharing_user_app/features/parcel/screens/parcel_list_view_screen.dart';
import 'package:ride_sharing_user_app/features/parcel/screens/parcel_screen.dart';
import 'package:ride_sharing_user_app/features/parcel/widgets/driver_request_dialog.dart';
import 'package:ride_sharing_user_app/features/ride/screens/ride_list_view_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/features/splash/domain/models/config_model.dart';
import 'package:ride_sharing_user_app/helper/home_screen_helper.dart';
import 'package:ride_sharing_user_app/helper/pusher_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/features/address/controllers/address_controller.dart';
import 'package:ride_sharing_user_app/features/home/controllers/banner_controller.dart';
import 'package:ride_sharing_user_app/features/home/controllers/category_controller.dart';
import 'package:ride_sharing_user_app/features/home/widgets/home_my_address.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/map/screens/map_screen.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_store_screen.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  JustTheController rideShareToolTip = JustTheController();
  JustTheController parcelDeliveryToolTip = JustTheController();
  final ScrollController _scrollController = ScrollController();
  bool _isShowRideIcon = true;


  String greetingMessage() {
    var timeNow = DateTime.now().hour;
    if (timeNow <= 12) {
      return 'good_morning'.tr;
    } else if ((timeNow > 12) && (timeNow <= 16)) {
      return 'good_afternoon'.tr;
    } else if ((timeNow > 16) && (timeNow < 20)) {
      return 'good_evening'.tr;
    } else {
      return 'good_night'.tr;
    }
  }

  @override
  void initState() {
    super.initState();
    Get.find<AddressController>().updateLastLocation();

    _scrollController.addListener((){
      if(_scrollController.offset > 20){
        setState(() {
          _isShowRideIcon = false;
        });

      }else{
        setState(() {
          _isShowRideIcon = true;
        });

      }
    });

    loadData();
  }

  @override
  void dispose() {
    rideShareToolTip.dispose();
    parcelDeliveryToolTip.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  bool clickedMenu = false;
  Future<void> loadData({bool isReload = false}) async{

    if(isReload) {
      Get.find<ConfigController>().getConfigData();
    }

    Get.find<ParcelController>().getUnpaidParcelList();
    Get.find<BannerController>().getBannerList();
    Get.find<CategoryController>().getCategoryList();
    Get.find<AddressController>().getAddressList(1);
    Get.find<CategoryController>().setCouponFilterIndex(0, isUpdate: false);
    Get.find<OfferController>().getOfferList(1);

    if(Get.find<ProfileController>().profileModel == null){
      Get.find<ProfileController>().getProfileInfo();
    }

    await Get.find<RideController>().getRunningRideList();
    final runningRideData = Get.find<RideController>().runningRideList?.data;
    if(runningRideData != null){
      for(var element in runningRideData){
        PusherHelper().pusherDriverStatus(element.id!);
      }
    }

    await Get.find<RideController>().getCurrentRegularRide();
    if(Get.find<RideController>().rideDetails != null){
      Get.find<RideController>().getBiddingList(Get.find<RideController>().rideDetails!.id!, 1);
    }else{
      Get.find<RideController>().clearBiddingList();
    }

    await Get.find<ParcelController>().getRunningParcelList();
    if(Get.find<ParcelController>().parcelListModel?.data?.isNotEmpty == true){
      for (var element in Get.find<ParcelController>().parcelListModel!.data!) {
        PusherHelper().pusherDriverStatus(element.id!);
      }
    }

    final userAddress = Get.find<LocationController>().getUserAddress();
    if (userAddress == null || userAddress.latitude == null || userAddress.longitude == null) {
      showCustomSnackBar('you_have_to_allow'.tr);
    } else {
      await Get.find<RideController>().getNearestDriverList(
        userAddress.latitude!.toString(),
        userAddress.longitude!.toString(),
      );
    }

    HomeScreenHelper.checkMaintanceMode();
  }


  @override
  Widget build(BuildContext context) {
    ConfigModel? config = Get.find<ConfigController>().config;

    return Scaffold(
      body: GetBuilder<ProfileController>(builder: (profileController) {
        return GetBuilder<RideController>(builder: (rideController) {
          return GetBuilder<ParcelController>(builder: (parcelController) {
            return BodyWidget(
              appBar: AppBarWidget(
                title: '${greetingMessage()}, ${profileController.customerFirstName()}',
                showBackButton: false, isHome: true, fontSize: Dimensions.fontSizeLarge,
              ),
              body: RefreshIndicator(
                onRefresh: () async {
                  await loadData(isReload: true);
                },
                child: CustomScrollView(
                  controller: _scrollController,
                  slivers: [
                    SliverToBoxAdapter(child: Column(children: [
                      Padding(
                        padding: const EdgeInsets.only(
                          top:Dimensions.paddingSize,left: Dimensions.paddingSize,
                          right: Dimensions.paddingSize,
                        ),
                        child: Column(children: [
                          const BannerView(),

                          const Padding(
                            padding: EdgeInsets.only(top:Dimensions.paddingSize),
                            child: CategoryView(),
                          ),

                          const Padding(
                            padding: EdgeInsets.only(top: Dimensions.paddingSize),
                            child: _ServiceCardsRow(),
                          ),

                          if((config?.externalSystem ?? false) && Get.find<AuthController>().isLoggedIn())...[
                            const VisitToMartWidget(),
                            const SizedBox(height: Dimensions.paddingSizeDefault)
                          ],

                          const HomeSearchWidget(),
                        ]),
                      ),
                      const SizedBox(height:Dimensions.paddingSizeDefault),

                      const HomeMyAddress(addressPage: AddressPage.home),

                      const Padding(
                        padding: EdgeInsets.only(
                          top:Dimensions.paddingSize,left: Dimensions.paddingSize,
                          right: Dimensions.paddingSize,
                        ),
                        child: HomeMapView(title: 'rider_around_you'),
                      ),

                      if(config?.referralEarningStatus ?? false)
                        const HomeReferralViewWidget(),

                      const BestOfferWidget(),

                      const HomeCouponWidget(),

                      const SizedBox(height: 100)
                    ])),
                  ],
                ),
              ),
            );
          });
        });
      }),
      floatingActionButton: GetBuilder<RideController>(builder: (rideController){
        if(Get.find<ConfigController>().isShowToolTips){
          showToolTips();
        }
        return Column(mainAxisSize:MainAxisSize.min, children: [
          (Get.find<ParcelController>().parcelListModel?.totalSize ?? 0) > 0 && _isShowRideIcon ?
          Padding(
            padding: EdgeInsets.only(
                bottom:rideController.biddingList.isEmpty && ((rideController.runningRideList?.data?.length ?? 0) == 0) ? Get.height * 0.08 : 0
            ),
            child: JustTheTooltip(
              backgroundColor: Get.isDarkMode ?
              Theme.of(context).primaryColor :
              Theme.of(context).textTheme.bodyMedium!.color,
              controller: parcelDeliveryToolTip,
              preferredDirection: AxisDirection.right,
              tailLength: 10,
              tailBaseWidth: 20,
              content: Container(width: 150,
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                child: Text(
                  'parcel_delivery'.tr,
                  style: textRegular.copyWith(
                    color: Colors.white, fontSize: Dimensions.fontSizeDefault,
                  ),
                ),
              ),
              child: InkWell(
                onTap: ()=> Get.to(()=> const ParcelListViewScreen(title: 'ongoing_parcel_list')),
                child: Stack(children: [
                  Container(height: 38,width: 38,
                    padding: EdgeInsets.all(Dimensions.paddingSizeSmall),
                    margin: EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                    decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context).primaryColor
                    ),
                    child: Image.asset(Images.parcelDeliveryIcon),
                  ),

                  Positioned(right: 0,top: 0,
                    child: Container(height: 20,width: 20,
                      decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Theme.of(context).cardColor
                      ),

                      child: Center(
                        child: Container(height: 18,width: 18,
                          decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Theme.of(context).colorScheme.error
                          ),
                          child: Center(child: Text(
                            '${Get.find<ParcelController>().parcelListModel?.totalSize}',
                            style: textRegular.copyWith(color: Theme.of(context).cardColor,fontSize: Dimensions.fontSizeSmall),
                          )),
                        ),
                      ),
                    ),
                  )
                ]),
              ),
            ),
          ) :
          const SizedBox(),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          (rideController.runningRideList?.data?.length ?? 0) > 0 && _isShowRideIcon ?
          Padding(
            padding: EdgeInsets.only(bottom: rideController.biddingList.isEmpty ? Get.height * 0.08 : 0),
            child: JustTheTooltip(
              backgroundColor: Get.isDarkMode ?
              Theme.of(context).primaryColor :
              Theme.of(context).textTheme.bodyMedium!.color,
              controller: rideShareToolTip,
              preferredDirection: AxisDirection.right,
              tailLength: 10,
              tailBaseWidth: 20,
              content: Container(width: 100,
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                child: Text(
                  'ride_share'.tr,
                  style: textRegular.copyWith(
                    color: Colors.white, fontSize: Dimensions.fontSizeDefault,
                  ),
                ),
              ),
              child: InkWell(
                onTap: ()=> Get.to(()=> const RideListViewScreen()),
                child: Stack(children: [
                  Container(height: 38,width: 38,
                    padding: EdgeInsets.all(Dimensions.paddingSizeSmall),
                    margin: EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                    decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context).primaryColor
                    ),
                    child: Image.asset(Images.rideShareIcon),
                  ),

                  Positioned(right: 0,top: 0,
                    child: Container(height: 20,width: 20,
                      decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Theme.of(context).cardColor
                      ),

                      child: Center(
                        child: Container(height: 18,width: 18,
                          decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Theme.of(context).colorScheme.error
                          ),
                          child: Center(child: Text(
                            '${rideController.runningRideList?.data?.length}',
                            style: textRegular.copyWith(color: Theme.of(context).cardColor,fontSize: Dimensions.fontSizeSmall),
                          )),
                        ),
                      ),
                    ),
                  )
                ]),
              ),
            ),
          ) :
          const SizedBox(),

          rideController.biddingList.isNotEmpty && _isShowRideIcon ?
          Padding(
            padding: EdgeInsets.only(bottom: Get.height * 0.08),
            child: InkWell(
              onTap: (){
                if(!rideController.isLoading){
                  rideController.getBiddingList(
                      rideController.rideDetails!.id!, 1
                  ).then((value) {
                    if(rideController.biddingList.isNotEmpty){

                      Get.dialog(
                          barrierDismissible: true,
                          barrierColor: Colors.black.withValues(alpha:0.5),
                          transitionDuration: const Duration(milliseconds: 500),
                          DriverRideRequestDialog(tripId: Get.find<RideController>().rideDetails!.id!)
                      );
                    }
                  });
                }
              },
              child: Stack(children: [
                Container(height: 38,width: 38,
                  padding: EdgeInsets.all(Dimensions.paddingSizeSeven),
                  margin: EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                  decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Theme.of(context).primaryColor
                  ),
                  child: Image.asset(Images.biddingIcon),
                ),

                Positioned(right: 0,top: 6,
                  child: Container(height: 12,width: 12,
                    decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Theme.of(context).cardColor
                    ),
                    child: Center(child: Container(
                      height: 10,width: 10,
                      decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Theme.of(context).colorScheme.error
                      ),
                    )),
                  ),
                )
              ]),
            ),
          ) :
          const SizedBox()
        ]);
      }),
    );
  }

  void showToolTips(){
    WidgetsBinding.instance.addPostFrameCallback((_){
      Future.delayed(const Duration(seconds: 1)).then((_){
        int ridingCount = (Get.find<RideController>().runningRideList?.data?.length ?? 0);
        int parcelCount = Get.find<ParcelController>().parcelListModel?.totalSize ?? 0;
        if(ridingCount > 0 && _isShowRideIcon){
          rideShareToolTip.showTooltip();
          Get.find<ConfigController>().hideToolTips();
          Future.delayed(const Duration(seconds: 5)).then((_){
            rideShareToolTip.hideTooltip();
          });
        }

        if(parcelCount > 0 && _isShowRideIcon){
          parcelDeliveryToolTip.showTooltip();
          Get.find<ConfigController>().hideToolTips();
          Future.delayed(const Duration(seconds: 5)).then((_){
            parcelDeliveryToolTip.hideTooltip();
          });
        }

      });
    });
  }

}

class _ServiceCardsRow extends StatelessWidget {
  const _ServiceCardsRow();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _ServiceCard(
          icon: Icons.directions_car_outlined,
          label: 'vito_ride'.tr,
          subtitle: 'book_ride'.tr,
          color: Theme.of(context).primaryColor,
          onTap: () => Get.to(() => const MapScreen(fromScreen: MapScreenType.ride)),
        ),
        const SizedBox(width: Dimensions.paddingSizeSmall),
        _ServiceCard(
          icon: Icons.inventory_2_outlined,
          label: 'vito_send'.tr,
          subtitle: 'send_package'.tr,
          color: AppColors.rideService,
          onTap: () => Get.to(() => const ParcelScreen()),
        ),
        const SizedBox(width: Dimensions.paddingSizeSmall),
        _ServiceCard(
          icon: Icons.storefront_outlined,
          label: 'vito_mart'.tr,
          subtitle: 'shop_mart'.tr,
          color: AppColors.parcelService,
          onTap: () => Get.to(() => const MartStoreScreen()),
        ),
      ],
    );
  }
}

class _ServiceCard extends StatefulWidget {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ServiceCard({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  State<_ServiceCard> createState() => _ServiceCardState();
}

class _ServiceCardState extends State<_ServiceCard> with SingleTickerProviderStateMixin {
  late AnimationController _animCtrl;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 120));
    _scaleAnim = Tween<double>(begin: 1.0, end: 0.93).animate(
      CurvedAnimation(parent: _animCtrl, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _animCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTapDown: (_) => _animCtrl.forward(),
        onTapUp: (_) {
          _animCtrl.reverse();
          widget.onTap();
        },
        onTapCancel: () => _animCtrl.reverse(),
        child: ScaleTransition(
          scale: _scaleAnim,
          child: Container(
          padding: const EdgeInsets.symmetric(
            vertical: Dimensions.paddingSizeDefault,
            horizontal: Dimensions.paddingSizeSmall,
          ),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            border: Border.all(color: widget.color.withValues(alpha: 0.25)),
            boxShadow: [
              BoxShadow(
                color: widget.color.withValues(alpha: 0.08),
                blurRadius: 6,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: widget.color.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(widget.icon, color: widget.color, size: 22),
              ),
              const SizedBox(height: 6),
              Text(
                widget.label,
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeSmall,
                  color: Theme.of(context).textTheme.bodyLarge?.color,
                ),
                textAlign: TextAlign.center,
              ),
              Text(
                widget.subtitle,
                style: textRegular.copyWith(
                  fontSize: 10,
                  color: Theme.of(context).hintColor,
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    ),
  );
  }
}




