import 'package:country_code_picker/country_code_picker.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/auth/domain/enums/verification_from_enum.dart';
import 'package:ride_sharing_user_app/features/auth/screens/otp_log_in_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/forgot_password_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/token_gate_screen.dart';
import 'package:ride_sharing_user_app/features/settings/domain/html_enum_types.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/settings/screens/policy_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_text_field.dart';
import 'package:url_launcher/url_launcher.dart';

class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key});

  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  TextEditingController passwordController = TextEditingController();
  TextEditingController phoneController = TextEditingController();
  FocusNode phoneNode = FocusNode();
  FocusNode passwordNode = FocusNode();

  @override
  void initState() {
    super.initState();

    if (Get.find<AuthController>().getUserNumber(false).isNotEmpty) {
      phoneController.text = Get.find<AuthController>().getUserNumber(false);
    }

    Get.find<AuthController>().getUserPassword(false).then((pwd) {
      if (mounted && pwd.isNotEmpty) {
        passwordController.text = pwd;
        Get.find<AuthController>().setRememberMe();
      }
    });

    if (Get.find<AuthController>().getLoginCountryCode(false).isNotEmpty) {
      Get.find<AuthController>().countryDialCode = Get.find<AuthController>().getLoginCountryCode(false);
    } else if (Get.find<ConfigController>().config!.countryCode != null) {
      Get.find<AuthController>().countryDialCode =
          CountryCode.fromCountryCode(Get.find<ConfigController>().config!.countryCode!).dialCode!;
    }
  }

  @override
  void dispose() {
    passwordController.dispose();
    phoneController.dispose();
    phoneNode.dispose();
    passwordNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Scaffold(
        body: GetBuilder<AuthController>(builder: (authController) {
          return Column(children: [
            Container(
              width: double.infinity,
              height: MediaQuery.of(context).size.height * 0.32,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Theme.of(context).primaryColor,
                    Theme.of(context).primaryColor.withValues(alpha: 0.75),
                  ],
                ),
              ),
              child: SafeArea(
                bottom: false,
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  ColorFiltered(
                    colorFilter: const ColorFilter.mode(Colors.white, BlendMode.srcIn),
                    child: Image.asset(Images.logo, height: 60, width: 60),
                  ),
                  const SizedBox(height: 10),
                  Text(AppConstants.appName, style: textBold.copyWith(fontSize: 24, color: Colors.white)),
                  const SizedBox(height: 4),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeLarge),
                    child: Text(
                      'log_in_message'.tr,
                      style: textRegular.copyWith(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: Dimensions.fontSizeSmall,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ]),
              ),
            ),
            Expanded(
              child: Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Theme.of(context).canvasColor,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(28),
                    topRight: Radius.circular(28),
                  ),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(
                    Dimensions.paddingSizeLarge,
                    Dimensions.paddingSizeExtraLarge,
                    Dimensions.paddingSizeLarge,
                    Dimensions.paddingSizeLarge,
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('ready_to_ride'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty)),
                    const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                    Text(
                      'log_in_message'.tr,
                      style: textMedium.copyWith(
                        color: Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                        fontSize: Dimensions.fontSizeSmall,
                      ),
                      maxLines: 2,
                    ),
                    const SizedBox(height: Dimensions.paddingSizeSignUp),

                    CustomTextField(
                      isCodePicker: true,
                      hintText: 'phone'.tr,
                      inputType: TextInputType.phone,
                      countryDialCode: authController.countryDialCode,
                      controller: phoneController,
                      focusNode: phoneNode,
                      nextFocus: passwordNode,
                      inputAction: TextInputAction.next,
                      onCountryChanged: (CountryCode countryCode) {
                        authController.countryDialCode = countryCode.dialCode!;
                        authController.setCountryCode(countryCode.dialCode!);
                        FocusScope.of(context).requestFocus(phoneNode);
                      },
                      autoFocus: phoneController.text.isEmpty,
                    ),
                    const SizedBox(height: Dimensions.paddingSizeSmall),

                    CustomTextField(
                      hintText: 'enter_password'.tr,
                      inputType: TextInputType.text,
                      prefixIcon: Images.lock,
                      inputAction: TextInputAction.done,
                      isPassword: true,
                      controller: passwordController,
                      focusNode: passwordNode,
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
                              activeColor: Theme.of(context).primaryColor.withValues(alpha: .125),
                              value: authController.isActiveRememberMe,
                              side: BorderSide(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
                              onChanged: (bool? isChecked) => authController.toggleRememberMe(),
                            )),
                            const SizedBox(width: Dimensions.paddingSizeExtraSmall),
                            Text('remember_me'.tr, style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
                          ]),
                        ),
                      ),
                      const Spacer(),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () {
                            Get.to(() => const ForgotPasswordScreen(from: VerificationForm.resetPassword));
                          },
                          child: Text('forgot_password'.tr, style: textRegular.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).primaryColor,
                          )),
                        ),
                      ),
                    ]),

                    authController.isLoading ?
                    Center(child: SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0)) :
                    ButtonWidget(
                      buttonText: 'log_in'.tr,
                      onPressed: () {
                        HapticFeedback.mediumImpact();
                        String phone = phoneController.text.trim();
                        String password = passwordController.text.trim();
                        if (phone.isEmpty) {
                          showCustomSnackBar('phone_number_is_required'.tr);
                          FocusScope.of(context).requestFocus(phoneNode);
                        } else if (!GetUtils.isPhoneNumber(authController.countryDialCode + phone)) {
                          showCustomSnackBar('phone_number_is_not_valid'.tr);
                          FocusScope.of(context).requestFocus(phoneNode);
                        } else if (password.isEmpty) {
                          showCustomSnackBar('password_is_required'.tr);
                          FocusScope.of(context).requestFocus(passwordNode);
                        } else if (!RegExp(r'^\d{6}$').hasMatch(password)) {
                          showCustomSnackBar('pin_must_be_6_digits'.tr);
                        } else {
                          authController.login(authController.countryDialCode, phone, password);
                        }
                      },
                      radius: 50,
                    ),

                    if ((Get.find<ConfigController>().config?.isSmsGateway ?? false) ||
                        (Get.find<ConfigController>().config?.isFirebaseOtpVerification ?? false)) ...[
                      Center(child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Dimensions.paddingSizeSmall,
                          vertical: 8,
                        ),
                        child: Text('or'.tr, style: textRegular.copyWith(color: Theme.of(context).hintColor)),
                      )),
                      ButtonWidget(
                        showBorder: true,
                        borderWidth: 1,
                        transparent: true,
                        buttonText: 'login_with_otp'.tr,
                        onPressed: () => Get.to(() => const OtpLoginScreen(from: VerificationForm.login)),
                        radius: 50,
                      ),
                    ],

                    const SizedBox(height: Dimensions.paddingSizeDefault),

                    if (!(Get.find<ConfigController>().config?.externalSystem ?? false)) ...[
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            '${'do_not_have_an_account'.tr} ',
                            style: textMedium.copyWith(
                              fontSize: Dimensions.fontSizeSmall,
                              color: Theme.of(context).hintColor,
                            ),
                          ),
                          TextButton(
                            onPressed: () {
                              Get.to(() => const TokenGateScreen());
                            },
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              minimumSize: const Size(50, 30),
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                            child: Text('sign_up'.tr, style: textMedium.copyWith(
                              color: Theme.of(context).primaryColor,
                              fontSize: Dimensions.fontSizeSmall,
                              decoration: TextDecoration.underline,
                              decorationColor: Theme.of(context).primaryColor,
                            )),
                          ),
                        ],
                      ),
                      const SizedBox(height: Dimensions.paddingSizeLarge),
                    ],

                    InkWell(
                      onTap: () => Get.to(() => PolicyScreen(
                        htmlType: HtmlType.termsAndConditions,
                        image: Get.find<ConfigController>().config?.termsAndConditions?.image ?? '',
                      )),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text("terms_and_condition".tr, style: textMedium.copyWith(
                            color: Theme.of(context).primaryColor,
                            fontSize: Dimensions.fontSizeSmall,
                          )),
                        ],
                      ),
                    ),
                  ]),
                ),
              ),
            ),
          ]);
        }),
        bottomNavigationBar: GetBuilder<AuthController>(builder: (authController) {
          return ((Get.find<ConfigController>().config?.externalSystem ?? false) && authController.showNavigationBar) ?
          Container(
            padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
            decoration: BoxDecoration(
              color: Theme.of(Get.context!).textTheme.titleMedium!.color!,
            ),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Row(children: [
                Padding(
                  padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                  child: Icon(Icons.info, size: 20, color: Theme.of(context).cardColor),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                Expanded(child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('this_is_not_an_independent_app'.tr,
                        style: textRegular.copyWith(color: Theme.of(context).cardColor)),
                    const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                    RichText(text: TextSpan(
                      text: 'this_app_is_connected_with_6ammart'.tr,
                      style: textRegular.copyWith(
                        color: Theme.of(context).cardColor.withValues(alpha: 0.7),
                        fontSize: Dimensions.fontSizeExtraSmall,
                      ),
                      children: [
                        TextSpan(
                          text: ' ${'click_here_to_sigh_up'.tr}',
                          style: textRegular.copyWith(
                            color: Theme.of(context).colorScheme.surfaceContainer,
                            fontSize: Dimensions.fontSizeExtraSmall,
                            decoration: TextDecoration.underline,
                          ),
                          recognizer: TapGestureRecognizer()..onTap = () async {
                            navigateToMart('sixammart://open?country_code=&phone=signUp&password=}');
                          },
                        ),
                        TextSpan(
                          text: '  ${'or'.tr}  ',
                          style: textRegular.copyWith(
                            color: Theme.of(context).cardColor,
                            fontSize: Dimensions.fontSizeExtraSmall,
                          ),
                        ),
                        TextSpan(
                          text: 'download_mart'.tr,
                          style: textRegular.copyWith(
                            color: Theme.of(context).colorScheme.surfaceContainer,
                            fontSize: Dimensions.fontSizeExtraSmall,
                            decoration: TextDecoration.underline,
                          ),
                          recognizer: TapGestureRecognizer()..onTap = () async {
                            if (GetPlatform.isAndroid && Get.find<ConfigController>().config?.martPlayStoreUrl != null) {
                              navigateToMart(Get.find<ConfigController>().config!.martPlayStoreUrl!);
                            } else if (GetPlatform.isIOS && Get.find<ConfigController>().config?.martAppStoreUrl != null) {
                              navigateToMart(Get.find<ConfigController>().config!.martAppStoreUrl!);
                            } else {
                              showCustomSnackBar('contact_with_support'.tr);
                            }
                          },
                        ),
                      ],
                    )),
                  ])),
                  InkWell(
                    onTap: () => authController.toggleNavigationBar(),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall),
                      child: Icon(Icons.clear, color: Theme.of(context).cardColor),
                    ),
                  ),
                ])),
              ]),
            ]),
          ) :
          const SizedBox();
        }),
      ),
    );
  }

  void navigateToMart(String url) async {
    if (GetPlatform.isAndroid) {
      try {
        await launchUrl(Uri.parse(url));
      } catch (exception) {
        navigateToStores(url);
      }
    } else if (GetPlatform.isIOS) {
      if (await launchUrl(Uri.parse(url))) {} else {
        navigateToStores(url);
      }
    }
  }

  void navigateToStores(String url) async {
    if (GetPlatform.isAndroid && Get.find<ConfigController>().config?.martPlayStoreUrl != null) {
      await launchUrl(Uri.parse(Get.find<ConfigController>().config!.martPlayStoreUrl!));
    } else if (GetPlatform.isIOS && Get.find<ConfigController>().config?.martAppStoreUrl != null) {
      await launchUrl(Uri.parse(Get.find<ConfigController>().config!.martAppStoreUrl!));
    } else {
      showCustomSnackBar('contact_with_support'.tr);
    }
  }
}
