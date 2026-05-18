import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/features/auth/screens/sign_in_screen.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class TokenGateScreen extends StatefulWidget {
  const TokenGateScreen({super.key});

  @override
  State<TokenGateScreen> createState() => _TokenGateScreenState();
}

class _TokenGateScreenState extends State<TokenGateScreen> {
  final TextEditingController _tokenController = TextEditingController();
  bool _isValidating = false;

  @override
  void dispose() {
    _tokenController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Scaffold(
        backgroundColor: Theme.of(context).canvasColor,
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Image.asset(Images.logoWithName, height: 75, width: 200),
                const SizedBox(height: Dimensions.paddingSizeExtraLarge),

                Icon(
                  Icons.qr_code_scanner_rounded,
                  size: 80,
                  color: Theme.of(context).primaryColor,
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),

                Text(
                  'invitation_required'.tr,
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),

                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Text(
                    'scan_qr_or_enter_token'.tr,
                    style: textRegular.copyWith(
                      color: Theme.of(context).hintColor,
                      fontSize: Dimensions.fontSizeSmall,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeExtraLarge),

                Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                    border: Border.all(
                      color: Theme.of(context).primaryColor.withValues(alpha: 0.3),
                    ),
                  ),
                  child: TextField(
                    controller: _tokenController,
                    maxLength: 64,
                    decoration: InputDecoration(
                      hintText: 'enter_invitation_token'.tr,
                      counterText: '',
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                      prefixIcon: Icon(Icons.vpn_key_outlined, color: Theme.of(context).primaryColor),
                    ),
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),

                _isValidating
                    ? CircularProgressIndicator(color: Theme.of(context).primaryColor)
                    : ButtonWidget(
                        buttonText: 'validate_token'.tr,
                        radius: 50,
                        onPressed: () {
                          HapticFeedback.mediumImpact();
                          _validateToken();
                        },
                      ),
                const SizedBox(height: Dimensions.paddingSizeDefault),

                TextButton(
                  onPressed: () => Get.to(() => const SignInScreen()),
                  child: Text(
                    'already_have_account'.tr,
                    style: textMedium.copyWith(
                      color: Theme.of(context).primaryColor,
                      decoration: TextDecoration.underline,
                      decorationColor: Theme.of(context).primaryColor,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _validateToken() {
    final token = _tokenController.text.trim();
    if (token.isEmpty) {
      showCustomSnackBar('token_is_required'.tr);
      return;
    }
    if (token.length < 10) {
      showCustomSnackBar('invalid_token_format'.tr);
      return;
    }

    setState(() => _isValidating = true);

    Future.delayed(const Duration(seconds: 1), () {
      setState(() => _isValidating = false);
      Get.to(() => const SignInScreen());
    });
  }
}
