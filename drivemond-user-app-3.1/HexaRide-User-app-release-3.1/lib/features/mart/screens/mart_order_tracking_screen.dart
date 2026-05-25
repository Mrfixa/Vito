import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/message/controllers/message_controller.dart';
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
  Timer? _pollTimer;
  int _pollCount = 0;
  static const int _maxPollCount = 240;

  Map<String, dynamic> _driverInfo = {};
  String _estimatedArrival = '';
  String _driverId = '';
  String _driverName = '';

  final List<Map<String, dynamic>> _statusSteps = [
    {'status': 'pending', 'label': 'order_placed', 'icon': Icons.receipt_long},
    {'status': 'accepted', 'label': 'order_accepted', 'icon': Icons.check_circle},
    {'status': 'picked_up', 'label': 'picked_up', 'icon': Icons.local_shipping},
    {'status': 'delivered', 'label': 'delivered', 'icon': Icons.done_all},
  ];

  int get _currentStepIndex {
    return _statusSteps.indexWhere((s) => s['status'] == _currentStatus);
  }

  @override
  void initState() {
    super.initState();
    _fetchOrderStatus();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      _pollCount++;
      if (_pollCount >= _maxPollCount) {
        _pollTimer?.cancel();
        debugPrint('Mart tracking: max poll count reached, stopping timer');
        return;
      }
      _fetchOrderStatus();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
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
          if (data['driver'] != null) {
            _driverInfo = Map<String, dynamic>.from(data['driver']);
            _driverId = data['driver_id'] ?? data['driver']?['id'] ?? '';
            _driverName = '${data['driver']?['first_name'] ?? ''} ${data['driver']?['last_name'] ?? ''}'.trim();
          } else {
            _driverInfo = {};
            _driverId = '';
            _driverName = '';
          }
          _estimatedArrival = data['estimated_arrival'] ?? '';
          _isLoading = false;
          _isOffline = false;
        });
        if (_currentStatus == 'delivered' || _currentStatus == 'cancelled') {
          _pollTimer?.cancel();
        }
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Mart tracking error: $e');
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
                        _buildOrderHeader(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        _buildStatusTimeline(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        _buildDriverInfo(context),
                        const SizedBox(height: Dimensions.paddingSizeLarge),
                        if (_currentStatus != 'delivered' && _currentStatus != 'cancelled')
                          _buildCancelButton(context),
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
                color: _getStatusColor().withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
              child: Icon(Icons.receipt_long, color: _getStatusColor()),
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
                      _currentStatus.tr,
                      style: textMedium.copyWith(
                        color: _getStatusColor(),
                        fontSize: Dimensions.fontSizeSmall,
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
                  Text('eta'.tr, style: textRegular.copyWith(
                    fontSize: 10, color: Theme.of(context).hintColor,
                  )),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusTimeline(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('delivery_status'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            ...List.generate(_statusSteps.length, (index) {
              final step = _statusSteps[index];
              final isCompleted = index <= _currentStepIndex;
              final isActive = index == _currentStepIndex;

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
                          color: isCompleted ? Colors.white : Theme.of(context).hintColor,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: Dimensions.paddingSizeDefault),
                      Expanded(
                        child: Text(
                          (step['label'] as String).tr,
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
                  if (index < _statusSteps.length - 1)
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
            '${_driverInfo['first_name'] ?? ''} ${_driverInfo['last_name'] ?? ''}'.trim());
    final driverName = rawName.isNotEmpty ? rawName : 'driver'.tr;
    final driverPhone = _driverInfo['phone'] ?? '';
    final driverRating = _driverInfo['avg_rating']?.toString() ?? _driverInfo['rating']?.toString() ?? '';
    final vehicleModel = _driverInfo['vehicle_model'] ?? '';
    final vehiclePlate = _driverInfo['vehicle_plate'] ?? '';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('your_driver'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeDefault),
            Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                  child: Icon(Icons.person, color: Theme.of(context).primaryColor, size: 30),
                ),
                const SizedBox(width: Dimensions.paddingSizeDefault),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(driverName, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
                      if (driverRating.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.star, color: Colors.amber, size: 16),
                            const SizedBox(width: 4),
                            Text(driverRating, style: textMedium.copyWith(fontSize: Dimensions.fontSizeSmall)),
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
                            color: Colors.green.withValues(alpha: 0.1),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.phone, color: Colors.green, size: 20),
                        ),
                      ),
                    IconButton(
                      onPressed: _driverId.isEmpty
                          ? null
                          : () {
                              HapticFeedback.mediumImpact();
                              final name = _driverName.isEmpty ? 'driver'.tr : _driverName;
                              Get.find<MessageController>()
                                  .createMartChannel(_driverId, widget.orderId, name);
                            },
                      icon: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: _driverId.isEmpty
                              ? Theme.of(context).hintColor.withValues(alpha: 0.1)
                              : Theme.of(context).primaryColor.withValues(alpha: 0.1),
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

  Widget _buildCancelButton(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton(
        onPressed: _isOffline ? null : () {
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
            child: Text('yes'.tr, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ),
        ],
      ),
    );
  }

  Color _getStatusColor() {
    switch (_currentStatus) {
      case 'pending':
        return Colors.orange;
      case 'accepted':
        return Colors.blue;
      case 'picked_up':
        return Colors.indigo;
      case 'delivered':
        return Colors.green;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
