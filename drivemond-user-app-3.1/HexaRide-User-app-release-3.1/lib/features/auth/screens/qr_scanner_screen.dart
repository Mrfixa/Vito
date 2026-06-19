import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class QrScannerScreen extends StatefulWidget {
  final Function(String) onTokenScanned;

  const QrScannerScreen({super.key, required this.onTokenScanned});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  final MobileScannerController _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );
  bool _hasScanned = false;
  bool _isLoadingFromGallery = false;

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  Future<void> _pickFromGallery() async {
    final xFile = await ImagePicker().pickImage(source: ImageSource.gallery);
    if (xFile == null) return;
    setState(() => _isLoadingFromGallery = true);
    try {
      final capture = await _scannerController.analyzeImage(xFile.path);
      if (capture == null || capture.barcodes.isEmpty) {
        showCustomSnackBar('no_qr_found_in_image'.tr);
        return;
      }
      _onDetect(capture);
    } finally {
      if (mounted) setState(() => _isLoadingFromGallery = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Get.back(),
        ),
        title: Text('scan_qr_code'.tr, style: textBold.copyWith(color: Colors.white)),
        centerTitle: true,
        actions: [
          IconButton(
            icon: ValueListenableBuilder(
              valueListenable: _scannerController,
              builder: (context, state, child) {
                return Icon(
                  state.torchState == TorchState.on ? Icons.flash_on : Icons.flash_off,
                  color: Colors.white,
                );
              },
            ),
            onPressed: () => _scannerController.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _scannerController,
            onDetect: _onDetect,
          ),
          _buildScanOverlay(context),
          Positioned(
            bottom: 80,
            left: 0,
            right: 0,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: Dimensions.paddingSizeLarge,
                  vertical: Dimensions.paddingSizeSmall,
                ),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
                child: Text(
                  'point_camera_at_qr'.tr,
                  style: textRegular.copyWith(color: Colors.white, fontSize: Dimensions.fontSizeSmall),
                ),
              ),
            ),
          ),
          Positioned(
            bottom: 20,
            left: 0,
            right: 0,
            child: Center(
              child: TextButton.icon(
                onPressed: _isLoadingFromGallery ? null : _pickFromGallery,
                icon: _isLoadingFromGallery
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                      )
                    : const Icon(Icons.photo_library_outlined, color: Colors.white),
                label: Text('pick_from_gallery'.tr, style: textRegular.copyWith(color: Colors.white)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildScanOverlay(BuildContext context) {
    return ColorFiltered(
      colorFilter: const ColorFilter.mode(Colors.black54, BlendMode.srcOut),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Container(decoration: const BoxDecoration(color: Colors.black, backgroundBlendMode: BlendMode.dstOut)),
          Center(
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                color: Colors.red,
                borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _onDetect(BarcodeCapture capture) {
    if (_hasScanned) return;
    final barcode = capture.barcodes.firstOrNull;
    if (barcode == null || barcode.rawValue == null) return;

    final scannedValue = barcode.rawValue!;
    if (scannedValue.length < 10) return;

    setState(() => _hasScanned = true);
    HapticFeedback.heavyImpact();

    String token = scannedValue;
    if (scannedValue.contains('token=')) {
      final uri = Uri.tryParse(scannedValue);
      if (uri != null && uri.queryParameters.containsKey('token')) {
        final extracted = uri.queryParameters['token']!;
        if (extracted.isNotEmpty) token = extracted;
      }
    } else if (scannedValue.contains('/invite/')) {
      final parts = scannedValue.split('/invite/');
      if (parts.length > 1 && parts.last.isNotEmpty) {
        token = parts.last;
      }
    }

    Get.back();
    widget.onTokenScanned(token);
  }
}
