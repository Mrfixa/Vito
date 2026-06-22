import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/features/mart/controllers/mart_controller.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';
import 'package:ride_sharing_user_app/helper/price_converter.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

class MartProductDetailsScreen extends StatefulWidget {
  final String productId;
  final MartProductModel? initialProduct;
  final void Function(MartProductModel product)? onAddToCart;

  const MartProductDetailsScreen({
    super.key,
    required this.productId,
    this.initialProduct,
    this.onAddToCart,
  });

  @override
  State<MartProductDetailsScreen> createState() => _MartProductDetailsScreenState();
}

class _MartProductDetailsScreenState extends State<MartProductDetailsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Get.find<MartController>().getProductDetails(widget.productId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'product_details'.tr),
      body: GetBuilder<MartController>(
        builder: (martController) {
          final product = martController.productDetails ?? widget.initialProduct;
          if (martController.isLoading && product == null) {
            return const Center(child: CircularProgressIndicator());
          }
          if (product == null) {
            return Center(child: Text('something_went_wrong'.tr));
          }
          return ListView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                child: AspectRatio(
                  aspectRatio: 16 / 10,
                  child: (product.image != null && product.image!.isNotEmpty)
                      ? CachedNetworkImage(
                          imageUrl: product.image!,
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => _placeholder(context),
                        )
                      : _placeholder(context),
                ),
              ),
              const SizedBox(height: Dimensions.paddingSizeDefault),
              Text(product.name ?? '', style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
              const SizedBox(height: Dimensions.paddingSizeExtraSmall),
              Text(PriceConverter.convertPrice(product.price),
                  style: textBold.copyWith(color: Theme.of(context).primaryColor, fontSize: Dimensions.fontSizeLarge)),
              const SizedBox(height: Dimensions.paddingSizeSmall),
              Row(
                children: [
                  Icon(product.inStock ? Icons.check_circle : Icons.cancel,
                      color: product.inStock ? Colors.green : Colors.red, size: 18),
                  const SizedBox(width: 6),
                  Text(product.inStock ? 'in_stock'.tr : 'out_of_stock'.tr,
                      style: textRegular.copyWith(color: product.inStock ? Colors.green : Colors.red)),
                ],
              ),
              if (product.description != null && product.description!.isNotEmpty) ...[
                const SizedBox(height: Dimensions.paddingSizeDefault),
                Text('description'.tr, style: textBold),
                const SizedBox(height: Dimensions.paddingSizeExtraSmall),
                Text(product.description!, style: textRegular.copyWith(color: Theme.of(context).disabledColor)),
              ],
            ],
          );
        },
      ),
      bottomNavigationBar: widget.onAddToCart == null
          ? null
          : GetBuilder<MartController>(
              builder: (martController) {
                final product = martController.productDetails ?? widget.initialProduct;
                final enabled = product != null && product.inStock;
                return Padding(
                  padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                  child: ElevatedButton(
                    onPressed: enabled
                        ? () {
                            widget.onAddToCart!(product);
                            Get.back();
                          }
                        : null,
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size(double.infinity, 50),
                      backgroundColor: Theme.of(context).primaryColor,
                    ),
                    child: Text('add_to_cart'.tr, style: textBold.copyWith(color: Colors.white)),
                  ),
                );
              },
            ),
    );
  }

  Widget _placeholder(BuildContext context) => Container(
        color: Theme.of(context).disabledColor.withValues(alpha: 0.1),
        child: Icon(Icons.shopping_bag_outlined, size: 56, color: Theme.of(context).disabledColor),
      );
}
