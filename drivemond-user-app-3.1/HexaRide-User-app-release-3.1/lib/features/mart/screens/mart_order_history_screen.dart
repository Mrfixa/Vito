import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/features/mart/controllers/mart_controller.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_model.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_order_tracking_screen.dart';
import 'package:ride_sharing_user_app/helper/price_converter.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class MartOrderHistoryScreen extends StatefulWidget {
  const MartOrderHistoryScreen({super.key});

  @override
  State<MartOrderHistoryScreen> createState() => _MartOrderHistoryScreenState();
}

class _MartOrderHistoryScreenState extends State<MartOrderHistoryScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Get.find<MartController>().getOrders();
    });
  }

  Color _statusColor(String? status) {
    switch (status) {
      case 'delivered':
        return Colors.green;
      case 'cancelled':
        return Colors.red;
      case 'picked_up':
        return Colors.blue;
      default:
        return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'mart_order_history'.tr),
      body: GetBuilder<MartController>(
        builder: (martController) {
          if (martController.isLoading && martController.orders.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }
          if (martController.orders.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.receipt_long, size: 64, color: Theme.of(context).disabledColor),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  Text('no_orders_yet'.tr, style: textRegular.copyWith(color: Theme.of(context).disabledColor)),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () => martController.getOrders(),
            child: ListView.builder(
              padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
              itemCount: martController.orders.length,
              itemBuilder: (context, index) => _orderCard(context, martController.orders[index]),
            ),
          );
        },
      ),
    );
  }

  Widget _orderCard(BuildContext context, MartOrderModel order) {
    return InkWell(
      onTap: () => Get.to(() => MartOrderTrackingScreen(orderId: order.id ?? '')),
      child: Container(
        margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('#${order.refId ?? ''}', style: textBold),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: _statusColor(order.status).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                  ),
                  child: Text(
                    'order_status_${order.status ?? 'pending'}'.tr,
                    style: textRegular.copyWith(color: _statusColor(order.status), fontSize: Dimensions.fontSizeSmall),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('${order.itemCount} ${'items'.tr}',
                    style: textRegular.copyWith(color: Theme.of(context).disabledColor, fontSize: Dimensions.fontSizeSmall)),
                Text(PriceConverter.convertPrice(order.totalAmount), style: textBold.copyWith(color: Theme.of(context).primaryColor)),
              ],
            ),
            if (order.createdAt != null) ...[
              const SizedBox(height: 4),
              Text(order.createdAt!.split('T').first,
                  style: textRegular.copyWith(color: Theme.of(context).disabledColor, fontSize: Dimensions.fontSizeSmall)),
            ],
          ],
        ),
      ),
    );
  }
}
