import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/no_data_widget.dart';

class MartStoreScreen extends StatefulWidget {
  const MartStoreScreen({super.key});

  @override
  State<MartStoreScreen> createState() => _MartStoreScreenState();
}

class _MartStoreScreenState extends State<MartStoreScreen> {
  final TextEditingController _searchController = TextEditingController();
  final List<Map<String, dynamic>> _products = [];
  final List<Map<String, dynamic>> _cartItems = [];
  bool _isLoading = false;
  bool _isOffline = false;
  String _selectedCategory = 'all';

  final List<String> _categories = ['all', 'food', 'drinks', 'snacks', 'essentials'];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'vito_mart'.tr, regularAppbar: true),
      body: _isOffline ? _buildOfflineBanner(context) : _buildBody(context),
      floatingActionButton: _cartItems.isNotEmpty
          ? FloatingActionButton.extended(
              onPressed: () {
                HapticFeedback.mediumImpact();
                _navigateToCart();
              },
              backgroundColor: Theme.of(context).primaryColor,
              icon: const Icon(Icons.shopping_cart, color: Colors.white),
              label: Text(
                '${'cart'.tr} (${_cartItems.length})',
                style: textMedium.copyWith(color: Colors.white),
              ),
            )
          : null,
    );
  }

  Widget _buildOfflineBanner(BuildContext context) {
    return Column(
      children: [
        Container(
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
        ),
        Expanded(child: _buildBody(context)),
      ],
    );
  }

  Widget _buildBody(BuildContext context) {
    return Column(
      children: [
        _buildSearchBar(context),
        _buildCategoryFilter(context),
        Expanded(
          child: _isLoading
              ? Center(child: CircularProgressIndicator(color: Theme.of(context).primaryColor))
              : _products.isEmpty
                  ? _buildEmptyState(context)
                  : _buildProductGrid(context),
        ),
      ],
    );
  }

  Widget _buildSearchBar(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      child: TextField(
        controller: _searchController,
        decoration: InputDecoration(
          hintText: 'search_products'.tr,
          prefixIcon: const Icon(Icons.search),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          ),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: Dimensions.paddingSizeDefault,
            vertical: Dimensions.paddingSizeSmall,
          ),
        ),
      ),
    );
  }

  Widget _buildCategoryFilter(BuildContext context) {
    return SizedBox(
      height: 40,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeDefault),
        itemCount: _categories.length,
        itemBuilder: (context, index) {
          final category = _categories[index];
          final isSelected = category == _selectedCategory;
          return Padding(
            padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
            child: FilterChip(
              label: Text(category.tr),
              selected: isSelected,
              onSelected: (selected) {
                HapticFeedback.selectionClick();
                setState(() => _selectedCategory = category);
              },
              selectedColor: Theme.of(context).primaryColor.withValues(alpha: 0.2),
              checkmarkColor: Theme.of(context).primaryColor,
            ),
          );
        },
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.store_outlined,
            size: 80,
            color: Theme.of(context).hintColor.withValues(alpha: 0.3),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text(
            'no_products_available'.tr,
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).hintColor,
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          Text(
            'check_back_later'.tr,
            style: textRegular.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProductGrid(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 0.75,
        crossAxisSpacing: Dimensions.paddingSizeSmall,
        mainAxisSpacing: Dimensions.paddingSizeSmall,
      ),
      itemCount: _products.length,
      itemBuilder: (context, index) => _buildProductCard(context, _products[index]),
    );
  }

  Widget _buildProductCard(BuildContext context, Map<String, dynamic> product) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 3,
            child: Container(
              decoration: BoxDecoration(
                color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(Dimensions.radiusDefault),
                  topRight: Radius.circular(Dimensions.radiusDefault),
                ),
              ),
              child: Center(
                child: Icon(
                  Icons.inventory_2_outlined,
                  size: 40,
                  color: Theme.of(context).hintColor,
                ),
              ),
            ),
          ),
          Expanded(
            flex: 2,
            child: Padding(
              padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product['name'] ?? '',
                    style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Spacer(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '\$${product['price'] ?? '0.00'}',
                        style: textBold.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: Theme.of(context).primaryColor,
                        ),
                      ),
                      InkWell(
                        onTap: () {
                          HapticFeedback.mediumImpact();
                          _addToCart(product);
                        },
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: Theme.of(context).primaryColor,
                            borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                          ),
                          child: const Icon(Icons.add, color: Colors.white, size: 18),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _addToCart(Map<String, dynamic> product) {
    setState(() {
      final existingIndex = _cartItems.indexWhere((item) => item['id'] == product['id']);
      if (existingIndex >= 0) {
        _cartItems[existingIndex]['quantity'] = (_cartItems[existingIndex]['quantity'] ?? 1) + 1;
      } else {
        _cartItems.add(<String, dynamic>{...product, 'quantity': 1});
      }
    });
  }

  void _navigateToCart() {
    Get.to(() => MartCartScreen(cartItems: _cartItems));
  }
}

