import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';
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
  bool _hasError = false;
  String _selectedCategory = 'all';

  final List<String> _categories = ['all', 'food', 'drinks', 'snacks', 'essentials'];

  // B16: running total for FAB
  double get _cartTotal => _cartItems.fold(
        0.0,
        (sum, item) =>
            sum +
            (double.tryParse(item['price']?.toString() ?? '0') ?? 0.0) *
                (item['quantity'] as int? ?? 1),
      );

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

  // B17: renamed so RefreshIndicator can call it
  Future<void> _loadProducts() => _fetchProducts();

  Future<void> _fetchProducts() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _hasError = false;
      _isOffline = false;
    });
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
      // B20: distinguish network error from general error
      setState(() {
        _isOffline = true;
        _hasError = false;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBarWidget(title: 'vito_mart'.tr),
      body: _isOffline ? _buildOfflineBody(context) : _buildBody(context),
      // B16: FAB shows count + running total
      floatingActionButton: _cartItems.isNotEmpty
          ? FloatingActionButton.extended(
              onPressed: () {
                HapticFeedback.mediumImpact();
                _navigateToCart();
              },
              backgroundColor: Theme.of(context).primaryColor,
              icon: const Icon(Icons.shopping_cart, color: Colors.white),
              label: Text(
                '${'cart'.tr} (${_cartItems.length}) • \$${_cartTotal.toStringAsFixed(2)}',
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
              ? _buildShimmerGrid(context) // B18: shimmer loading
              : _hasError
                  ? _buildErrorState(context) // B20: error state
                  : _buildAnimatedContent(context), // B12: animated switcher
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

  // B12: AnimatedSwitcher keyed by category
  Widget _buildAnimatedContent(BuildContext context) {
    final filtered = _selectedCategory == 'all'
        ? _products
        : _products.where((p) => p['category'] == _selectedCategory).toList();

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 200),
      transitionBuilder: (child, animation) => FadeTransition(opacity: animation, child: child),
      child: filtered.isEmpty
          ? _buildEmptyState(context, key: ValueKey('empty_$_selectedCategory'))
          : _buildProductGrid(context, filtered, key: ValueKey('grid_$_selectedCategory')),
    );
  }

  // B18: shimmer skeleton loading grid
  Widget _buildShimmerGrid(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 0.75,
        crossAxisSpacing: Dimensions.paddingSizeSmall,
        mainAxisSpacing: Dimensions.paddingSizeSmall,
      ),
      itemCount: 6,
      itemBuilder: (context, index) => Shimmer.fromColors(
        baseColor: Colors.grey[300]!,
        highlightColor: Colors.grey[100]!,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.grey[200],
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }

  // B20: error state with retry
  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.error_outline, size: 48, color: Colors.red[300]),
          const SizedBox(height: 8),
          Text('something_went_wrong'.tr),
          TextButton(onPressed: _loadProducts, child: Text('retry'.tr)),
        ],
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context, {Key? key}) {
    return Center(
      key: key,
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

  Widget _buildProductGrid(BuildContext context, List<Map<String, dynamic>> filtered, {Key? key}) {
    // B17: RefreshIndicator on the product grid
    return RefreshIndicator(
      onRefresh: _loadProducts,
      color: Theme.of(context).primaryColor,
      child: GridView.builder(
        key: key,
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          childAspectRatio: 0.75,
          crossAxisSpacing: Dimensions.paddingSizeSmall,
          mainAxisSpacing: Dimensions.paddingSizeSmall,
        ),
        itemCount: filtered.length,
        itemBuilder: (context, index) =>
            _ProductCard(
              product: filtered[index],
              isOffline: _isOffline,
              onAdd: _addToCart,
            ),
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

// B14: Stateful product card with AnimatedScale + B13: CachedNetworkImage + B15: out-of-stock + B21: offline disable
class _ProductCard extends StatefulWidget {
  final Map<String, dynamic> product;
  final bool isOffline;
  final void Function(Map<String, dynamic>) onAdd;

  const _ProductCard({
    required this.product,
    required this.isOffline,
    required this.onAdd,
  });

  @override
  State<_ProductCard> createState() => _ProductCardState();
}

class _ProductCardState extends State<_ProductCard> {
  bool _isAdding = false;

  // B15: out-of-stock check
  bool get _isOutOfStock {
    final stock = widget.product['stock'] as int? ?? 1;
    final isActive = widget.product['is_active'] as bool? ?? true;
    return stock <= 0 || !isActive;
  }

  void _handleAdd() {
    if (widget.isOffline) {
      Get.snackbar('', 'you_are_offline'.tr);
      return;
    }
    if (_isOutOfStock) return;
    setState(() => _isAdding = true);
    HapticFeedback.mediumImpact();
    widget.onAdd(widget.product);
    Future.delayed(const Duration(milliseconds: 100), () {
      if (mounted) setState(() => _isAdding = false);
    });
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = widget.product['image'] as String?;

    // B15: wrap in Opacity when out of stock
    Widget card = Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // B13: product image
          Expanded(
            flex: 3,
            child: ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(Dimensions.radiusDefault),
                topRight: Radius.circular(Dimensions.radiusDefault),
              ),
              child: imageUrl != null && imageUrl.isNotEmpty
                  ? CachedNetworkImage(
                      imageUrl: imageUrl,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: Colors.grey[200]),
                      errorWidget: (_, __, ___) => Container(
                        color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                        child: Center(
                          child: Icon(
                            Icons.inventory_2_outlined,
                            size: 40,
                            color: Theme.of(context).hintColor,
                          ),
                        ),
                      ),
                    )
                  : Container(
                      color: Theme.of(context).hintColor.withValues(alpha: 0.1),
                      child: Center(
                        child: Icon(
                          Icons.inventory_2_outlined,
                          size: 40,
                          color: Theme.of(context).hintColor,
                        ),
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
                    widget.product['name'] ?? '',
                    style: textMedium.copyWith(fontSize: Dimensions.fontSizeDefault),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Spacer(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '\$${widget.product['price'] ?? '0.00'}',
                        style: textBold.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: Theme.of(context).primaryColor,
                        ),
                      ),
                      // B15: show out-of-stock label or B14: AnimatedScale add button
                      _isOutOfStock
                          ? Text(
                              'out_of_stock'.tr,
                              style: TextStyle(color: Colors.red[400], fontSize: 12),
                            )
                          : AnimatedScale(
                              scale: _isAdding ? 0.88 : 1.0,
                              duration: const Duration(milliseconds: 100),
                              child: InkWell(
                                onTap: widget.isOffline ? _handleAdd : _handleAdd,
                                child: Opacity(
                                  opacity: widget.isOffline ? 0.5 : 1.0,
                                  child: Container(
                                    padding: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).primaryColor,
                                      borderRadius:
                                          BorderRadius.circular(Dimensions.radiusSmall),
                                    ),
                                    child: const Icon(Icons.add, color: Colors.white, size: 18),
                                  ),
                                ),
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

    // B15: dim entire card when out of stock
    if (_isOutOfStock) {
      card = Opacity(opacity: 0.5, child: card);
    }

    return card;
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

  // B23: delivery fee constant
  static const double _kDeliveryFee = 2.00;

  // B25: payment method state
  String _paymentMethod = 'cash';

  // B27: checkout error state
  String? _checkoutError;

  final List<double> _tipOptions = [0, 2, 5, 10];

  double get _subtotal {
    double total = 0;
    for (final item in widget.cartItems) {
      final price = double.tryParse(item['price']?.toString() ?? '0') ?? 0.0;
      total += price * (item['quantity'] as int? ?? 1);
    }
    return total;
  }

  // B23: include delivery fee in total
  double get _totalAmount => _subtotal - _discount + _tipAmount + _kDeliveryFee;

  @override
  void dispose() {
    _addressController.dispose();
    _notesController.dispose();
    _promoController.dispose();
    super.dispose();
  }

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
                      // B22: Dismissible cart items
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

  // B22: Dismissible + product image in cart
  Widget _buildCartItem(BuildContext context, int index) {
    final item = widget.cartItems[index];
    final imageUrl = item['image'] as String?;

    return Dismissible(
      key: Key(item['id']?.toString() ?? item['product_id']?.toString() ?? '$index'),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 16),
        color: Colors.red[400],
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      onDismissed: (_) {
        setState(() {
          final id = item['id'];
          widget.cartItems.removeWhere((e) => e['id'] == id);
        });
      },
      child: Card(
        margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeSmall),
        child: ListTile(
          leading: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Theme.of(context).hintColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
            ),
            child: imageUrl != null && imageUrl.isNotEmpty
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    child: CachedNetworkImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: Colors.grey[200]),
                      errorWidget: (_, __, ___) =>
                          Icon(Icons.inventory_2_outlined, color: Theme.of(context).hintColor),
                    ),
                  )
                : Icon(Icons.inventory_2_outlined, color: Theme.of(context).hintColor),
          ),
          title: Text(item['name'] ?? '', style: textMedium),
          subtitle: Text(
            '\$${item['price'] ?? '0.00'}',
            style: textRegular.copyWith(color: Theme.of(context).primaryColor),
          ),
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
                  final current = item['quantity'] as int? ?? 1;
                  if (current < 100) {
                    setState(() {
                      item['quantity'] = current + 1;
                    });
                  }
                },
                icon: const Icon(Icons.add_circle_outline),
              ),
            ],
          ),
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
                        style: textMedium.copyWith(
                            color: Colors.green, fontSize: Dimensions.fontSizeSmall),
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
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white))
                          : Text('apply'.tr,
                              style: textMedium.copyWith(
                                  color: Colors.white,
                                  fontSize: Dimensions.fontSizeSmall)),
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
            Text('tip_driver'.tr,
                style: textBold.copyWith(fontSize: Dimensions.fontSizeDefault)),
            const SizedBox(height: Dimensions.paddingSizeSmall),
            Text(
              'show_appreciation'.tr,
              style: textRegular.copyWith(
                  color: Theme.of(context).hintColor,
                  fontSize: Dimensions.fontSizeSmall),
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
                              color:
                                  isSelected ? Colors.white : Theme.of(context).primaryColor,
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

          // B25: payment method selector
          Align(
            alignment: Alignment.centerLeft,
            child: Text('payment_method'.tr,
                style: textBold.copyWith(fontSize: 14)),
          ),
          const SizedBox(height: 4),
          ...['cash', 'card', 'wallet'].map((method) => RadioListTile<String>(
                value: method,
                groupValue: _paymentMethod,
                onChanged: (v) => setState(() => _paymentMethod = v!),
                title: Text(method == 'cash'
                    ? 'cash_on_delivery'.tr
                    : method == 'card'
                        ? 'card'.tr
                        : 'wallet'.tr),
                contentPadding: EdgeInsets.zero,
                dense: true,
              )),
          const SizedBox(height: Dimensions.paddingSizeSmall),

          // Price breakdown
          _buildPriceLine('subtotal'.tr, _subtotal),
          // B23: delivery fee line
          _buildPriceLine('delivery_fee'.tr, _kDeliveryFee),
          if (_discount > 0) _buildPriceLine('discount'.tr, -_discount, isDiscount: true),
          if (_tipAmount > 0) _buildPriceLine('tip'.tr, _tipAmount),
          const Divider(),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('total'.tr,
                  style: textBold.copyWith(fontSize: Dimensions.fontSizeLarge)),
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

          // B27: checkout error banner
          if (_checkoutError != null)
            Container(
              padding: const EdgeInsets.all(8),
              margin: const EdgeInsets.only(bottom: 8),
              decoration: BoxDecoration(
                  color: Colors.red[50],
                  borderRadius: BorderRadius.circular(8)),
              child: Row(
                children: [
                  Icon(Icons.error_outline, color: Colors.red[700], size: 16),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _checkoutError!,
                      style: TextStyle(color: Colors.red[700], fontSize: 12),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, size: 16),
                    onPressed: () => setState(() => _checkoutError = null),
                  ),
                ],
              ),
            ),

          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isOrdering
                  ? null
                  : () {
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
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : Text('place_order'.tr,
                      style: textBold.copyWith(
                          fontSize: Dimensions.fontSizeDefault,
                          color: Colors.white)),
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
          Text(label,
              style: textRegular.copyWith(fontSize: Dimensions.fontSizeSmall)),
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
          _discount =
              (response.body['data']['discount'] as num?)?.toDouble() ?? 0.0;
          _appliedPromoCode = code;
          _promoController.clear();
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

    setState(() {
      _isOrdering = true;
      _checkoutError = null;
    });

    try {
      final items = widget.cartItems
          .map((item) => {
                'product_id': item['id'],
                'quantity': item['quantity'] ?? 1,
              })
          .toList();

      // Server computes the authoritative total; client sends tip and promo only
      final body = <String, dynamic>{
        'items': items,
        'delivery_address': _addressController.text,
        'notes': _notesController.text,
        'payment_method': _paymentMethod,
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
        final message =
            response.body['errors']?.first?['message'] ?? 'order_failed'.tr;
        setState(() => _checkoutError = message.toString());
      }
    } catch (e) {
      debugPrint('Mart error: $e');
      // B27: set checkout error state
      if (mounted) setState(() => _checkoutError = 'checkout_error'.tr);
    } finally {
      if (mounted) setState(() => _isOrdering = false);
    }
  }
}
