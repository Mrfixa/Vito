import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_order_tracking_screen.dart';

class MartStoreScreen extends StatefulWidget {
  const MartStoreScreen({super.key});

  @override
  State<MartStoreScreen> createState() => _MartStoreScreenState();
}

class _MartStoreScreenState extends State<MartStoreScreen> {
  final TextEditingController _searchController = TextEditingController();
  final List<Map<String, dynamic>> _products = [];
  final List<Map<String, dynamic>> _cartItems = [];
  bool _isLoading = true;
  bool _isOffline = false;
  String _selectedCategory = 'all';

  final List<String> _categories = ['all', 'food', 'drinks', 'snacks', 'essentials'];

  @override
  void initState() {
    super.initState();
    _fetchProducts();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchProducts() async {
    try {
      final response = await Get.find<ApiClient>().getData(AppConstants.martProducts);
      if (!mounted) return;
      if (response.statusCode == 200 && response.body['data'] != null) {
        setState(() {
          _products.clear();
          for (final item in response.body['data']) {
            _products.add(Map<String, dynamic>.from(item));
          }
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Mart error: $e');
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
      appBar: AppBarWidget(title: 'vito_mart'.tr),
      body: _isOffline ? _buildOfflineBody(context) : _buildBody(context),
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

  Widget _buildOfflineBody(BuildContext context) {
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
              Text('you_are_offline'.tr, style: textMedium.copyWith(color: Colors.white)),
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
    final filtered = _selectedCategory == 'all'
        ? _products
        : _products.where((p) => p['category'] == _selectedCategory).toList();

    return GridView.builder(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 0.75,
        crossAxisSpacing: Dimensions.paddingSizeSmall,
        mainAxisSpacing: Dimensions.paddingSizeSmall,
      ),
      itemCount: filtered.length,
      itemBuilder: (context, index) => _buildProductCard(context, filtered[index]),
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
  final TextEditingController _promoController = TextEditingController();
  bool _isOrdering = false;
  bool _isApplyingPromo = false;
  double _discount = 0.0;
  String? _appliedPromoCode;
  double _tipAmount = 0.0;

  final List<double> _tipOptions = [0, 2, 5, 10];

  double get _subtotal {
    double total = 0;
    for (final item in widget.cartItems) {
      total += (item['price'] as num? ?? 0) * (item['quantity'] as int? ?? 1);
    }
    return total;
  }

  double get _totalAmount => _subtotal - _discount + _tipAmount;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'cart'.tr),
      body: widget.cartItems.isEmpty
          ? _buildEmptyCart(context)
          : Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
                    children: [
                      ...List.generate(widget.cartItems.length,
                          (index) => _buildCartItem(context, index)),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      _buildPromoSection(context),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      _buildTipSection(context),
                    ],
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

  Widget _buildPromoSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('promo_code'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            if (_appliedPromoCode != null) ...[
              Container(
                padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                decoration: BoxDecoration(
                  color: Colors.green.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle, color: Colors.green, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '${'promo_applied'.tr}: $_appliedPromoCode (-\$${_discount.toStringAsFixed(2)})',
                        style: textMedium.copyWith(color: Colors.green, fontSize: Dimensions.fontSizeSmall),
                      ),
                    ),
                    IconButton(
                      onPressed: () {
                        setState(() {
                          _appliedPromoCode = null;
                          _discount = 0.0;
                          _promoController.clear();
                        });
                      },
                      icon: const Icon(Icons.close, size: 18),
                    ),
                  ],
                ),
              ),
            ] else ...[
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _promoController,
                      decoration: InputDecoration(
                        hintText: 'enter_promo_code'.tr,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: Dimensions.paddingSizeSmall,
                          vertical: Dimensions.paddingSizeSmall,
                        ),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: Dimensions.paddingSizeSmall),
                  SizedBox(
                    height: 40,
                    child: ElevatedButton(
                      onPressed: _isApplyingPromo ? null : _applyPromoCode,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Theme.of(context).primaryColor,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        ),
                      ),
                      child: _isApplyingPromo
                          ? const SizedBox(width: 16, height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : Text('apply'.tr, style: textMedium.copyWith(color: Colors.white, fontSize: Dimensions.fontSizeSmall)),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildTipSection(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('tip_driver'.tr, style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              'show_appreciation'.tr,
              style: textRegular.copyWith(color: Theme.of(context).hintColor, fontSize: Dimensions.fontSizeSmall),
            ),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Row(
              children: _tipOptions.map((tip) {
                final isSelected = _tipAmount == tip;
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: GestureDetector(
                      onTap: () {
                        HapticFeedback.selectionClick();
                        setState(() => _tipAmount = tip);
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: isSelected
                              ? Theme.of(context).primaryColor
                              : Theme.of(context).primaryColor.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                          border: Border.all(
                            color: isSelected
                                ? Theme.of(context).primaryColor
                                : Theme.of(context).primaryColor.withValues(alpha: 0.3),
                          ),
                        ),
                        child: Center(
                          child: Text(
                            tip == 0 ? 'no_tip'.tr : '\$${tip.toInt()}',
                            style: textMedium.copyWith(
                              color: isSelected ? Colors.white : Theme.of(context).primaryColor,
                              fontSize: Dimensions.fontSizeSmall,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              }).toList(),
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
          _buildPriceLine('subtotal'.tr, _subtotal),
          if (_discount > 0) _buildPriceLine('discount'.tr, -_discount, isDiscount: true),
          if (_tipAmount > 0) _buildPriceLine('tip'.tr, _tipAmount),
          const Divider(),
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

  Widget _buildPriceLine(String label, double amount, {bool isDiscount = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
          Text(
            '${isDiscount ? '-' : ''}\$${amount.abs().toStringAsFixed(2)}',
            style: textMedium.copyWith(
              fontSize: Dimensions.fontSizeSmall,
              color: isDiscount ? Colors.green : null,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _applyPromoCode() async {
    final code = _promoController.text.trim();
    if (code.isEmpty) return;

    setState(() => _isApplyingPromo = true);

    try {
      final response = await Get.find<ApiClient>().postData(
        AppConstants.martApplyPromo,
        {'code': code, 'subtotal': _subtotal},
      );

      if (!mounted) return;
      if (response.statusCode == 200 && response.body['data'] != null) {
        setState(() {
          _discount = (response.body['data']['discount'] as num?)?.toDouble() ?? 0.0;
          _appliedPromoCode = code;
        });
      } else {
        Get.snackbar('error'.tr, 'invalid_promo_code'.tr);
      }
    } catch (e) {
      debugPrint('Mart error: $e');
      Get.snackbar('error'.tr, 'promo_validation_failed'.tr);
    } finally {
      if (mounted) setState(() => _isApplyingPromo = false);
    }
  }

  Future<void> _placeOrder() async {
    if (_addressController.text.isEmpty) {
      Get.snackbar('error'.tr, 'please_enter_delivery_address'.tr);
      return;
    }

    setState(() => _isOrdering = true);

    try {
      final items = widget.cartItems.map((item) => {
        'product_id': item['id'],
        'quantity': item['quantity'] ?? 1,
      }).toList();

      // Server computes the authoritative total; client sends tip and promo only
      final body = <String, dynamic>{
        'items': items,
        'delivery_address': _addressController.text,
        'notes': _notesController.text,
        if (_tipAmount > 0) 'tip_amount': _tipAmount,
        if (_appliedPromoCode != null) 'promo_code': _appliedPromoCode,
      };

      final response = await Get.find<ApiClient>().postData(
        AppConstants.martCreateOrder,
        body,
      );

      if (!mounted) return;
      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = response.body['data'];
        final orderId = data?['id'] ?? data?['order_id'] ?? '';
        Get.back();
        Get.snackbar('success'.tr, 'order_placed_successfully'.tr);
        if (orderId.toString().isNotEmpty) {
          Get.to(() => MartOrderTrackingScreen(orderId: orderId.toString()));
        }
      } else {
        final message = response.body['errors']?.first?['message'] ?? 'order_failed'.tr;
        Get.snackbar('error'.tr, message.toString());
      }
    } catch (e) {
      debugPrint('Mart error: $e');
      Get.snackbar('error'.tr, 'network_error'.tr);
    } finally {
      if (mounted) setState(() => _isOrdering = false);
    }
  }
}
