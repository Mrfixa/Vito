import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class JobRequestModal extends StatefulWidget {
  final String tripId;
  final String pickupAddress;
  final String destinationAddress;
  final String estimatedFare;
  final String distance;
  final VoidCallback onAccept;
  final VoidCallback? onDecline;
  final VoidCallback? onTimeout;
  final String? serviceType;

  const JobRequestModal({
    super.key,
    required this.tripId,
    required this.pickupAddress,
    required this.destinationAddress,
    required this.estimatedFare,
    required this.distance,
    required this.onAccept,
    this.onDecline,
    this.onTimeout,
    this.serviceType,
  });

  static Future<void> show({
    required String tripId,
    required String pickupAddress,
    required String destinationAddress,
    required String estimatedFare,
    required String distance,
    required VoidCallback onAccept,
    VoidCallback? onDecline,
    VoidCallback? onTimeout,
    String? serviceType,
  }) {
    return Get.dialog(
      JobRequestModal(
        tripId: tripId,
        pickupAddress: pickupAddress,
        destinationAddress: destinationAddress,
        estimatedFare: estimatedFare,
        distance: distance,
        onAccept: onAccept,
        onDecline: onDecline,
        onTimeout: onTimeout,
        serviceType: serviceType,
      ),
      barrierDismissible: false,
    );
  }

  @override
  State<JobRequestModal> createState() => _JobRequestModalState();
}

