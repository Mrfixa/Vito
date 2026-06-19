import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/message/controllers/message_controller.dart';
import 'package:ride_sharing_user_app/features/payment/controllers/payment_controller.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:url_launcher/url_launcher.dart';

class MartOrderTrackingScreen extends StatefulWidget {
  final String orderId;

  const MartOrderTrackingScreen({super.key, required this.orderId});

  @override
  State<MartOrderTrackingScreen> createState() => _MartOrderTrackingScreenState();
}

class _MartOrderTrackingScreenState extends State<MartOrderTrackingScreen> {
  String _currentStatus = 'pending';
  bool _isOffline = false;
  bool _isLoading = true;
  bool _hasPromptedRating = false;
  Timer? _pollTimer;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;
  int _pollCount = 0;
  static const int _maxPollCount = 240;

  Map<String, dynamic> _driverInfo = {};
  Map<String, dynamic> _orderData = {};
  String _estimatedArrival = '';
  String _driverId = '';
  String _driverName = '';

  // B30: driver location
  double? _driverLat;
  double? _driverLng;

  // B29: richer status timeline matching actual backend statuses
  List<Map<String, dynamic>> get _statusSteps => [
        {'key': 'pending',   'label': 'order_placed'.tr,    'icon': Icons.receipt_outlined},
        {'key': 'accepted',  'label': 'order_confirmed'.tr, 'icon': Icons.check_circle_outline},
        {'key': 'picked_up', 'label': 'out_for_delivery'.tr,'icon': Icons.delivery_dining_outlined},
        {'key': 'delivered', 'label': 'delivered'.tr,       'icon': Icons.home_outlined},
      ];

  // B29: map status → step index; cancelled returns -1
  int get _currentStepIndex {
    switch (_currentStatus) {
      case 'pending':
        return 0;
      case 'accepted':
        return 1;
      case 'picked_up':
        return 2;
      case 'delivered':
        return 3;
      case 'cancelled':
        return -1;
      default:
        return 0;
    }
  }

