import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';

class MartOrderTrackingScreen extends StatefulWidget {
  final String orderId;

  const MartOrderTrackingScreen({super.key, required this.orderId});

  @override
  State<MartOrderTrackingScreen> createState() => _MartOrderTrackingScreenState();
}

class _MartOrderTrackingScreenState extends State<MartOrderTrackingScreen> {
  String _currentStatus = 'pending';
  bool _isOffline = false;

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
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'order_tracking'.tr, regularAppbar: true),
      body: Column(
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
                  if (_currentStatus == 'pending') _buildCancelButton(context),
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

  Widget _buildOrderHeader(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${'order'.tr} #${widget.orderId.substring(0, 8)}',
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Dimensions.paddingSizeSmall,
                    vertical: 4,
                  ),
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
          children: List.generate(_statusSteps.length, (index) {
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
        ),
      ),
    );
  }

  Widget _buildDriverInfo(BuildContext context) {
    if (_currentStepIndex < 1) {
      return Center(
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
      );
    }
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Theme.of(context).primaryColor.withValues(alpha: 0.1),
          child: Icon(Icons.person, color: Theme.of(context).primaryColor),
        ),
        title: Text('driver'.tr, style: textMedium),
        subtitle: Text('on_the_way'.tr, style: textRegular),
        trailing: IconButton(
          onPressed: () {
            HapticFeedback.mediumImpact();
          },
          icon: Icon(Icons.chat, color: Theme.of(context).primaryColor),
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
            onPressed: () {
              Get.back();
              Get.back();
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
