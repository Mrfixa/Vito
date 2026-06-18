import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/auth/domain/enums/verification_from_enum.dart';
import 'package:ride_sharing_user_app/features/auth/screens/verification_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_text_field.dart';

class SignUpScreen extends StatefulWidget {
  const SignUpScreen({super.key});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  final FocusNode _fNameNode = FocusNode();
  final FocusNode _lNameNode = FocusNode();
  final FocusNode _phoneNode = FocusNode();
  final FocusNode _passwordNode = FocusNode();
  final FocusNode _confirmPasswordNode = FocusNode();
  final FocusNode _referralNode = FocusNode();

  @override
  void initState() {
    super.initState();
    final authController = Get.find<AuthController>();
    authController.fNameController.clear();
    authController.lNameController.clear();
    authController.phoneController.clear();
    authController.passwordController.clear();
    authController.confirmPasswordController.clear();
    authController.referralCodeController.clear();
    authController.countryDialCode = CountryCode.fromCountryCode(
      Get.find<ConfigController>().config!.countryCode!,
    ).dialCode!;
  }

  @override
  void dispose() {
    _fNameNode.dispose();
    _lNameNode.dispose();
    _phoneNode.dispose();
    _passwordNode.dispose();
    _confirmPasswordNode.dispose();
    _referralNode.dispose();
    super.dispose();
  }

