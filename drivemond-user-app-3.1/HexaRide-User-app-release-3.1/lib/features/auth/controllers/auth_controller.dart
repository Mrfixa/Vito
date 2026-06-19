import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/features/auth/domain/enums/verification_from_enum.dart';
import 'package:ride_sharing_user_app/features/auth/domain/models/sign_up_body.dart';
import 'package:ride_sharing_user_app/features/auth/domain/services/auth_service_interface.dart';
import 'package:ride_sharing_user_app/features/auth/screens/otp_signup_screen.dart';
import 'package:ride_sharing_user_app/features/auth/widgets/manual_auth_waring_bottom_sheet_widget.dart';
import 'package:ride_sharing_user_app/features/dashboard/controllers/bottom_menu_controller.dart';
import 'package:ride_sharing_user_app/features/auth/screens/reset_password_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/verification_screen.dart';
import 'package:ride_sharing_user_app/features/dashboard/screens/dashboard_screen.dart';
import 'package:ride_sharing_user_app/features/parcel/controllers/parcel_controller.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/safety_setup/controllers/safety_alert_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/helper/login_helper.dart';
import 'package:ride_sharing_user_app/helper/pusher_helper.dart';

class AuthController extends GetxController implements GetxService {
  final AuthServiceInterface authServiceInterface;
  AuthController({required this.authServiceInterface});

  bool _isLoading = false;
  bool _isOtpSending = false;
  String _verificationCode = '';
  bool _isActiveRememberMe = false;
  bool otpVerifying = false;
  String countryDialCode = '+1';
  bool get isLoading => _isLoading;
  bool get isOtpSending => _isOtpSending;
  String get verificationCode => _verificationCode;
  bool get isActiveRememberMe => _isActiveRememberMe;
  bool showNavigationBar = true;

  TextEditingController usernameController = TextEditingController();
  TextEditingController fNameController = TextEditingController();
  TextEditingController lNameController = TextEditingController();
  TextEditingController phoneController = TextEditingController();
  TextEditingController passwordController = TextEditingController();
  TextEditingController confirmPasswordController = TextEditingController();
  TextEditingController referralCodeController = TextEditingController();

  /// QR token collected by TokenGateScreen; forwarded into SignUpBody on registration.
  String? pendingQrToken;

  void toggleNavigationBar(){
    showNavigationBar = false;
    update();
  }

  void setCountryCode( String countryCode){
    countryDialCode  = countryCode;
    update();
  }

  Future<void> login(String countryCode, String phone, String password) async {
    _isLoading = true;
    update();

    final String fullPhoneNumber = countryCode + phone;
    Response? response = await authServiceInterface.login(phone: fullPhoneNumber, password: password);

    _isLoading = false;

    if (response?.statusCode == 200) {

      saveUserNumberAndPassword(countryCode, phone, password, false);
      setUserToken(response!.body['data']['token']);
      PusherHelper.initializePusher();
      updateToken();

      await Get.find<ProfileController>().getProfileInfo();
      _navigateLogin(countryCode, phone, password);

    } else if (response?.statusCode == 202) {
      final bool isPhoneNotVerified = response?.body['data']['is_phone_verified'] == 0;

      if (isPhoneNotVerified) {
        if (Get.find<ConfigController>().config?.isFirebaseOtpVerification ?? false) {
          firebaseOtpSend(fullPhoneNumber, from: VerificationForm.login);
        } else if(Get.find<ConfigController>().config?.isSmsGateway ?? false){
          sendOtp(fullPhoneNumber).then((_){
            Get.to(() => VerificationScreen(number: fullPhoneNumber, form: VerificationForm.login));
          });

        }else{
          showCustomSnackBar('sms_gateway_not_integrate'.tr);
        }
      }

    } else if(response?.statusCode == 408){
      Get.bottomSheet(ManualAuthWaringBottomSheetWidget(phoneNumber: phone, from: VerificationForm.resetPassword));
    }else {
      ApiChecker.checkApi(response!);
    }

    update();
  }

  Future<void> logOut() async {
    _isLoading = true;
    update();
    Response? response = await authServiceInterface.logOut();
    if(response!.statusCode == 200){
      Get.back();
      LoginHelper.checkLoginMedium();
      showCustomSnackBar('successfully_logout'.tr, isError: false);
      clearSharedData();
      Get.find<RideController>().clearRideDetails();
      Get.find<ParcelController>().clearParcelModel();
      Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
    }else{
      ApiChecker.checkApi(response);
    }
    _isLoading = false;
    update();
  }

