import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_order_tracking_screen.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class MartPaymentScreen extends StatefulWidget {
  final String orderId;
  final double totalAmount;

  const MartPaymentScreen({super.key, required this.orderId, required this.totalAmount});

  @override
  State<MartPaymentScreen> createState() => _MartPaymentScreenState();
}

class _MartPaymentScreenState extends State<MartPaymentScreen> {
  bool _isInitializing = true;
  bool _isPaying = false;
  String? _clientSecret;
  String? _initError;

  @override
  void initState() {
    super.initState();
    _initPaymentSheet();
  }

  Future<void> _initPaymentSheet() async {
    final isDark = mounted && Theme.of(context).brightness == Brightness.dark;
    setState(() {
      _isInitializing = true;
      _initError = null;
    });

    try {
      final response = await Get.find<ApiClient>().postData(
        AppConstants.martOrderPaymentIntent,
        {'order_id': widget.orderId},
      );

      if (response.statusCode != 200 || response.body['data'] == null) {
        setState(() {
          _initError = 'payment_failed'.tr;
          _isInitializing = false;
        });
        return;
      }

      final clientSecret = response.body['data']['client_secret'] as String?;
      if (clientSecret == null || clientSecret.isEmpty) {
        setState(() {
          _initError = 'payment_failed'.tr;
          _isInitializing = false;
        });
        return;
      }

      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: clientSecret,
          merchantDisplayName: AppConstants.appName,
          style: isDark ? ThemeMode.dark : ThemeMode.light,
        ),
      );

      setState(() {
        _clientSecret = clientSecret;
        _isInitializing = false;
      });
    } catch (_) {
      setState(() {
        _initError = 'payment_failed'.tr;
        _isInitializing = false;
      });
    }
  }

  Future<void> _presentPaymentSheet() async {
    if (_clientSecret == null) return;
    setState(() => _isPaying = true);
    try {
      HapticFeedback.mediumImpact();
      await Stripe.instance.presentPaymentSheet();
      showCustomSnackBar('payment_successful'.tr, isError: false);
      Get.off(() => MartOrderTrackingScreen(orderId: widget.orderId));
    } on StripeException catch (e) {
      if (e.error.code != FailureCode.Canceled) {
        showCustomSnackBar('payment_failed'.tr);
      }
    } catch (_) {
      showCustomSnackBar('payment_failed'.tr);
    } finally {
      if (mounted) setState(() => _isPaying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Scaffold(
        appBar: AppBar(
          title: Text('order_payment'.tr, style: textBold),
          backgroundColor: Theme.of(context).canvasColor,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => Get.back(),
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                ColorFiltered(
                  colorFilter: ColorFilter.mode(Theme.of(context).primaryColor, BlendMode.srcIn),
                  child: Image.asset(Images.logo, height: 64, width: 64),
                ),
                const SizedBox(height: Dimensions.paddingSizeLarge),

                Text(
                  'order_payment'.tr,
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeTwenty),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),

                Text(
                  '\$${widget.totalAmount.toStringAsFixed(2)}',
                  style: textBold.copyWith(
                    fontSize: 32,
                    color: Theme.of(context).primaryColor,
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeExtraLarge),

                if (_isInitializing)
                  Column(children: [
                    CircularProgressIndicator(color: Theme.of(context).primaryColor),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                    Text('processing_payment'.tr, style: textRegular.copyWith(color: Theme.of(context).hintColor)),
                  ])
                else if (_initError != null)
                  Column(children: [
                    Icon(Icons.error_outline, color: Theme.of(context).colorScheme.error, size: 48),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                    Text(_initError!, style: textMedium.copyWith(color: Theme.of(context).colorScheme.error)),
                    const SizedBox(height: Dimensions.paddingSizeLarge),
                    ButtonWidget(
                      buttonText: 'retry'.tr,
                      radius: 50,
                      onPressed: _initPaymentSheet,
                    ),
                  ])
                else
                  _isPaying
                      ? CircularProgressIndicator(color: Theme.of(context).primaryColor)
                      : ButtonWidget(
                          buttonText: 'pay_now'.tr,
                          radius: 50,
                          onPressed: _presentPaymentSheet,
                        ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
