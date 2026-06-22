import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';

class MartOrderItemModel {
  final String? id;
  final String? productId;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final MartProductModel? product;

  MartOrderItemModel({
    this.id,
    this.productId,
    this.quantity = 0,
    this.unitPrice = 0,
    this.totalPrice = 0,
    this.product,
  });

  factory MartOrderItemModel.fromJson(Map<String, dynamic> json) {
    return MartOrderItemModel(
      id: json['id']?.toString(),
      productId: json['product_id']?.toString(),
      quantity: int.tryParse(json['quantity']?.toString() ?? '') ?? 0,
      unitPrice: double.tryParse(json['unit_price']?.toString() ?? '') ?? 0,
      totalPrice: double.tryParse(json['total_price']?.toString() ?? '') ?? 0,
      product: json['product'] is Map<String, dynamic>
          ? MartProductModel.fromJson(json['product'])
          : null,
    );
  }

  String get displayName => product?.name ?? 'Item';
}
