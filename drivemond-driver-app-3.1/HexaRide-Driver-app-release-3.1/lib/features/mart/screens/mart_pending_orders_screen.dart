import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_delivery_screen.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';

class MartPendingOrdersScreen extends StatefulWidget {
  const MartPendingOrdersScreen({super.key});

  @override
  State<MartPendingOrdersScreen> createState() => _MartPendingOrdersScreenState();
}

class _MartPendingOrdersScreenState extends State<MartPendingOrdersScreen> {
  List<dynamic> _orders = [];
  bool _isLoading = true;
  String? _acceptingOrderId; // tracks which specific order is being accepted
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _fetchOrders();
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) _fetchOrders();
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _fetchOrders() async {
    try {
      final response = await Get.find<ApiClient>().getData(AppConstants.martPendingOrders);
      if (!mounted) return;
      if (response.statusCode == 200 && response.body['data'] != null) {
        final data = response.body['data'];
        setState(() {
          _orders = (data['data'] as List?) ?? (data is List ? data : []);
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Mart pending orders error: $e');
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _acceptOrder(String orderId) async {
    if (_acceptingOrderId != null) return; // another acceptance in progress
    setState(() => _acceptingOrderId = orderId);
    try {
      final response = await Get.find<ApiClient>().postData(
        AppConstants.martAcceptOrder,
        {'order_id': orderId},
      );
      if (!mounted) return;
      if (response.statusCode == 200) {
        Get.off(() => MartDeliveryScreen(orderId: orderId));
      } else {
        showCustomSnackBar('order_not_available'.tr);
        _fetchOrders();
      }
    } catch (e) {
      debugPrint('Mart accept error: $e');
      if (mounted) showCustomSnackBar('something_went_wrong'.tr);
    } finally {
      if (mounted) setState(() => _acceptingOrderId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'pending_mart_orders'.tr, regularAppbar: true, showLogo: true),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor))
          : RefreshIndicator(
              onRefresh: _fetchOrders,
              color: Theme.of(context).primaryColor,
              child: _orders.isEmpty
                  ? ListView(
                      children: [
                        SizedBox(height: Get.height * 0.3),
                        Icon(Icons.storefront_outlined, size: 64, color: Theme.of(context).hintColor),
                        const SizedBox(height: Dimensions.paddingSizeDefault),
                        Text(
                          'no_pending_orders'.tr,
                          textAlign: TextAlign.center,
                          style: textMedium.copyWith(color: Theme.of(context).hintColor),
                        ),
                        const SizedBox(height: Dimensions.paddingSizeSmall),
                        Text(
                          'new_orders_appear_here'.tr,
                          textAlign: TextAlign.center,
                          style: textRegular.copyWith(
                            color: Theme.of(context).hintColor,
                            fontSize: Dimensions.fontSizeSmall,
                          ),
                        ),
                      ],
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                      itemCount: _orders.length,
                      separatorBuilder: (_, __) => const SizedBox(height: Dimensions.paddingSizeSmall),
                      itemBuilder: (context, index) => _buildOrderCard(context, _orders[index]),
                    ),
            ),
    );
  }

  Widget _buildOrderCard(BuildContext context, Map<String, dynamic> order) {
    final orderId = order['id'] ?? '';
    final refId = order['ref_id'] ?? '';
    final address = order['delivery_address'] ?? '';
    final total = order['total_amount'] ?? 0;
    final items = (order['items'] as List?) ?? [];
    final itemCount = items.length;

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(Dimensions.radiusDefault)),
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('${'order'.tr} #$refId',
                    style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall, vertical: Dimensions.paddingSizeExtraSmall),
                  decoration: BoxDecoration(
                    color: Theme.of(context).primaryColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                  ),
                  child: Text(
                    'pending'.tr,
                    style: textMedium.copyWith(
                        color: Theme.of(context).primaryColor, fontSize: Dimensions.fontSizeSmall),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),

            Row(children: [
              Icon(Icons.location_on_outlined, size: 16, color: Theme.of(context).hintColor),
              const SizedBox(width: 4),
              Expanded(
                child: Text(address,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textRegular.copyWith(
                        fontSize: Dimensions.fontSizeSmall, color: Theme.of(context).hintColor)),
              ),
            ]),
            const SizedBox(height: Dimensions.paddingSizeSmall),

            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('$itemCount ${'items_ordered'.tr}',
                  style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
              Text('\$$total',
                  style: textBold.copyWith(
                      color: Theme.of(context).primaryColor, fontSize: Dimensions.fontSizeDefault)),
            ]),
            const SizedBox(height: Dimensions.paddingSizeDefault),

            SizedBox(
              width: double.infinity,
              child: _acceptingOrderId == orderId
                  ? Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor))
                  : ElevatedButton(
                      onPressed: _acceptingOrderId != null ? null : () => _acceptOrder(orderId),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Theme.of(context).primaryColor,
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(Dimensions.radiusDefault)),
                      ),
                      child: Text('accept_order'.tr,
                          style: textBold.copyWith(color: Theme.of(context).colorScheme.onPrimary)),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
