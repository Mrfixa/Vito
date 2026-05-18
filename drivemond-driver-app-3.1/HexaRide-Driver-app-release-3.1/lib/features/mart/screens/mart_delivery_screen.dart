import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';

class MartDeliveryScreen extends StatefulWidget {
  final String orderId;

  const MartDeliveryScreen({super.key, required this.orderId});

  @override
  State<MartDeliveryScreen> createState() => _MartDeliveryScreenState();
}

class _MartDeliveryScreenState extends State<MartDeliveryScreen> {
  String _orderStatus = 'accepted';
  bool _isUpdating = false;
  bool _hasSignature = false;
  bool _hasDeliveryPhoto = false;
  bool _isOffline = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'delivery_details'.tr, regularAppbar: true),
      body: Column(
        children: [
          if (_isOffline) _buildOfflineBanner(context),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildOrderStatusCard(context),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  _buildDeliveryAddress(context),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  if (_orderStatus == 'picked_up') ...[
                    _buildSignatureSection(context),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                    _buildPhotoSection(context),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                  ],
                  _buildActionButton(context),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOfflineBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
      color: Colors.orange,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.wifi_off, color: Colors.white, size: 16),
          const SizedBox(width: 8),
          Text(
            'you_are_offline'.tr,
            style: textMedium.copyWith(color: Colors.white),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderStatusCard(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
              child: Icon(Icons.local_shipping, color: Theme.of(context).primaryColor),
            ),
            const SizedBox(width: Dimensions.paddingSizeDefault),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${'order'.tr} #${widget.orderId.substring(0, 8)}',
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: _getStatusColor().withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    ),
                    child: Text(
                      _orderStatus.tr,
                      style: textMedium.copyWith(
                        color: _getStatusColor(),
                        fontSize: Dimensions.fontSizeSmall,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeliveryAddress(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('delivery_address'.tr, style: textBold.copyWith(
              fontSize: Dimensions.fontSizeDefault,
            )),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Row(
              children: [
                Icon(Icons.location_on, color: Theme.of(context).primaryColor, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'loading_address'.tr,
                    style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSignatureSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('customer_signature'.tr, style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeDefault,
                )),
                if (_hasSignature)
                  Icon(Icons.check_circle, color: Colors.green, size: 20),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            GestureDetector(
              onTap: () {
                HapticFeedback.mediumImpact();
                _showSignatureCanvas(context);
              },
              child: Container(
                width: double.infinity,
                height: 150,
                decoration: BoxDecoration(
                  border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                  color: Theme.of(context).hintColor.withValues(alpha: 0.05),
                ),
                child: _hasSignature
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.check_circle, color: Colors.green, size: 40),
                            const SizedBox(height: 8),
                            Text('signature_captured'.tr, style: textMedium.copyWith(
                              color: Colors.green,
                            )),
                          ],
                        ),
                      )
                    : Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.draw,
                              size: 40,
                              color: Theme.of(context).hintColor.withValues(alpha: 0.5),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'tap_to_capture_signature'.tr,
                              style: textRegular.copyWith(
                                color: Theme.of(context).hintColor,
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPhotoSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('delivery_photo'.tr, style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeDefault,
                )),
                if (_hasDeliveryPhoto)
                  Icon(Icons.check_circle, color: Colors.green, size: 20),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            GestureDetector(
              onTap: () {
                HapticFeedback.mediumImpact();
                setState(() => _hasDeliveryPhoto = true);
              },
              child: Container(
                width: double.infinity,
                height: 120,
                decoration: BoxDecoration(
                  border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                  color: Theme.of(context).hintColor.withValues(alpha: 0.05),
                ),
                child: _hasDeliveryPhoto
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.check_circle, color: Colors.green, size: 40),
                            const SizedBox(height: 8),
                            Text('photo_captured'.tr, style: textMedium.copyWith(
                              color: Colors.green,
                            )),
                          ],
                        ),
                      )
                    : Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.camera_alt,
                              size: 40,
                              color: Theme.of(context).hintColor.withValues(alpha: 0.5),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'tap_to_take_photo'.tr,
                              style: textRegular.copyWith(
                                color: Theme.of(context).hintColor,
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButton(BuildContext context) {
    String buttonText;
    VoidCallback? onPressed;

    switch (_orderStatus) {
      case 'accepted':
        buttonText = 'mark_as_picked_up'.tr;
        onPressed = _isOffline ? null : () => _updateStatus('picked_up');
        break;
      case 'picked_up':
        buttonText = 'mark_as_delivered'.tr;
        onPressed = (_isOffline || !_hasSignature || !_hasDeliveryPhoto) ? null : () => _updateStatus('delivered');
        break;
      default:
        buttonText = 'completed'.tr;
        onPressed = null;
    }

    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: _isUpdating ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: Theme.of(context).primaryColor,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          ),
        ),
        child: _isUpdating
            ? const SizedBox(
                width: 24, height: 24,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : Text(buttonText, style: textBold.copyWith(
                fontSize: Dimensions.fontSizeDefault,
                color: Colors.white,
              )),
      ),
    );
  }

  void _updateStatus(String newStatus) {
    HapticFeedback.heavyImpact();
    setState(() => _isUpdating = true);
    Future.delayed(const Duration(seconds: 1), () {
      if (mounted) {
        setState(() {
          _orderStatus = newStatus;
          _isUpdating = false;
        });
        if (newStatus == 'delivered') {
          Get.back();
          Get.snackbar('success'.tr, 'delivery_completed'.tr);
        }
      }
    });
  }

  void _showSignatureCanvas(BuildContext context) {
    Get.dialog(
      SignatureDialog(
        onSave: () {
          setState(() => _hasSignature = true);
        },
      ),
    );
  }

  Color _getStatusColor() {
    switch (_orderStatus) {
      case 'accepted':
        return Colors.blue;
      case 'picked_up':
        return Colors.indigo;
      case 'delivered':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }
}

