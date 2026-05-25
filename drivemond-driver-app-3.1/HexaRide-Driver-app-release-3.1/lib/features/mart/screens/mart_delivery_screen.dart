import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/chat/controllers/chat_controller.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

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
  bool _isLoading = true;

  Map<String, dynamic> _orderData = {};
  String? _deliveryPhotoPath;
  Uint8List? _signatureBytes;

  @override
  void initState() {
    super.initState();
    _fetchOrderDetails();
  }

  Future<void> _fetchOrderDetails() async {
    try {
      final response = await Get.find<ApiClient>().getData(
        '${AppConstants.martOrderDetails}${widget.orderId}',
      );
      if (response.statusCode == 200 && response.body['data'] != null) {
        setState(() {
          _orderData = Map<String, dynamic>.from(response.body['data']);
          _orderStatus = _orderData['status'] ?? 'accepted';
          _isLoading = false;
          _isOffline = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (_) {
      setState(() {
        _isOffline = true;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'delivery_details'.tr, regularAppbar: true),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor))
          : Column(
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
                        _buildCustomerInfo(context),
                        const SizedBox(height: Dimensions.paddingSizeDefault),
                        _buildDeliveryAddress(context),
                        const SizedBox(height: Dimensions.paddingSizeDefault),
                        _buildOrderItems(context),
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
                    '${'order'.tr} #${widget.orderId.length > 8 ? widget.orderId.substring(0, 8) : widget.orderId}',
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
            if (_orderData['total_amount'] != null)
              Text(
                '\$${_orderData['total_amount']}',
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Theme.of(context).primaryColor,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomerInfo(BuildContext context) {
    final customer = _orderData['customer'] as Map<String, dynamic>?;
    final phone = customer?['phone'] as String? ?? '';
    final customerId = (_orderData['customer_id'] as String?) ??
        (customer?['id'] as String?) ?? '';
    final customerName = '${customer?['first_name'] ?? ''} ${customer?['last_name'] ?? ''}'.trim().isNotEmpty
        ? '${customer?['first_name'] ?? ''} ${customer?['last_name'] ?? ''}'.trim()
        : customer?['name'] ?? 'customer'.tr;

    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Theme.of(context).primaryColor.withValues(alpha: 0.1),
          child: Icon(Icons.person, color: Theme.of(context).primaryColor),
        ),
        title: Text(customerName, style: textMedium),
        subtitle: phone.isNotEmpty ? Text(phone, style: textRegular) : null,
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (phone.isNotEmpty)
              IconButton(
                onPressed: () async {
                  HapticFeedback.mediumImpact();
                  final uri = Uri.parse('tel:$phone');
                  if (await canLaunchUrl(uri)) {
                    await launchUrl(uri);
                  }
                },
                icon: Icon(Icons.phone, color: Theme.of(context).primaryColor),
              ),
            if (customerId.isNotEmpty)
              IconButton(
                onPressed: () {
                  HapticFeedback.mediumImpact();
                  Get.find<ChatController>().createMartChannel(
                    customerId,
                    widget.orderId,
                    customerName,
                  );
                },
                icon: Icon(Icons.chat, color: Theme.of(context).primaryColor),
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
                    _orderData['delivery_address'] ?? 'loading_address'.tr,
                    style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
                  ),
                ),
              ],
            ),
            if (_orderData['notes'] != null && (_orderData['notes'] as String).isNotEmpty) ...[
              const SizedBox(height: Dimensions.paddingSizeSmall),
              Row(
                children: [
                  Icon(Icons.notes, color: Theme.of(context).hintColor, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _orderData['notes'],
                      style: textRegular.copyWith(
                        fontSize: Dimensions.fontSizeSmall,
                        color: Theme.of(context).hintColor,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildOrderItems(BuildContext context) {
    final items = _orderData['items'] as List<dynamic>? ?? [];
    if (items.isEmpty) return const SizedBox.shrink();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('order_items'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            ...items.map((item) {
              final product = item['product'] as Map<String, dynamic>? ?? item;
              final name = product['name'] ?? item['name'] ?? '';
              final price = item['unit_price'] ?? item['price'] ?? '0.00';
              final qty = item['quantity'] ?? 1;
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        '${qty}x $name',
                        style: textRegular.copyWith(fontSize: Dimensions.fontSizeDefault),
                      ),
                    ),
                    Text(
                      '\$$price',
                      style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                    ),
                  ],
                ),
              );
            }),
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
                  const Icon(Icons.check_circle, color: Colors.green, size: 20),
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
                            const Icon(Icons.check_circle, color: Colors.green, size: 40),
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
                  const Icon(Icons.check_circle, color: Colors.green, size: 20),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            GestureDetector(
              onTap: () async {
                HapticFeedback.mediumImpact();
                final picker = ImagePicker();
                final photo = await picker.pickImage(source: ImageSource.camera);
                if (photo != null) {
                  setState(() {
                    _hasDeliveryPhoto = true;
                    _deliveryPhotoPath = photo.path;
                  });
                }
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
                            const Icon(Icons.check_circle, color: Colors.green, size: 40),
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
        onPressed = _isOffline ? null : () => _updateStatusViaApi('picked_up');
        break;
      case 'picked_up':
        buttonText = 'mark_as_delivered'.tr;
        onPressed = (_isOffline || !_hasSignature || !_hasDeliveryPhoto)
            ? null
            : () => _markAsDelivered();
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

  Future<void> _updateStatusViaApi(String newStatus) async {
    HapticFeedback.heavyImpact();
    setState(() => _isUpdating = true);

    try {
      final response = await Get.find<ApiClient>().putData(
        AppConstants.martUpdateStatus,
        {
          'order_id': widget.orderId,
          'status': newStatus,
        },
      );

      if (response.statusCode == 200) {
        setState(() {
          _orderStatus = newStatus;
          _isUpdating = false;
        });
      } else {
        setState(() => _isUpdating = false);
        Get.snackbar('error'.tr, 'status_update_failed'.tr);
      }
    } catch (_) {
      setState(() => _isUpdating = false);
      Get.snackbar('error'.tr, 'network_error'.tr);
    }
  }

  Future<void> _markAsDelivered() async {
    HapticFeedback.heavyImpact();
    setState(() => _isUpdating = true);

    try {
      // Upload delivery proof (photo as file, signature as base64)
      final fields = {'order_id': widget.orderId};
      final multipartFiles = <MultipartBody>[];

      if (_deliveryPhotoPath != null && File(_deliveryPhotoPath!).existsSync()) {
        multipartFiles.add(MultipartBody('delivery_photo', XFile(_deliveryPhotoPath!)));
      }

      // Signature bytes are uploaded as a base64 field alongside the multipart
      final extraFields = <String, String>{...fields};
      if (_signatureBytes != null) {
        extraFields['signature_base64'] = base64Encode(_signatureBytes!);
      }

      final uploadResponse = await Get.find<ApiClient>().postMultipartData(
        AppConstants.martUploadProof,
        extraFields,
        multipartFiles,
        null,
        <MultipartDocument>[],
      );

      if (uploadResponse.statusCode != 200) {
        setState(() => _isUpdating = false);
        showCustomSnackBar('upload_failed_try_again'.tr);
        return;
      }

      final response = await Get.find<ApiClient>().putData(
        AppConstants.martUpdateStatus,
        {
          'order_id': widget.orderId,
          'status': 'delivered',
        },
      );

      if (response.statusCode == 200) {
        setState(() {
          _orderStatus = 'delivered';
          _isUpdating = false;
        });
        Get.back();
        Get.snackbar('success'.tr, 'delivery_completed'.tr);
      } else {
        setState(() => _isUpdating = false);
        Get.snackbar('error'.tr, 'delivery_failed'.tr);
      }
    } catch (_) {
      setState(() => _isUpdating = false);
      Get.snackbar('error'.tr, 'network_error'.tr);
    }
  }

  void _showSignatureCanvas(BuildContext context) {
    Get.dialog(
      SignatureDialog(
        onSave: (Uint8List bytes) {
          setState(() {
            _hasSignature = true;
            _signatureBytes = bytes;
          });
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
  final void Function(Uint8List bytes) onSave;

  const SignatureDialog({super.key, required this.onSave});

  @override
  State<SignatureDialog> createState() => _SignatureDialogState();
}

class _SignatureDialogState extends State<SignatureDialog> {
  final List<Offset?> _points = [];
  static const double _canvasWidth = 320;
  static const double _canvasHeight = 200;
  final GlobalKey _canvasKey = GlobalKey();

  Future<Uint8List> _renderToBytes() async {
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder, Rect.fromLTWH(0, 0, _canvasWidth, _canvasHeight));

    // White background
    canvas.drawRect(
      Rect.fromLTWH(0, 0, _canvasWidth, _canvasHeight),
      Paint()..color = Colors.white,
    );

    final paint = Paint()
      ..color = Colors.black
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 3.0
      ..style = PaintingStyle.stroke;

    for (int i = 0; i < _points.length - 1; i++) {
      if (_points[i] != null && _points[i + 1] != null) {
        canvas.drawLine(_points[i]!, _points[i + 1]!, paint);
      }
    }

    final picture = recorder.endRecording();
    final image = await picture.toImage(_canvasWidth.toInt(), _canvasHeight.toInt());
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

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
            key: _canvasKey,
            width: _canvasWidth,
            height: _canvasHeight,
            margin: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(color: Colors.grey.withValues(alpha: 0.3)),
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
            ),
            child: GestureDetector(
              onPanUpdate: (details) {
                final renderBox = _canvasKey.currentContext?.findRenderObject() as RenderBox?;
                if (renderBox == null) return;
                setState(() {
                  _points.add(renderBox.globalToLocal(details.globalPosition));
                });
              },
              onPanEnd: (_) {
                setState(() => _points.add(null));
              },
              child: CustomPaint(
                painter: _SignaturePainter(points: _points),
                size: const Size(_canvasWidth, _canvasHeight),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () => setState(() => _points.clear()),
                  child: Text('clear'.tr),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                TextButton(
                  onPressed: () => Get.back(),
                  child: Text('cancel'.tr),
                ),
                const SizedBox(width: Dimensions.paddingSizeSmall),
                ElevatedButton(
                  onPressed: _points.isEmpty
                      ? null
                      : () async {
                          HapticFeedback.mediumImpact();
                          final bytes = await _renderToBytes();
                          widget.onSave(bytes);
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
