import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/vito_pin_field.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/auth/widgets/text_field_title_widget.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';

class ChangePinScreen extends StatefulWidget {
  const ChangePinScreen({super.key});

  @override
  State<ChangePinScreen> createState() => _ChangePinScreenState();
}

class _ChangePinScreenState extends State<ChangePinScreen> {
  final TextEditingController _currentPinController = TextEditingController();
  final TextEditingController _newPinController = TextEditingController();
  final TextEditingController _confirmPinController = TextEditingController();
  final FocusNode _currentPinNode = FocusNode();
  final FocusNode _newPinNode = FocusNode();
  final FocusNode _confirmPinNode = FocusNode();

  @override
  void dispose() {
    _currentPinController.dispose();
    _newPinController.dispose();
    _confirmPinController.dispose();
    _currentPinNode.dispose();
    _newPinNode.dispose();
    _confirmPinNode.dispose();
    super.dispose();
  }

  bool _isSixDigits(String value) => RegExp(r'^\d{6}$').hasMatch(value);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'change_pin'.tr, regularAppbar: true),
      body: SafeArea(
        child: GetBuilder<AuthController>(builder: (authController) {
          return Column(children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  TextFieldTitleWidget(title: 'current_pin'.tr, isRequired: true),
                  VitoPinField(
                    controller: _currentPinController,
                    focusNode: _currentPinNode,
                    autoFocus: true,
                  ),

                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  TextFieldTitleWidget(title: 'new_pin'.tr, isRequired: true),
                  VitoPinField(
                    controller: _newPinController,
                    focusNode: _newPinNode,
                  ),

                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  TextFieldTitleWidget(title: 'confirm_new_pin'.tr, isRequired: true),
                  VitoPinField(
                    controller: _confirmPinController,
                    focusNode: _confirmPinNode,
                  ),
                ]),
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              child: authController.isLoading
                  ? Center(child: SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0))
                  : ButtonWidget(
                      radius: Dimensions.radiusExtraLarge,
                      buttonText: 'change_pin'.tr,
                      onPressed: () {
                        final String currentPin = _currentPinController.text.trim();
                        final String newPin = _newPinController.text.trim();
                        final String confirmPin = _confirmPinController.text.trim();

                        if (currentPin.isEmpty) {
                          showCustomSnackBar('current_pin_is_required'.tr);
                          FocusScope.of(context).requestFocus(_currentPinNode);
                        } else if (!_isSixDigits(currentPin)) {
                          showCustomSnackBar('pin_must_be_6_digits'.tr);
                          FocusScope.of(context).requestFocus(_currentPinNode);
                        } else if (newPin.isEmpty) {
                          showCustomSnackBar('new_pin_is_required'.tr);
                          FocusScope.of(context).requestFocus(_newPinNode);
                        } else if (!_isSixDigits(newPin)) {
                          showCustomSnackBar('pin_must_be_6_digits'.tr);
                          FocusScope.of(context).requestFocus(_newPinNode);
                        } else if (newPin != confirmPin) {
                          showCustomSnackBar('pins_do_not_match'.tr);
                          FocusScope.of(context).requestFocus(_confirmPinNode);
                        } else {
                          authController.changePin(currentPin, newPin);
                        }
                      },
                    ),
            ),
          ]);
        }),
      ),
    );
  }
}