class SignatureDialog extends StatefulWidget {
  final VoidCallback onSave;

  const SignatureDialog({super.key, required this.onSave});

  @override
  State<SignatureDialog> createState() => _SignatureDialogState();
}

class _SignatureDialogState extends State<SignatureDialog> {
  final List<Offset?> _points = [];

  @override
  Widget build(BuildContext context) {
    return Dialog(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Text('draw_signature'.tr, style: textBold.copyWith(
              fontSize: Dimensions.fontSizeLarge,
            )),
          ),
          Container(
            height: 200,
            margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
            decoration: BoxDecoration(
              border: Border.all(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            ),
            child: GestureDetector(
              onPanUpdate: (details) {
                setState(() {
                  final renderBox = context.findRenderObject() as RenderBox;
                  _points.add(renderBox.globalToLocal(details.globalPosition));
                });
              },
              onPanEnd: (_) {
                _points.add(null);
              },
              child: CustomPaint(
                painter: _SignaturePainter(points: _points),
                size: Size.infinite,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () {
                    setState(() => _points.clear());
                  },
                  child: Text('clear'.tr),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                TextButton(
                  onPressed: () => Get.back(),
                  child: Text('cancel'.tr),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                ElevatedButton(
                  onPressed: _points.isEmpty ? null : () {
                    HapticFeedback.mediumImpact();
                    widget.onSave();
                    Get.back();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).primaryColor,
                  ),
                  child: Text('save'.tr, style: const TextStyle(color: Colors.white)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SignaturePainter extends CustomPainter {
  final List<Offset?> points;

  _SignaturePainter({required this.points});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.black
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 3.0;

    for (int i = 0; i < points.length - 1; i++) {
      if (points[i] != null && points[i + 1] != null) {
        canvas.drawLine(points[i]!, points[i + 1]!, paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _SignaturePainter oldDelegate) => true;
}