  @override
  void initState() {
    super.initState();
    _fetchOrderStatus();
    _startPolling();
    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) {
      if (!results.contains(ConnectivityResult.none) && _isOffline) {
        if (mounted) setState(() => _isOffline = false);
        _startPolling();
      }
    });
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      _pollCount++;
      if (_pollCount >= _maxPollCount) {
        _pollTimer?.cancel();
        return;
      }
      _fetchOrderStatus();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _connectivitySub?.cancel();
    super.dispose();
  }

  Future<void> _fetchOrderStatus() async {
    try {
      final response = await Get.find<ApiClient>().getData(
        '${AppConstants.martOrderDetails}${widget.orderId}',
      );
      if (response.statusCode == 200 && response.body['data'] != null) {
        final data = response.body['data'];
        setState(() {
          _currentStatus = data['status'] ?? 'pending';
          _orderData = Map<String, dynamic>.from(data);
          if (data['driver'] != null) {
            _driverInfo = Map<String, dynamic>.from(data['driver']);
            _driverId = data['driver_id'] ?? data['driver']?['id'] ?? '';
            _driverName =
                '${data['driver']?['first_name'] ?? ''} ${data['driver']?['last_name'] ?? ''}'
                    .trim();
          } else {
            _driverInfo = {};
            _driverId = '';
            _driverName = '';
          }
          _estimatedArrival = data['estimated_arrival'] ?? '';
          // B30: parse driver location
          _driverLat = double.tryParse(data['driver_lat']?.toString() ?? '');
          _driverLng = double.tryParse(data['driver_lng']?.toString() ?? '');
          _isLoading = false;
          _isOffline = false;
        });
        if (_currentStatus == 'delivered' || _currentStatus == 'cancelled') {
          _pollTimer?.cancel();
        }
        if (_currentStatus == 'delivered' && !_hasPromptedRating) {
          _hasPromptedRating = true;
          Future.delayed(const Duration(milliseconds: 500), () {
            if (mounted) _showRatingBottomSheet();
          });
        }
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Mart tracking error: $e');
      // Stop the 15s poll while offline so we don't drain battery/data;
      // a manual refresh restarts it.
      _pollTimer?.cancel();
      if (!mounted) return;
      setState(() {
        _isOffline = true;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'order_tracking'.tr),
      body: _isLoading
          ? _buildLoadingSkeleton(context)
          : Column(
              children: [
                if (_isOffline) _buildOfflineBanner(context),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildOrderHeader(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        _buildStatusTimeline(context),
                        // B30: driver location map when picked_up
                        _buildDriverMap(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        _buildDriverInfo(context),
                        // B31: delivery photo + signature
                        _buildDeliveryProof(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        if (_currentStatus != 'delivered' &&
                            _currentStatus != 'cancelled')
                          _buildCancelButton(context),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildLoadingSkeleton(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final base = isDark ? const Color(0xFF303030) : const Color(0xFFE0E0E0);
    final highlight = isDark ? const Color(0xFF404040) : const Color(0xFFF5F5F5);
    return Shimmer.fromColors(
      baseColor: base,
      highlightColor: highlight,
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _shimmerBox(context, height: 90),
            const SizedBox(height: Dimensions.paddingSizeLarge),
            _shimmerBox(context, height: 140),
            const SizedBox(height: Dimensions.paddingSizeLarge),
            _shimmerBox(context, height: 80),
            const SizedBox(height: Dimensions.paddingSizeLarge),
            _shimmerBox(context, height: 100),
          ],
        ),
      ),
    );
  }

  Widget _shimmerBox(BuildContext context, {required double height}) {
    return Container(
      width: double.infinity,
      height: height,
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      ),
    );
  }

  Widget _buildOfflineBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
      color: AppColors.offlineWarning,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.wifi_off, color: Colors.white, size: Dimensions.iconSizeMedium),
          const SizedBox(width: Dimensions.paddingSizeExtraSmall),
          Text('you_are_offline'.tr, style: textMedium.copyWith(color: Colors.white)),
        ],
      ),
    );
  }

  Widget _buildOrderHeader(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: _getStatusColor(context).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
              child: Icon(Icons.receipt_long, color: _getStatusColor(context)),
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
                  const SizedBox(height: Dimensions.paddingSizeThree),
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 300),
                    child: Container(
                      key: ValueKey(_currentStatus),
                      padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeExtraSmall, vertical: Dimensions.paddingSizeThree),
                      decoration: BoxDecoration(
                        color: _getStatusColor(context).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                      ),
                      child: Text(
                        _currentStatus.tr,
                        style: textMedium.copyWith(
                          color: _getStatusColor(context),
                          fontSize: Dimensions.fontSizeSmall,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (_estimatedArrival.isNotEmpty)
              Column(
                children: [
                  const Icon(Icons.access_time, size: 16),
                  const SizedBox(height: 4),
                  Text(
                    _estimatedArrival,
                    style: textMedium.copyWith(fontSize: Dimensions.fontSizeSmall),
                  ),
                  Text('eta'.tr,
                      style: textRegular.copyWith(
                        fontSize: 10,
                        color: Theme.of(context).hintColor,
                      )),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCancelledCard(BuildContext context) {
    return Card(
      color: Theme.of(context).colorScheme.error.withValues(alpha: 0.08),
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.error.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
              child: Icon(Icons.cancel_outlined,
                  color: Theme.of(context).colorScheme.error, size: 28),
            ),
            const SizedBox(width: Dimensions.paddingSizeDefault),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'order_cancelled_title'.tr,
                    style: textBold.copyWith(
                        color: Theme.of(context).colorScheme.error),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'order_cancelled'.tr,
                    style: textRegular.copyWith(
                      color: Theme.of(context).hintColor,
                      fontSize: Dimensions.fontSizeSmall,
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

  // B29: richer status timeline
  Widget _buildStatusTimeline(BuildContext context) {
    if (_currentStepIndex == -1) {
      return _buildCancelledCard(context);
    }
    final steps = _statusSteps;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('delivery_status'.tr,
                style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            ...List.generate(steps.length, (index) {
              final step = steps[index];
              final stepIndex = _currentStepIndex;
              final isCompleted = stepIndex >= 0 && index <= stepIndex;
              final isActive = index == stepIndex;

              return Column(
                children: [
                  Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: isCompleted
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).hintColor.withValues(alpha: 0.2),
                        ),
                        child: Icon(
                          step['icon'] as IconData,
                          color: isCompleted ? Theme.of(context).colorScheme.onPrimary : Theme.of(context).hintColor,
                          size: Dimensions.iconSizeMedium,
                        ),
                      ),
                      const SizedBox(width: Dimensions.paddingSizeDefault),
                      Expanded(
                        child: Text(
                          step['label'] as String,
                          style: (isActive ? textBold : textRegular).copyWith(
                            fontSize: Dimensions.fontSizeDefault,
                            color: isCompleted
                                ? Theme.of(context).textTheme.bodyLarge?.color
                                : Theme.of(context).hintColor,
                          ),
                        ),
                      ),
                      if (isCompleted)
                        Icon(Icons.check, color: Theme.of(context).primaryColor, size: 20),
                    ],
                  ),
                  if (index < steps.length - 1)
                    Container(
                      margin: const EdgeInsets.only(left: 17),
                      width: 2,
                      height: 30,
                      color: isCompleted
                          ? Theme.of(context).primaryColor
                          : Theme.of(context).hintColor.withValues(alpha: 0.2),
                    ),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }

  // B30: driver location GoogleMap shown only when picked_up
  Widget _buildDriverMap(BuildContext context) {
    if (_driverLat == null ||
        _driverLng == null ||
        !['picked_up'].contains(_currentStatus)) {
      return const SizedBox.shrink();
    }
    return Column(
      children: [
        const SizedBox(height: 16),
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            height: 180,
            child: GoogleMap(
              initialCameraPosition: CameraPosition(
                target: LatLng(_driverLat!, _driverLng!),
                zoom: 14,
              ),
              markers: {
                Marker(
                  markerId: const MarkerId('driver'),
                  position: LatLng(_driverLat!, _driverLng!),
                  icon: BitmapDescriptor.defaultMarkerWithHue(
                      BitmapDescriptor.hueViolet),
                ),
              },
              zoomControlsEnabled: false,
              myLocationButtonEnabled: false,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDriverInfo(BuildContext context) {
    if (_currentStepIndex < 1 && _driverInfo.isEmpty) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
          child: Center(
            child: Column(
              children: [
                Icon(
                  Icons.delivery_dining,
                  size: 60,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.3),
                ),
                const SizedBox(height: Dimensions.paddingSizeSmall),
                Text(
                  'waiting_for_driver'.tr,
                  style: textRegular.copyWith(color: Theme.of(context).hintColor),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final String rawName = _driverName.isNotEmpty
        ? _driverName
        : (_driverInfo['full_name'] as String? ??
            '${_driverInfo['first_name'] ?? ''} ${_driverInfo['last_name'] ?? ''}'
                .trim());
    final driverName = rawName.isNotEmpty ? rawName : 'driver'.tr;
    final driverPhone = _driverInfo['phone'] ?? '';
    final driverRating = _driverInfo['avg_rating']?.toString() ??
        _driverInfo['rating']?.toString() ??
        '';
    final vehicleModel = _driverInfo['vehicle_model'] ?? '';
    final vehiclePlate = _driverInfo['vehicle_plate'] ?? '';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('your_driver'.tr,
                style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor:
                      Theme.of(context).primaryColor.withValues(alpha: 0.1),
                  child: Icon(Icons.person,
                      color: Theme.of(context).primaryColor, size: 30),
                ),
                const SizedBox(width: Dimensions.paddingSizeDefault),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(driverName,
                          style: textBold.copyWith(
                              fontSize: Dimensions.fontSizeDefault)),
                      if (driverRating.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.star,
                                color: Colors.amber, size: 16),
                            const SizedBox(width: 4),
                            Text(driverRating,
                                style: textMedium.copyWith(
                                    fontSize: Dimensions.fontSizeSmall)),
                          ],
                        ),
                      ],
                      if (vehicleModel.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          '$vehicleModel ${vehiclePlate.isNotEmpty ? '• $vehiclePlate' : ''}',
                          style: textRegular.copyWith(
                            fontSize: Dimensions.fontSizeSmall,
                            color: Theme.of(context).hintColor,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (driverPhone.isNotEmpty)
                      IconButton(
                        onPressed: () async {
                          HapticFeedback.mediumImpact();
                          final uri = Uri.parse('tel:$driverPhone');
                          if (await canLaunchUrl(uri)) {
                            await launchUrl(uri);
                          }
                        },
                        icon: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Theme.of(context).colorScheme.tertiary.withValues(alpha: 0.15),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(Icons.phone,
                              color: Theme.of(context).colorScheme.tertiary, size: 20),
                        ),
                      ),
                    IconButton(
                      onPressed: _driverId.isEmpty
                          ? null
                          : () {
                              HapticFeedback.mediumImpact();
                              final name =
                                  _driverName.isEmpty ? 'driver'.tr : _driverName;
                              Get.find<MessageController>()
                                  .createMartChannel(_driverId, widget.orderId, name);
                            },
                      icon: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: _driverId.isEmpty
                              ? Theme.of(context).hintColor.withValues(alpha: 0.1)
                              : Theme.of(context)
                                  .primaryColor
                                  .withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.chat,
                          color: _driverId.isEmpty
                              ? Theme.of(context).hintColor
                              : Theme.of(context).primaryColor,
                          size: 20,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // B31: delivery photo + signature display with lightbox
  Widget _buildDeliveryProof(BuildContext context) {
    final deliveryPhoto = _orderData['delivery_photo'] as String?;
    final signatureImage = _orderData['signature_image'] as String?;

    if ((deliveryPhoto == null || deliveryPhoto.isEmpty) &&
        (signatureImage == null || signatureImage.isEmpty)) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (deliveryPhoto != null && deliveryPhoto.isNotEmpty) ...[
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text('delivery_proof'.tr,
              style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          GestureDetector(
            onTap: () => _showLightbox(context, deliveryPhoto),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              child: CachedNetworkImage(
                imageUrl: deliveryPhoto,
                height: 150,
                width: double.infinity,
                fit: BoxFit.cover,
                placeholder: (_, __) => Container(
                  height: 150,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                  child: const Center(child: CircularProgressIndicator()),
                ),
                errorWidget: (_, __, ___) => Container(
                  height: 150,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                  child: const Icon(Icons.image_not_supported_outlined),
                ),
              ),
            ),
          ),
        ],
        if (signatureImage != null && signatureImage.isNotEmpty) ...[
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text('customer_signature'.tr,
              style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          GestureDetector(
            onTap: () => _showLightbox(context, signatureImage),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              child: CachedNetworkImage(
                imageUrl: signatureImage,
                height: 120,
                width: double.infinity,
                fit: BoxFit.contain,
                placeholder: (_, __) => Container(
                  height: 120,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                  child: const Center(child: CircularProgressIndicator()),
                ),
                errorWidget: (_, __, ___) => Container(
                  height: 120,
                  color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                  child: const Icon(Icons.draw_outlined),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }

  // B31: lightbox helper
  void _showLightbox(BuildContext context, String imageUrl) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.black,
        child: InteractiveViewer(
          child: CachedNetworkImage(
            imageUrl: imageUrl,
            fit: BoxFit.contain,
            progressIndicatorBuilder: (_, __, ___) =>
                const Center(child: CircularProgressIndicator()),
            errorWidget: (_, __, ___) => const Center(
              child: Icon(Icons.image_not_supported_outlined, color: Colors.white, size: 48),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCancelButton(BuildContext context) {
    final canCancel = _currentStatus == 'pending' || _currentStatus == 'accepted';

    if (!canCancel) {
      return Column(
        children: [
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: null,
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).hintColor,
                side: BorderSide(color: Theme.of(context).hintColor.withValues(alpha: 0.3)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
              child: Text('cancel_order'.tr),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'order_in_transit_cannot_cancel'.tr,
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    return SizedBox(
      width: double.infinity,
      child: OutlinedButton(
        onPressed: _isOffline
            ? null
            : () {
                HapticFeedback.mediumImpact();
                _showCancelConfirmation(context);
              },
        style: OutlinedButton.styleFrom(
          foregroundColor: Theme.of(context).colorScheme.error,
          side: BorderSide(color: Theme.of(context).colorScheme.error),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          ),
        ),
        child: Text('cancel_order'.tr),
      ),
    );
  }

  void _showCancelConfirmation(BuildContext context) {
    // Guard against acting on a stale status (e.g. screen reopened offline).
    if (_currentStatus == 'delivered' || _currentStatus == 'cancelled') {
      Get.snackbar('error'.tr, 'order_already_completed'.tr);
      return;
    }
    Get.dialog(
      AlertDialog(
        title: Text('cancel_order'.tr),
        content: Text('cancel_order_confirmation'.tr),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: Text('no'.tr),
          ),
          TextButton(
            onPressed: () async {
              Get.back();
              try {
                final cancelResponse = await Get.find<ApiClient>().putData(
                  '${AppConstants.martCancelOrder}${widget.orderId}/cancel',
                  {},
                );
                if (cancelResponse.statusCode == 200) {
                  Get.back();
                  Get.snackbar('success'.tr, 'order_cancelled'.tr);
                } else if (cancelResponse.statusCode == 404) {
                  Get.snackbar('error'.tr, 'order_not_found'.tr);
                } else {
                  Get.snackbar('error'.tr, 'cancel_failed'.tr);
                }
              } catch (e) {
                debugPrint('Mart tracking error: $e');
                Get.snackbar('error'.tr, 'cancel_failed'.tr);
              }
            },
            child: Text('yes'.tr,
                style:
                    TextStyle(color: Theme.of(context).colorScheme.error)),
          ),
        ],
      ),
    );
  }

  void _showRatingBottomSheet() {
    int selectedRating = 5;
    final commentController = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setSheetState) {
          return Padding(
            padding: EdgeInsets.only(
              left: Dimensions.paddingSizeLarge,
              right: Dimensions.paddingSizeLarge,
              top: Dimensions.paddingSizeLarge,
              bottom: MediaQuery.of(ctx).viewInsets.bottom + Dimensions.paddingSizeLarge,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('rate_your_delivery'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(5, (i) {
                    final star = i + 1;
                    return GestureDetector(
                      onTap: () => setSheetState(() => selectedRating = star),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Icon(
                          star <= selectedRating ? Icons.star : Icons.star_border,
                          color: Colors.amber,
                          size: 36,
                        ),
                      ),
                    );
                  }),
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                TextField(
                  controller: commentController,
                  maxLines: 2,
                  decoration: InputDecoration(
                    hintText: 'optional_comment'.tr,
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: Dimensions.paddingSizeDefault),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(ctx).pop(),
                        child: Text('skip'.tr),
                      ),
                    ),
                    const SizedBox(width: Dimensions.paddingSizeSmall),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          Navigator.of(ctx).pop();
                          try {
                            await Get.find<PaymentController>().submitReview(
                              widget.orderId,
                              selectedRating,
                              commentController.text,
                            );
                          } catch (_) {}
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Theme.of(context).primaryColor,
                          foregroundColor: Theme.of(context).colorScheme.onPrimary,
                        ),
                        child: Text('submit'.tr),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        });
      },
    ).whenComplete(() => commentController.dispose());
  }

  Color _getStatusColor(BuildContext context) {
    switch (_currentStatus) {
      case 'pending':
        return Theme.of(context).primaryColor;
      case 'accepted':
        return Theme.of(context).primaryColor;
      case 'picked_up':
        return Theme.of(context).primaryColor;
      case 'delivered':
        return Theme.of(context).colorScheme.tertiary;
      case 'cancelled':
        return Theme.of(context).colorScheme.error;
      default:
        return Theme.of(context).hintColor;
    }
  }
}
