import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_item_model.dart';

class MartOrderModel {
  final String? id;
  final String? refId;
  final String? status;
  final double totalAmount;
  final double tipAmount;
  final double discountAmount;
  final String? paymentStatus;
  final String? paymentMethod;
  final String? deliveryAddress;
  final String? notes;
  final String? customerId;
  final String? customerName;
  final String? customerPhone;
  final String? createdAt;
  final List<MartOrderItemModel> items;

  MartOrderModel({
    this.id,
    this.refId,
    this.status,
    this.totalAmount = 0,
    this.tipAmount = 0,
    this.discountAmount = 0,
    this.paymentStatus,
    this.paymentMethod,
    this.deliveryAddress,
    this.notes,
    this.customerId,
    this.customerName,
    this.customerPhone,
    this.createdAt,
    this.items = const [],
  });

  factory MartOrderModel.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'];
    return MartOrderModel(
      id: json['id']?.toString(),
      refId: json['ref_id']?.toString(),
      status: json['status']?.toString(),
      totalAmount: double.tryParse(json['total_amount']?.toString() ?? '') ?? 0,
      tipAmount: double.tryParse(json['tip_amount']?.toString() ?? '') ?? 0,
      discountAmount: double.tryParse(json['discount_amount']?.toString() ?? '') ?? 0,
      paymentStatus: json['payment_status']?.toString(),
      paymentMethod: json['payment_method']?.toString(),
      deliveryAddress: json['delivery_address']?.toString(),
      notes: json['notes']?.toString(),
      customerId: json['customer_id']?.toString(),
      customerName: customer is Map<String, dynamic>
          ? '${customer['first_name'] ?? ''} ${customer['last_name'] ?? ''}'.trim()
          : null,
      customerPhone: customer is Map<String, dynamic> ? customer['phone']?.toString() : null,
      createdAt: json['created_at']?.toString(),
      items: json['items'] is List
          ? (json['items'] as List)
              .whereType<Map<String, dynamic>>()
              .map(MartOrderItemModel.fromJson)
              .toList()
          : const [],
    );
  }

  int get itemCount => items.fold(0, (sum, i) => sum + i.quantity);
}