class MartCartScreen extends StatefulWidget {
  final List<Map<String, dynamic>> cartItems;

  const MartCartScreen({super.key, required this.cartItems});

  @override
  State<MartCartScreen> createState() => _MartCartScreenState();
}

class _MartCartScreenState extends State<MartCartScreen> {
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();
  bool _isOrdering = false;

  double get _totalAmount {
    double total = 0;
    for (final item in widget.cartItems) {
      total += (item['price'] as num? ?? 0) * (item['quantity'] as int? ?? 1);
    }
    return total;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'cart'.tr, regularAppbar: true),
      body: widget.cartItems.isEmpty
          ? _buildEmptyCart(context)
          : Column(
              children: [
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    itemCount: widget.cartItems.length,
                    itemBuilder: (context, index) => _buildCartItem(context, index),
                  ),
                ),
                _buildOrderSummary(context),
              ],
            ),
    );
  }

  Widget _buildEmptyCart(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.shopping_cart_outlined,
            size: 80,
            color: Theme.of(context).hintColor.withValues(alpha: 0.3),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text(
            'cart_is_empty'.tr,
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeLarge,
              color: Theme.of(context).hintColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCartItem(BuildContext context, int index) {
    final item = widget.cartItems[index];
    return Card(
      margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
      child: ListTile(
        leading: Container(
          width: 50,
          height: 50,
          decoration: BoxDecoration(
            color: Theme.of(context).hintColor.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
          ),
          child: Icon(Icons.inventory_2_outlined, color: Theme.of(context).hintColor),
        ),
        title: Text(item['name'] ?? '', style: textMedium),
        subtitle: Text('\$${item['price'] ?? '0.00'}', style: textRegular.copyWith(
          color: Theme.of(context).primaryColor,
        )),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              onPressed: () {
                setState(() {
                  if ((item['quantity'] ?? 1) > 1) {
                    item['quantity'] = (item['quantity'] ?? 1) - 1;
                  } else {
                    widget.cartItems.removeAt(index);
                  }
                });
              },
              icon: const Icon(Icons.remove_circle_outline),
            ),
            Text('${item['quantity'] ?? 1}', style: textMedium),
            IconButton(
              onPressed: () {
                setState(() {
                  item['quantity'] = (item['quantity'] ?? 1) + 1;
                });
              },
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderSummary(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _addressController,
            decoration: InputDecoration(
              hintText: 'delivery_address'.tr,
              prefixIcon: const Icon(Icons.location_on_outlined),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeSmall),
          TextField(
            controller: _notesController,
            decoration: InputDecoration(
              hintText: 'order_notes'.tr,
              prefixIcon: const Icon(Icons.notes),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
              ),
            ),
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('total'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
              Text(
                '\$${_totalAmount.toStringAsFixed(2)}',
                style: textBold.copyWith(
                  fontSize: Dimensions.fontSizeLarge,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ],
          ),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isOrdering ? null : () {
                HapticFeedback.mediumImpact();
                _placeOrder();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Theme.of(context).primaryColor,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
                ),
              ),
              child: _isOrdering
                  ? const SizedBox(
                      width: 24, height: 24,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Text('place_order'.tr, style: textBold.copyWith(
                      fontSize: Dimensions.fontSizeDefault,
                      color: Colors.white,
                    )),
            ),
          ),
        ],
      ),
    );
  }

  void _placeOrder() {
    if (_addressController.text.isEmpty) {
      Get.snackbar('error'.tr, 'please_enter_delivery_address'.tr);
      return;
    }
    setState(() => _isOrdering = true);
    Future.delayed(const Duration(seconds: 2), () {
      if (mounted) {
        setState(() => _isOrdering = false);
        Get.back();
        Get.snackbar('success'.tr, 'order_placed_successfully'.tr);
      }
    });
  }
}
