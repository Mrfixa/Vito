import 'dart:async';
import 'dart:io';
import 'package:app_links/app_links.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/auth/domain/enums/verification_from_enum.dart';
import 'package:ride_sharing_user_app/features/auth/screens/otp_log_in_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/sign_in_screen.dart';
import 'package:ride_sharing_user_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/location/view/access_location_screen.dart';
import 'package:ride_sharing_user_app/features/maintainance_mode/maintainance_screen.dart';
import 'package:ride_sharing_user_app/features/realtime_location_trac/screens/live_location_screen.dart';
import 'package:ride_sharing_user_app/features/onboard/screens/onboarding_screen.dart';
import 'package:ride_sharing_user_app/features/payment/controllers/payment_controller.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/features/refund_request/controllers/refund_request_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/features/splash/domain/models/config_model.dart';
import 'package:ride_sharing_user_app/features/splash/screens/app_version_warning_screen.dart';
import 'package:ride_sharing_user_app/features/trip/controllers/trip_controller.dart';
import 'package:ride_sharing_user_app/helper/firebase_helper.dart';
import 'package:ride_sharing_user_app/helper/notification_helper.dart';
import 'package:ride_sharing_user_app/helper/pusher_helper.dart';
import 'package:ride_sharing_user_app/localization/language_selection_screen.dart';
import 'package:ride_sharing_user_app/localization/localization_controller.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';

class LoginHelper{

  void handleIncomingLinks(Map<String,dynamic>? notificationData) async{
    Get.find<TripController>().getRideCancellationReasonList();
    Get.find<TripController>().getParcelCancellationReasonList();
    Get.find<RefundRequestController>().getParcelRefundReasonList();
    Get.find<PaymentController>().getPaymentGetWayList();
    FirebaseHelper().subscribeFirebaseTopic();
    String? path = await initDynamicLinks();

    Get.find<ConfigController>().getConfigData().then((value){
      if(_isForceUpdate(Get.find<ConfigController>().config)) {
        Get.offAll(()=> const AppVersionWarningScreen());
      }else{
       if(path != null){
         Get.offAll(()=> LiveLocationScreen(trackingUrl: path));
       }else{
         route(notificationData);
       }

      }

    });

  }

  Future<String?> initDynamicLinks() async {
    final appLinks = AppLinks();
    final uri = await appLinks.getInitialLink();
    String? path;
    if (uri != null) {
      path = uri.path;

    }else{
      path = null;
    }
    return path;

  }

  bool _isForceUpdate(ConfigModel? config) {
    double minimumVersion = Platform.isAndroid
        ? config?.androidAppMinimumVersion ?? 0
        : Platform.isIOS
        ? config?.iosAppMinimumVersion ?? 0
        : 0;

    return minimumVersion > 0 && minimumVersion > AppConstants.appVersion;
  }

  void route(Map<String,dynamic>? notificationData) async {

    if(Get.find<AuthController>().getUserToken().isNotEmpty){
      PusherHelper.initializePusher();
    }

    Future.delayed(const Duration(milliseconds: 100), () {
      if(Get.find<AuthController>().isLoggedIn()) {
        if(Get.find<LocalizationController>().haveLocalLanguageCode()){
          forLoginUserRoute(notificationData);
        }else{
          Get.offAll(()=> LanguageSelectionScreen(notificationData: notificationData));
        }

      }else{
        forNotLoginUserRoute(notificationData);
      }
    });

  }

  void forNotLoginUserRoute(Map<String,dynamic>? notificationData){
    if(Get.find<ConfigController>().config!.maintenanceMode != null &&
        Get.find<ConfigController>().config!.maintenanceMode!.maintenanceStatus == 1 &&
        Get.find<ConfigController>().config!.maintenanceMode!.selectedMaintenanceSystem!.userApp == 1
    ){
      Get.offAll(() => const MaintenanceScreen());
    }else{
      if (Get.find<ConfigController>().showIntro()) {
        Get.offAll(() => OnBoardingScreen(notificationData: notificationData));

      }else {
        if(Get.find<LocalizationController>().haveLocalLanguageCode()){
          checkLoginMedium();

        }else{
          Get.offAll(()=> LanguageSelectionScreen(notificationData: notificationData));
        }

      }
    }
  }

  void forLoginUserRoute(Map<String,dynamic>? notificationData){
    if(notificationData != null) {
      NotificationHelper.notificationRouteCheck(notificationData, formSplash: true, userName: notificationData['user_name']);

    }else if(Get.find<LocationController>().getUserAddress() != null
        && Get.find<LocationController>().getUserAddress()!.address != null
        && Get.find<LocationController>().getUserAddress()!.address!.isNotEmpty) {

      Get.find<ProfileController>().getProfileInfo().then((value) {
        if(value.statusCode == 200) {
          Get.find<AuthController>().updateToken();
          Get.find<AuthController>().remainingFindingRideTime();
          Get.offAll(()=> const DashboardScreen());
        }
      });

    }else{
      Get.offAll(() => const AccessLocationScreen());
    }
  }

  static void checkLoginMedium(){
    final bool isManualLogin = Get.find<ConfigController>().config?.customerLoginOptions?.manualLogin ?? false;
    if(isManualLogin){
      Get.offAll(()=> const SignInScreen());
    }else{
      Get.offAll(()=> const OtpLoginScreen(from: VerificationForm.login));
    }
  }

}