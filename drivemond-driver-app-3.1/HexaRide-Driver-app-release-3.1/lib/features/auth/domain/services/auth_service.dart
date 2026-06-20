import 'package:image_picker/image_picker.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/auth/domain/models/signup_body.dart';
import 'package:ride_sharing_user_app/features/auth/domain/repositories/auth_repository_interface.dart';
import 'package:ride_sharing_user_app/features/auth/domain/services/auth_service_interface.dart';

class AuthService implements AuthServiceInterface{
 final AuthRepositoryInterface authRepositoryInterface;
 AuthService({required this.authRepositoryInterface});

  @override
  Future changePassword(String oldPassword, String password) {
    return authRepositoryInterface.changePassword(oldPassword, password);
  }

  @override
  Future changePin(String currentPin, String newPin) {
    return authRepositoryInterface.changePin(currentPin, newPin);
  }

  @override
  bool clearSharedAddress() {
   return authRepositoryInterface.clearSharedAddress();
  }

  @override
  bool clearSharedData() {
    return authRepositoryInterface.clearSharedData();
  }

  @override
  Future<bool> clearUserCredentials() {
    return authRepositoryInterface.clearUserCredential();
  }

  @override
  Future forgetPassword(String? phone) {
    return authRepositoryInterface.forgetPassword(phone);
  }

  @override
  String getDeviceToken() {
    return authRepositoryInterface.getDeviceToken();
  }

  @override
  String getUserCountryCode() {
    return getUserCountryCode();
  }

  @override
  String getUserNumber() {
    return authRepositoryInterface.getUserNumber();
  }

  @override
  Future<String> getUserPassword() async {
    return authRepositoryInterface.getUserPassword();
  }

  @override
  String getUserToken() {
    return authRepositoryInterface.getUserToken();
  }

  @override
  String getZonId() {
    return authRepositoryInterface.getZonId();
  }

  @override
  bool isLoggedIn() {
    return authRepositoryInterface.isLoggedIn();
  }

  @override
  bool isNotificationActive() {
    return authRepositoryInterface.isNotificationActive();
  }

  @override
  Future logOut() {
   return authRepositoryInterface.logOut();
  }

  @override
  Future login({required String phone, required String password}) {
    return authRepositoryInterface.login(phone: phone, password: password);
  }

  @override
  Future registration({required SignUpBody signUpBody, XFile? profileImage, List<MultipartBody>? identityImage, List<MultipartDocument>? documents}) {
    return authRepositoryInterface.registration(signUpBody: signUpBody,profileImage: profileImage, identityImage: identityImage,documents: documents);
  }

  @override
  Future registerWithOtp({required SignUpBody signUpBody, XFile? profileImage, List<MultipartBody>? identityImage, List<MultipartDocument>? documents, required bool updateFromRegistration}) {
    return authRepositoryInterface.registerWithOtp(signUpBody: signUpBody,profileImage: profileImage, identityImage: identityImage,documents: documents, updateFromRegistration: updateFromRegistration);
  }

  @override
  Future resetPassword(String phoneOrEmail, String password) {
    return authRepositoryInterface.resetPassword(phoneOrEmail, password);
  }

  @override
  Future<void> saveDeviceToken() {
    return authRepositoryInterface.saveDeviceToken();
  }

  @override
  Future<void> saveUserCredential(String code,String number, String password) {
    return authRepositoryInterface.saveUserCredential(code, number, password);
  }

  @override
  Future<bool?> saveUserToken(String token, String zoneId) {
    return authRepositoryInterface.saveUserToken(token, zoneId);
  }

  @override
  Future sendOtp({required String phone}) {
    return authRepositoryInterface.sendOtp(phone: phone);
  }

  @override
  void toggleNotificationSound(bool isNotification) {
    return authRepositoryInterface.toggleNotificationSound(isNotification);
  }

  @override
  Future updateToken() {
    return authRepositoryInterface.updateToken();
  }

  @override
  Future<void> updateZone(String zoneId) {
    return authRepositoryInterface.updateZone(zoneId);
  }

  @override
  Future verifyOtp({required String phone, required String otp}) {
    return authRepositoryInterface.verifyOtp(phone: phone, otp: otp);
  }

  @override
  Future verifyPhone(String phone, String otp) {
    return authRepositoryInterface.verifyPhone(phone, otp);
  }

  @override
  Future permanentDelete() {
    return authRepositoryInterface.permanentDelete();
  }

  @override
  Future saveRideCreatedTime(DateTime dateTime) async {
    authRepositoryInterface.saveRideCreatedTime(dateTime);
  }

  @override
  Future remainingTime() {
   return  authRepositoryInterface.remainingTime();
  }

  @override
  String getLoginCountryCode() {
   return  authRepositoryInterface.getLoginCountryCode();
  }

 @override
 Future verifyFirebaseOtp({required String phone, required String otp, required String session}) async{
   return await authRepositoryInterface.verifyFirebaseOtp(phone: phone, otp: otp, session: session);
 }

 @override
 Future isUserRegistered({required String phone}) async{
   return await authRepositoryInterface.isUserRegistered(phone: phone);
 }

}