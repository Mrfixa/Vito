import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/settings/domain/html_enum_types.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/auth/domain/enums/verification_from_enum.dart';
import 'package:ride_sharing_user_app/features/auth/screens/otp_log_in_screen.dart';
import 'package:ride_sharing_user_app/features/settings/screens/policy_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_text_field.dart';

class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key});

  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  TextEditingController usernameController = TextEditingController();
  TextEditingController pinController = TextEditingController();
  FocusNode usernameNode = FocusNode();
  FocusNode pinNode = FocusNode();

  @override
  void initState() {
    super.initState();

    if(Get.find<AuthController>().getUserNumber(false).isNotEmpty) {
      usernameController.text = Get.find<AuthController>().getUserNumber(false);
    }
    pinController.text = Get.find<AuthController>().getUserPassword(false);

    if(pinController.text.isNotEmpty) {
      Get.find<AuthController>().setRememberMe();
    }
  }

  @override
  void dispose() {
    usernameController.dispose();
    pinController.dispose();
    usernameNode.dispose();
    pinNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(child: Scaffold(
      backgroundColor: Theme.of(context).canvasColor,
      body: GetBuilder<AuthController>(builder: (authController) {
        return Center(child: SingleChildScrollView(child: Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Image.asset(Images.logoWithName, height: 75, width: 200)),
            const SizedBox(height: Dimensions.paddingSizeExtraLarge),

            Text(
              'ready_to_ride'.tr,
              style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty),
            ),
            const SizedBox(height: Dimensions.paddingSizeExtraSmall),

            Text(
              'enter_username_and_pin'.tr,
              style: textMedium.copyWith(color: Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7),fontSize: Dimensions.fontSizeSmall),
              maxLines: 2,
            ),
            const SizedBox(height: Dimensions.paddingSizeSignUp),

            CustomTextField(
              hintText: 'username'.tr,
              inputType: TextInputType.text,
              prefixIcon: Images.person,
              controller: usernameController,
              focusNode: usernameNode,
              nextFocus: pinNode,
              inputAction: TextInputAction.next,
              autoFocus: usernameController.text.isEmpty,
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),

            CustomTextField(
              hintText: 'enter_6_digit_pin'.tr,
              inputType: TextInputType.number,
              prefixIcon: Images.lock,
              inputAction: TextInputAction.done,
              isPassword: true,
              controller: pinController,
              focusNode: pinNode,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            ),

            Row(children: [
              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                child: InkWell(
                  onTap: () => authController.toggleRememberMe(),
                  child: Row(children: [
                    SizedBox(width: 20.0, child: Checkbox(
                      checkColor: Theme.of(context).primaryColor,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                      activeColor: Theme.of(context).primaryColor.withValues(alpha:.125),
                      value: authController.isActiveRememberMe,
                      side: BorderSide(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
                      onChanged: (bool? isChecked) => authController.toggleRememberMe(),
                    )),
                    const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                    Text(
                      'remember_me'.tr,
                      style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                    ),
                  ]),
                ),
              ),

              const Spacer(),
            ]),

            authController.isLoading ?
            Center(child: SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0)) :
            ButtonWidget(
              buttonText: 'log_in'.tr,
              onPressed: () {
                HapticFeedback.mediumImpact();
                String username = usernameController.text.trim();
                String pin = pinController.text.trim();

                if(username.isEmpty){
                  showCustomSnackBar('username_is_required'.tr);
                  FocusScope.of(context).requestFocus(usernameNode);
                }else if(username.length < 3) {
                  showCustomSnackBar('username_min_3_characters'.tr);
                  FocusScope.of(context).requestFocus(usernameNode);
                }else if(pin.isEmpty) {
                  showCustomSnackBar('pin_is_required'.tr);
                  FocusScope.of(context).requestFocus(pinNode);
                }else if(!RegExp(r'^\d{6}$').hasMatch(pin)) {
                  showCustomSnackBar('pin_must_be_6_digits'.tr);
                }else {
                  authController.login('', username, pin);
                }
              },
              radius: 50,
            ),

            const SizedBox(height: Dimensions.paddingSizeDefault),

            if(!(Get.find<ConfigController>().config?.externalSystem ?? false))...[
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text('${'do_not_have_an_account'.tr} ',
                    style: textMedium.copyWith(
                      fontSize: Dimensions.fontSizeSmall,
                      color: Theme.of(context).hintColor,
                    ),
                  ),

                  TextButton(
                    onPressed: () {
                      Get.to(() => OtpLoginScreen(from: VerificationForm.signUp));
                    },
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(50,30),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,

                    ),
                    child: Text('sign_up'.tr, style: textMedium.copyWith(
                      color: Theme.of(context).primaryColor,
                      fontSize: Dimensions.fontSizeSmall,
                      decoration: TextDecoration.underline,
                      decorationColor: Theme.of(context).primaryColor
                    )),
                  )
                ],
              ),
              SizedBox(height: Dimensions.paddingSizeLarge),
            ],

            InkWell(
              onTap: ()=> Get.to(() => PolicyScreen(htmlType: HtmlType.termsAndConditions, image: Get.find<ConfigController>().config?.termsAndConditions?.image??'',)),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                      "terms_and_condition".tr, style: textMedium.copyWith(
                      color: Theme.of(context).primaryColor,
                      fontSize: Dimensions.fontSizeSmall
                  )),
                ],
              ),
            ),
          ]),
        )));
      }),
    ));
  }
}