class _JobRequestModalState extends State<JobRequestModal>
    with SingleTickerProviderStateMixin {
  static const int _countdownSeconds = 30;
  late Timer _timer;
  int _remainingSeconds = _countdownSeconds;
  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;
  bool _isAccepting = false;

  @override
  void initState() {
    super.initState();
    HapticFeedback.heavyImpact();
    try {
      AudioPlayer().play(AssetSource('notification.wav'));
    } catch (_) {}

    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    )..repeat(reverse: true);

    _pulseAnimation = Tween<double>(begin: 1.0, end: 1.05).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );

    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        setState(() {
          _remainingSeconds--;
        });
        if (_remainingSeconds <= 5) {
          HapticFeedback.lightImpact();
        }
      } else {
        _timer.cancel();
        if (mounted) {
          widget.onTimeout?.call();
          if (Navigator.of(context).canPop()) {
            Navigator.of(context).pop();
          }
        }
      }
    });
  }

  @override
  void dispose() {
    _timer.cancel();
    _pulseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(Dimensions.radiusLarge),
            boxShadow: [
              BoxShadow(
                color: Theme.of(context).primaryColor.withValues(alpha: 0.3),
                blurRadius: 20,
                spreadRadius: 2,
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildCountdownHeader(context),
              Padding(
                padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildFareAndDistance(context),
                    const SizedBox(height: Dimensions.paddingSizeDefault),
                    _buildAddressRow(
                      context,
                      icon: Icons.circle,
                      iconColor: Colors.green,
                      label: 'pickup'.tr,
                      address: widget.pickupAddress,
                    ),
                    _buildDottedLine(context),
                    _buildAddressRow(
                      context,
                      icon: Icons.location_on,
                      iconColor: Colors.red,
                      label: 'destination'.tr,
                      address: widget.destinationAddress,
                    ),
                    const SizedBox(height: Dimensions.paddingSizeLarge),
                    _buildActionButtons(context),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _ringColor() {
    final progress = _remainingSeconds / _countdownSeconds;
    if (progress > 0.5) return Colors.white;
    if (progress > 0.25) return Colors.yellow;
    return Colors.red.shade300;
  }

  Widget _buildCountdownHeader(BuildContext context) {
    final progress = _remainingSeconds / _countdownSeconds;
    final serviceLabel = widget.serviceType == 'parcel'
        ? 'send_service'.tr
        : widget.serviceType == 'mart'
            ? 'mart_service'.tr
            : 'ride_service'.tr;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
      decoration: BoxDecoration(
        color: Theme.of(context).primaryColor,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(Dimensions.radiusLarge),
          topRight: Radius.circular(Dimensions.radiusLarge),
        ),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'new_ride_request'.tr,
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.25),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  serviceLabel,
                  style: textMedium.copyWith(
                    fontSize: Dimensions.fontSizeSmall,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          SizedBox(
            width: 60,
            height: 60,
            child: Stack(
              fit: StackFit.expand,
              children: [
                CircularProgressIndicator(
                  value: progress,
                  strokeWidth: 4,
                  backgroundColor: Colors.white.withValues(alpha: 0.3),
                  valueColor: AlwaysStoppedAnimation<Color>(_ringColor()),
                ),
                Center(
                  child: Text(
                    '$_remainingSeconds',
                    style: textBold.copyWith(
                      fontSize: Dimensions.fontSizeOverLarge,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFareAndDistance(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
      children: [
        _buildInfoChip(
          context,
          icon: Icons.attach_money,
          label: 'estimated_fare'.tr,
          value: widget.estimatedFare,
        ),
        Container(
          width: 1,
          height: 40,
          color: Theme.of(context).dividerColor,
        ),
        _buildInfoChip(
          context,
          icon: Icons.straighten,
          label: 'distance'.tr,
          value: widget.distance,
        ),
      ],
    );
  }

  Widget _buildInfoChip(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Column(
      children: [
        Icon(icon, color: Theme.of(context).primaryColor, size: 20),
        const SizedBox(height: 4),
        Text(label, style: textRegular.copyWith(
          fontSize: Dimensions.fontSizeSmall,
          color: Theme.of(context).hintColor,
        )),
        Text(value, style: textSemiBold.copyWith(
          fontSize: Dimensions.fontSizeDefault,
        )),
      ],
    );
  }

  Widget _buildAddressRow(
    BuildContext context, {
    required IconData icon,
    required Color iconColor,
    required String label,
    required String address,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: iconColor, size: 14),
        const SizedBox(width: Dimensions.paddingSizeSmall),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: textRegular.copyWith(
                fontSize: Dimensions.fontSizeSmall,
                color: Theme.of(context).hintColor,
              )),
              Text(address, style: textMedium.copyWith(
                fontSize: Dimensions.fontSizeDefault,
              ), maxLines: 2, overflow: TextOverflow.ellipsis),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildDottedLine(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 6),
      child: Column(
        children: List.generate(3, (_) => Container(
          width: 2,
          height: 4,
          margin: const EdgeInsets.symmetric(vertical: 1),
          color: Theme.of(context).hintColor.withValues(alpha: 0.3),
        )),
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: SizedBox(
            height: 50,
            child: OutlinedButton(
              onPressed: _isAccepting ? null : () {
                HapticFeedback.mediumImpact();
                _timer.cancel();
                if (Navigator.of(context).canPop()) {
                  Navigator.of(context).pop();
                }
                widget.onDecline?.call();
              },
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).colorScheme.error,
                side: BorderSide(color: Theme.of(context).colorScheme.error),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
              child: Text(
                'decline'.tr,
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeDefault,
                  color: Theme.of(context).colorScheme.error,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(width: Dimensions.paddingSizeDefault),
        Expanded(
          flex: 2,
          child: ScaleTransition(
            scale: _pulseAnimation,
            child: SizedBox(
              height: 50,
              child: ElevatedButton(
                onPressed: _isAccepting ? null : () {
                  setState(() => _isAccepting = true);
                  HapticFeedback.heavyImpact();
                  _timer.cancel();
                  widget.onAccept();
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Theme.of(context).primaryColor,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                  ),
                ),
                child: _isAccepting
                    ? const SizedBox(
                        width: 24, height: 24,
                        child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white,
                        ),
                      )
                    : Text(
                        'accept'.tr,
                        style: textBold.copyWith(
                          fontSize: Dimensions.fontSizeLarge,
                          color: Colors.white,
                        ),
                      ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