  void _submit(AuthController authController) {
    HapticFeedback.mediumImpact();
    final fName = authController.fNameController.text.trim();
    final phone = authController.phoneController.text.trim();
    final password = authController.passwordController.text;
    final confirmPassword = authController.confirmPasswordController.text;

    if (fName.isEmpty) {
      showCustomSnackBar('first_name_is_required'.tr);
      FocusScope.of(context).requestFocus(_fNameNode);
    } else if (!GetUtils.isPhoneNumber(authController.countryDialCode + phone)) {
      showCustomSnackBar('phone_number_is_not_valid'.tr);
      FocusScope.of(context).requestFocus(_phoneNode);
    } else if (password.isEmpty) {
      showCustomSnackBar('password_is_required'.tr);
      FocusScope.of(context).requestFocus(_passwordNode);
    } else if (!RegExp(r'^\d{6}$').hasMatch(password)) {
      showCustomSnackBar('pin_must_be_6_digits'.tr);
      FocusScope.of(context).requestFocus(_passwordNode);
    } else if (confirmPassword.isEmpty) {
      showCustomSnackBar('confirm_password_is_required'.tr);
      FocusScope.of(context).requestFocus(_confirmPasswordNode);
    } else if (password != confirmPassword) {
      showCustomSnackBar('password_is_mismatch'.tr);
      FocusScope.of(context).requestFocus(_confirmPasswordNode);
    } else {
      final fullPhone = authController.countryDialCode + phone;
      authController.checkOAuth(countryCode: authController.countryDialCode, number: phone).then((value) {
        if (value.statusCode == 200 || value.statusCode == 404) {
          if (Get.find<ConfigController>().config?.isFirebaseOtpVerification ?? false) {
            authController.firebaseOtpSend(fullPhone, from: VerificationForm.verifyUser);
          } else if (Get.find<ConfigController>().config?.isSmsGateway ?? false) {
            authController.sendOtp(fullPhone).then((otpResp) {
              if (otpResp.statusCode == 200) {
                Get.to(() => VerificationScreen(
                  number: fullPhone,
                  form: VerificationForm.verifyUser,
                ));
              }
            });
          } else {
            showCustomSnackBar('sms_gateway_not_integrate'.tr);
          }
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Scaffold(
        backgroundColor: Theme.of(context).canvasColor,
        appBar: AppBar(
          backgroundColor: Theme.of(context).canvasColor,
          elevation: 0,
          leading: IconButton(
            icon: Icon(Icons.arrow_back, color: Theme.of(context).textTheme.bodyMedium?.color),
            onPressed: () => Get.back(),
          ),
        ),
        body: GetBuilder<AuthController>(builder: (authController) {
          return Center(
            child: SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Center(child: Image.asset(Images.logoWithName, height: 75, width: 200)),
                  const SizedBox(height: Dimensions.paddingSizeExtraLarge),

                  Text(
                    'create_your_account'.tr,
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                  Text(
                    'sign_up_message'.tr,
                    style: textMedium.copyWith(
                      color: Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                      fontSize: Dimensions.fontSizeSmall,
                    ),
                    maxLines: 2,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSignUp),

                  CustomTextField(
                    hintText: 'enter_your_first_name'.tr,
                    inputType: TextInputType.name,
                    prefixIcon: Images.person,
                    controller: authController.fNameController,
                    focusNode: _fNameNode,
                    nextFocus: _lNameNode,
                    inputAction: TextInputAction.next,
                    capitalization: TextCapitalization.words,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomTextField(
                    hintText: 'enter_your_last_name'.tr,
                    inputType: TextInputType.name,
                    prefixIcon: Images.person,
                    controller: authController.lNameController,
                    focusNode: _lNameNode,
                    nextFocus: _phoneNode,
                    inputAction: TextInputAction.next,
                    capitalization: TextCapitalization.words,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomTextField(
                    isCodePicker: true,
                    hintText: 'phone'.tr,
                    inputType: TextInputType.phone,
                    countryDialCode: authController.countryDialCode,
                    controller: authController.phoneController,
                    focusNode: _phoneNode,
                    nextFocus: _passwordNode,
                    inputAction: TextInputAction.next,
                    onCountryChanged: (CountryCode countryCode) {
                      authController.countryDialCode = countryCode.dialCode!;
                      authController.setCountryCode(countryCode.dialCode!);
                      FocusScope.of(context).requestFocus(_phoneNode);
                    },
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomTextField(
                    hintText: 'password'.tr,
                    inputType: TextInputType.text,
                    prefixIcon: Images.lock,
                    isPassword: true,
                    controller: authController.passwordController,
                    focusNode: _passwordNode,
                    nextFocus: _confirmPasswordNode,
                    inputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomTextField(
                    hintText: 'enter_confirm_password'.tr,
                    inputType: TextInputType.text,
                    prefixIcon: Images.lock,
                    isPassword: true,
                    controller: authController.confirmPasswordController,
                    focusNode: _confirmPasswordNode,
                    nextFocus: _referralNode,
                    inputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeSmall),

                  CustomTextField(
                    hintText: 'referral_code'.tr,
                    inputType: TextInputType.text,
                    prefixIcon: Images.referIcon,
                    controller: authController.referralCodeController,
                    focusNode: _referralNode,
                    inputAction: TextInputAction.done,
                  ),
                  const SizedBox(height: Dimensions.paddingSizeDefault),

                  authController.isLoading || authController.isOtpSending
                      ? Center(child: SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0))
                      : Semantics(
                          button: true,
                          label: 'sign_up'.tr,
                          child: ButtonWidget(
                            buttonText: 'sign_up'.tr,
                            onPressed: () => _submit(authController),
                            radius: 50,
                          ),
                        ),

                  const SizedBox(height: Dimensions.paddingSizeDefault),

                  Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(
                      '${'already_have_an_account'.tr} ',
                      style: textMedium.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).hintColor,
                      ),
                    ),
                    TextButton(
                      onPressed: () => Get.back(),
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        minimumSize: const Size(50, 30),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: Text(
                        'log_in'.tr,
                        style: textMedium.copyWith(
                          color: Theme.of(context).primaryColor,
                          fontSize: Dimensions.fontSizeSmall,
                          decoration: TextDecoration.underline,
                          decorationColor: Theme.of(context).primaryColor,
                        ),
                      ),
                    ),
                  ]),
                ]),
              ),
            ),
          );
        }),
      ),
    );
  }
}
