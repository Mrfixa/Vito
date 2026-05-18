import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/screens/sign_up_screen.dart';
import 'package:ride_sharing_user_app/features/html/domain/html_enum_types.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/dashboard/controllers/bottom_menu_controller.dart';
import 'package:ride_sharing_user_app/features/html/screens/policy_viewer_screen.dart';
import 'package:ride_sharing_user_app/features/location/controllers/location_controller.dart';
import 'package:ride_sharing_user_app/features/profile/controllers/profile_controller.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/splash_controller.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/text_field_widget.dart';

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
    if(Get.find<AuthController>().getUserNumber().isNotEmpty){
      usernameController.text = Get.find<AuthController>().getUserNumber();
    }
    pinController.text = Get.find<AuthController>().getUserPassword();
    if(pinController.text != ''){
      Get.find<AuthController>().setRememberMe();
    }
    super.initState();
  }


  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (res, val) async {
        Get.find<BottomMenuController>().exitApp();
        return;
      },
      child: SafeArea(
        top: false,
        child: Scaffold(
          body: GetBuilder<AuthController>(builder: (authController){
            return GetBuilder<ProfileController>(builder: (profileController) {
              return GetBuilder<RideController>(builder: (rideController) {
                return GetBuilder<LocationController>(builder: (locationController) {
                  return Center(child: SingleChildScrollView(
                    child: Padding(
                      padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                      child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const SizedBox(height: Dimensions.paddingSizeSignUp),

                        Center(child: Image.asset( Images.logoWithName, height: 60)),
                        const SizedBox(height: Dimensions.paddingSizeSignUp),

                        Text(
                          'login'.tr,
                          style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty),
                        ),
                        const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                        Text(
                          'enter_username_and_pin'.tr,
                          style: textMedium.copyWith(color: Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7),fontSize: Dimensions.fontSizeSmall),
                          maxLines: 2,
                        ),
                        const SizedBox(height: Dimensions.paddingSizeSignUp),

                        TextFieldWidget(
                          hintText: 'username'.tr,
                          inputType: TextInputType.text,
                          prefixIcon: Images.person,
                          controller: usernameController,
                          focusNode: usernameNode,
                          autoFocus: usernameController.text.isEmpty,
                          inputAction: TextInputAction.next,
                        ),
                        const SizedBox(height: Dimensions.paddingSizeDefault),

                        TextFieldWidget(
                          hintText: 'enter_6_digit_pin'.tr,
                          inputType: TextInputType.number,
                          prefixIcon: Images.lock,
                          inputAction: TextInputAction.done,
                          focusNode: pinNode,
                          isPassword: true,
                          controller: pinController,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(6),
                          ],
                        ),

                        Row(children: [
                          InkWell(
                            onTap: () => authController.toggleRememberMe(),
                            child: Row(children: [
                              SizedBox(width: 20.0, child: Checkbox(
                                checkColor: Theme.of(context).primaryColor,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                                activeColor: Theme.of(context).primaryColor.withValues(alpha: .125),
                                value: authController.isActiveRememberMe,
                                side: BorderSide(color: Theme.of(context).hintColor.withValues(alpha: 0.5)),
                                onChanged: (bool? isChecked) => authController.toggleRememberMe(),
                              )),
                              const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                              Text(
                                'remember'.tr,
                                style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall),
                              ),
                            ]),
                          ),

                          const Spacer(),
                        ]),

                        const SizedBox(height: Dimensions.paddingSizeDefault),

                        (authController.isLoading || authController.updateFcm ||
                            profileController.isLoading || rideController.isLoading ||
                            locationController.lastLocationLoading) ?
                        Center(child: SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0)) :
                        ButtonWidget(
                          buttonText: 'login'.tr,
                          onPressed: (){
                            HapticFeedback.mediumImpact();
                            String username = usernameController.text.trim();
                            String pin = pinController.text.trim();
                            if(username.isEmpty){
                              showCustomSnackBar('username_is_required'.tr);
                              FocusScope.of(context).requestFocus(usernameNode);
                            }else if(username.length < 3){
                              showCustomSnackBar('username_min_3_characters'.tr);
                              FocusScope.of(context).requestFocus(usernameNode);
                            }else if(pin.isEmpty){
                              showCustomSnackBar('pin_is_required'.tr);
                              FocusScope.of(context).requestFocus(pinNode);
                            }else if(pin.length != 6){
                              showCustomSnackBar('pin_must_be_6_digits'.tr);
                              FocusScope.of(context).requestFocus(pinNode);
                            }else{
                              authController.login('', username, pin);
                            }
                          }, radius: 50,
                        ),

                        const SizedBox(height: Dimensions.paddingSizeDefault),

                        (Get.find<SplashController>().config!.selfRegistration != null &&
                            Get.find<SplashController>().config!.selfRegistration!) ?
                        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Text(
                            '${'do_not_have_an_account'.tr} ',
                            style: textMedium.copyWith(
                              fontSize: Dimensions.fontSizeSmall,
                              color: Theme.of(context).hintColor,
                            ),
                          ),

                          TextButton(
                            onPressed: () =>  Get.to(()=> const SignUpScreen()),
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero, minimumSize: const Size(50,30),
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                            child: Text(
                              'sign_up'.tr,
                              style: textMedium.copyWith(
                                decoration: TextDecoration.underline,
                                color: Theme.of(context).primaryColor,
                                decorationColor: Theme.of(context).primaryColor,
                              ),
                            ),
                          ),
                        ]) :
                        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Text("${'to_create_account'.tr} "),

                          InkWell(
                            onTap: ()=>
                                Get.find<SplashController>().sendMailOrCall(
                                  "tel:${Get.find<SplashController>().config?.businessContactPhone}",
                                  false,
                                ),
                            child: Text(
                              "${'contact_support'.tr} ",
                              style: textRegular.copyWith(
                                color: Theme.of(context).primaryColor,
                                decoration: TextDecoration.underline,
                              ),
                            ),
                          ),
                        ]),
                        SizedBox(height: Dimensions.paddingSizeSmall),

                        Center(
                          child: InkWell(
                            onTap: ()=> Get.to(()=> const PolicyViewerScreen(htmlType: HtmlType.termsAndConditions)),
                            child: Padding(
                              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                              child: Text(
                                "terms_and_condition".tr,
                                style: textMedium.copyWith(
                                  decoration: TextDecoration.underline,
                                  color: Theme.of(context).primaryColor,
                                  decorationColor: Theme.of(context).primaryColor,
                                ),
                              ),
                            ),
                          ),
                        ),

                      ]),
                    ),
                  ));
                });
              });
            });
          }),
        ),
      ),
    );
  }
}
