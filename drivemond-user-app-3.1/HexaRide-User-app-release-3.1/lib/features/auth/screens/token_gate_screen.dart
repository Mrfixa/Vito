import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/auth/screens/qr_scanner_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/sign_in_screen.dart';
import 'package:ride_sharing_user_app/features/auth/screens/sign_up_screen.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
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
  List<Map<String, dynamic>> _tokenHistory = [];

  static const String _tokenHistoryKey = 'token_history';

  @override
  void initState() {
    super.initState();
    _loadTokenHistory();
  }

  @override
  void dispose() {
    _tokenController.dispose();
    super.dispose();
  }

  Future<void> _loadTokenHistory() async {
    final prefs = await SharedPreferences.getInstance();
    final historyJson = prefs.getString(_tokenHistoryKey);
    if (historyJson != null) {
      final List<dynamic> decoded = jsonDecode(historyJson);
      setState(() {
        _tokenHistory = decoded.cast<Map<String, dynamic>>();
      });
    }
  }

  Future<void> _saveTokenToHistory(String token, bool isValid) async {
    final prefs = await SharedPreferences.getInstance();
    final entry = {
      'token': token.length > 12 ? '${token.substring(0, 6)}...${token.substring(token.length - 6)}' : token,
      'fullToken': token,
      'timestamp': DateTime.now().toIso8601String(),
      'valid': isValid,
    };
    _tokenHistory.insert(0, entry);
    if (_tokenHistory.length > 20) {
      _tokenHistory = _tokenHistory.sublist(0, 20);
    }
    await prefs.setString(_tokenHistoryKey, jsonEncode(_tokenHistory));
    setState(() {});
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

                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      HapticFeedback.mediumImpact();
                      Get.to(() => QrScannerScreen(
                        onTokenScanned: (token) {
                          _tokenController.text = token;
                          _validateToken();
                        },
                      ));
                    },
                    icon: const Icon(Icons.qr_code_scanner, color: Colors.white),
                    label: Text('scan_qr_code'.tr, style: textBold.copyWith(color: Colors.white)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Theme.of(context).primaryColor,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),

                Row(
                  children: [
                    Expanded(child: Divider(color: Theme.of(context).hintColor.withValues(alpha: 0.3))),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                      child: Text('or'.tr, style: textRegular.copyWith(color: Theme.of(context).hintColor)),
                    ),
                    Expanded(child: Divider(color: Theme.of(context).hintColor.withValues(alpha: 0.3))),
                  ],
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),

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

                if (_tokenHistory.isNotEmpty) ...[
                  const SizedBox(height: Dimensions.paddingSizeLarge),
                  _buildTokenHistory(context),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildTokenHistory(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('token_history'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            TextButton(
              onPressed: () async {
                final prefs = await SharedPreferences.getInstance();
                await prefs.remove(_tokenHistoryKey);
                setState(() => _tokenHistory.clear());
              },
              child: Text('clear'.tr, style: textRegular.copyWith(
                color: Theme.of(context).hintColor,
                fontSize: Dimensions.fontSizeSmall,
              )),
            ),
          ],
        ),
        ...(_tokenHistory.take(5).map((entry) => ListTile(
          dense: true,
          contentPadding: EdgeInsets.zero,
          leading: Icon(
            entry['valid'] == true ? Icons.check_circle : Icons.cancel,
            color: entry['valid'] == true ? Colors.green : Colors.red,
            size: 20,
          ),
          title: Text(entry['token'] ?? '', style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
          subtitle: Text(
            _formatTimestamp(entry['timestamp'] ?? ''),
            style: textRegular.copyWith(fontSize: 10, color: Theme.of(context).hintColor),
          ),
          onTap: () {
            _tokenController.text = entry['fullToken'] ?? '';
          },
        ))),
      ],
    );
  }

  String _formatTimestamp(String isoString) {
    if (isoString.isEmpty) return '';
    final dt = DateTime.tryParse(isoString);
    if (dt == null) return '';
    return '${dt.day}/${dt.month}/${dt.year} ${dt.hour}:${dt.minute.toString().padLeft(2, '0')}';
  }

  Future<void> _validateToken() async {
    final token = _tokenController.text.trim();
    if (token.isEmpty) {
      showCustomSnackBar('token_is_required'.tr);
      return;
    }
    if (token.length != 64) {
      showCustomSnackBar('invalid_token_length'.tr);
      return;
    }

    setState(() => _isValidating = true);

    try {
      // Public, unauthenticated GET — the user is not logged in yet.
      final response = await Get.find<ApiClient>().getData(
        AppConstants.qrTokenValidatePublic + token,
      );

      if (response.statusCode == 200 && response.body['data']?['valid'] == true) {
        await _saveTokenToHistory(token, true);
        Get.off(() => SignUpScreen(qrToken: token));
      } else {
        await _saveTokenToHistory(token, false);
        showCustomSnackBar('invalid_or_expired_token'.tr);
      }
    } catch (e) {
      debugPrint('Token validation error: $e');
      await _saveTokenToHistory(token, false);
      showCustomSnackBar('token_validation_failed'.tr);
    } finally {
      if (mounted) setState(() => _isValidating = false);
    }
  }
}