  Future<void> register(SignUpBody signUpBody) async {
    _isLoading = true;
    update();

    Response? response = await authServiceInterface.registration(signUpBody: signUpBody);
    if(response!.statusCode == 200){
      login('', signUpBody.username ?? '', signUpBody.password!);
    } else if(response.statusCode == 407){
      Get.bottomSheet(ManualAuthWaringBottomSheetWidget(phoneNumber: '', from: VerificationForm.verifyUser));
    }else {
      ApiChecker.checkApi(response);
    }
    _isLoading = false;
    update();

  }

  void _navigateLogin(String code, String phone, String password){
    if (_isActiveRememberMe) {
      saveUserNumberAndPassword(code,phone, password,false);
    } else {
      clearUserNumberAndPassword();
    }
    Get.find<BottomMenuController>().resetNavBar();
    Get.find<BottomMenuController>().navigateToDashboard();
  }

  Future<Response> sendOtp(String phone) async{
    _isOtpSending = true;
    update();

    Response? response = await authServiceInterface.sendOtp(phone: phone);
    if(response!.statusCode == 200){
      _isOtpSending = false;
      showCustomSnackBar('otp_sent_successfully'.tr, isError: false);
    }else{
      _isOtpSending = false;
      ApiChecker.checkApi(response);
    }

    update();
    return response;
  }

  Future<void> firebaseOtpSend(String phoneNumber,{bool canRoute = true, required VerificationForm from})async {
    _isOtpSending = true;
    update();

    await FirebaseAuth.instance.verifyPhoneNumber(
      phoneNumber: phoneNumber,
      verificationCompleted: (PhoneAuthCredential credential) {},
      verificationFailed: (FirebaseAuthException e) {
        _isOtpSending = false;
        update();

        if(e.code == 'invalid-phone-number') {
          showCustomSnackBar('please_submit_a_valid_phone_number'.tr);
        }else{
          showCustomSnackBar(e.message?.replaceAll('_', ' ') ?? '');
        }

      },
      codeSent: (String vId, int? resendToken) {

        _isOtpSending = false;
        update();
        if(canRoute){
          Get.to(() => VerificationScreen(number: phoneNumber, form: from, session: vId));
        }

      },
      codeAutoRetrievalTimeout: (String verificationId) {},
    );


  }

  Future<Response> checkOAuth({required String countryCode,  required String number}) async{
    _isOtpSending = true;
    update();
    Response? response = await authServiceInterface.isUserRegistered(phone: countryCode+number);
    if(response!.statusCode == 200){
      _isOtpSending = false;
    }else{
      _isOtpSending = false;
      ApiChecker.checkApi(response);
    }
    update();
    return response;
  }

  Future<Response> otpVerification(String phone, String otp, { required VerificationForm from, String? session}) async{
    otpVerifying = true;
    update();
    Response? response;
    if(Get.find<ConfigController>().config?.isFirebaseOtpVerification ?? false){
      response = await authServiceInterface.verifyFirebaseOtp(phone: phone, otp: otp,session: session!);
    }else{
      response = await authServiceInterface.verifyOtp(phone: phone, otp: otp);
    }
    if(response!.statusCode == 200) {
      otpVerifying = false;
      _verificationCode = '';
      updateVerificationCode('');
      if(from == VerificationForm.login) {
        setUserToken(response.body['data']['token']);
        updateToken();
        await Get.find<ProfileController>().getProfileInfo();
        Get.find<BottomMenuController>().navigateToDashboard();

      }else if(from == VerificationForm.resetPassword){
        otpVerifying = false;
        Get.to(() =>  ResetPasswordScreen(phoneNumber: phone));
      }else if(from == VerificationForm.verifyUser){
        registrationFromOtp(
          SignUpBody(
              fName: fNameController.text.trim(),
              lName: lNameController.text.trim(),
              phone: countryDialCode + phoneController.text.trim(),
              password: passwordController.text.trim(),
              confirmPassword: confirmPasswordController.text.trim(),
              referralCode: referralCodeController.text.trim(),
              qrToken: pendingQrToken,
          ), updateFromRegistration: true
        );
        pendingQrToken = null;
      }

    }else if(response.statusCode == 406){
      Get.off(()=> OtpSignupScreen(phoneNumber: phone));
    }else{
      otpVerifying = false;
      ApiChecker.checkApi(response);
    }
    otpVerifying = false;
    update();
    return response;
  }


