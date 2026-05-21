import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/domain/models/error_response.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/helper/login_helper.dart';

class ApiChecker {
  static void checkApi(Response response) {
    if(response.statusCode == 401) {
      Get.find<ConfigController>().removeSharedData();
      LoginHelper.checkLoginMedium();

    }else if(response.statusCode == 403) {
      ErrorResponse errorResponse;
      errorResponse = ErrorResponse.fromJson(response.body);
      if(errorResponse.errors != null && errorResponse.errors!.isNotEmpty){
        showCustomSnackBar(errorResponse.errors![0].message!);
      }else{
        showCustomSnackBar(response.body['message']);
      }

    }else if(response.statusCode == 422) {
      ErrorResponse errorResponse;
      errorResponse = ErrorResponse.fromJson(response.body);
      if(errorResponse.errors != null && errorResponse.errors!.isNotEmpty){
        showCustomSnackBar(errorResponse.errors![0].message!);
      }else{
        showCustomSnackBar(response.body['message']);
      }

    }else if(response.statusCode == 429){
      showCustomSnackBar('too_many_requests'.tr);
    }else if(response.statusCode == 500){
      showCustomSnackBar(response.statusText ?? 'something_went_wrong'.tr);
    }else {
      showCustomSnackBar(response.statusText ?? 'something_went_wrong'.tr);
    }
  }
}