  Future<void> registrationFromOtp(SignUpBody signUpBody, {required bool updateFromRegistration}) async {
    _isLoading = true;
    update();

    Response? response = await authServiceInterface.registrationFromOtp(signUpBody, updateFromRegistration: updateFromRegistration);

    _isLoading = false;

    if (response?.statusCode == 200) {

      setUserToken(response!.body['data']['token']);
      PusherHelper.initializePusher();
      updateToken();

      await Get.find<ProfileController>().getProfileInfo();
      Get.find<BottomMenuController>().resetNavBar();
      Get.find<BottomMenuController>().navigateToDashboard();

    }else {
      ApiChecker.checkApi(response!);
    }

    update();
  }

  Future<void> forgetPassword(String phone) async {
    _isLoading = true;
    update();
    Response? response = await authServiceInterface.forgetPassword(phone);
    if (response!.statusCode  == 200) {
      _isLoading = false;
      showCustomSnackBar('successfully_sent_otp'.tr, isError: false);
    }else{
      _isLoading = false;
      showCustomSnackBar('invalid_number'.tr);
    }
    update();
  }

  Future<void> updateToken() async {
    await authServiceInterface.updateToken();
  }

  Future<void> resetPassword(String phone, String password) async {
    _isLoading = true;
    update();
    Response? response = await authServiceInterface.resetPassword(phone, password);
    if (response!.statusCode == 200) {

      showCustomSnackBar('password_change_successfully'.tr, isError: false);
      LoginHelper.checkLoginMedium();
    }else{
      showCustomSnackBar(response.body['message']);
    }
    _isLoading = false;
    update();
  }

  Future<void> changePassword(String password, String newPassword) async {
    _isLoading = true;
    update();
    Response? response = await authServiceInterface.changePassword(password, newPassword);
    if (response!.statusCode == 200) {
      Get.offAll(()=> const DashboardScreen());
      showCustomSnackBar('password_change_successfully'.tr, isError: false);
    }else{
      ApiChecker.checkApi(response);
    }
    _isLoading = false;
    update();
  }

  void updateVerificationCode(String query, {bool isUpdate = true}) {
    _verificationCode = query;
    if(isUpdate){
      update();
    }
  }

  void toggleRememberMe() {
    _isActiveRememberMe = !_isActiveRememberMe;
    update();
  }

  void setRememberMe() {
    _isActiveRememberMe = true;
  }

  bool isLoggedIn() {
    return authServiceInterface.isLoggedIn();
  }

  Future <bool> clearSharedData() async {
    return authServiceInterface.clearSharedData();
  }

  void saveUserNumberAndPassword(String code,String number, String password,bool externalUser) {
    authServiceInterface.saveUserNumberAndPassword(code, number, password, externalUser);
  }

  String getUserNumber(bool externalUser) {
    return authServiceInterface.getUserNumber(externalUser);
  }

  String getLoginCountryCode(bool externalUser) {
    return authServiceInterface.getLoginCountryCode(externalUser);
  }
  Future<String> getUserPassword(bool externalUser) async {
    return authServiceInterface.getUserPassword(externalUser);
  }

  Future<bool> clearUserNumberAndPassword() async {
    return authServiceInterface.clearUserNumberAndPassword();
  }

  String getUserToken() {
    return authServiceInterface.getUserToken();
  }

  Future <void> setUserToken(String token) async{
    authServiceInterface.saveUserToken(token);
  }

  Future<void> permanentlyDelete() async {
    _isLoading = true;
    update();
    Response? response = await authServiceInterface.permanentlyDelete();
    if(response!.statusCode == 200){
      Get.back();
      LoginHelper.checkLoginMedium();
      showCustomSnackBar('successfully_delete_account'.tr, isError: false);
      clearSharedData();
      Get.find<SafetyAlertController>().cancelDriverNeedSafetyStream();
    }else{
      ApiChecker.checkApi(response);
    }
    _isLoading = false;
    update();
  }


  void saveFindingRideCreatedTime(){
    authServiceInterface.saveRideCreatedTime(DateTime.now());
  }

  void remainingFindingRideTime() async{
    String time = await authServiceInterface.remainingTime();
    if(time.isNotEmpty){
      DateTime oldTime = DateTime.parse(time);
      DateTime newTime = DateTime.now();
      int diff =  newTime.difference(oldTime).inSeconds;
      Get.find<RideController>().resumeCountingTimeState(diff);
    }
  }
}
