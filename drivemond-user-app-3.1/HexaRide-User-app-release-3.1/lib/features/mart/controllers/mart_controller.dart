import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_category_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_product_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/services/mart_service_interface.dart';

class MartController extends GetxController implements GetxService {
  final MartServiceInterface martServiceInterface;
  MartController({required this.martServiceInterface});

  bool isLoading = false;
  bool isActionLoading = false;

  List<MartProductModel> products = [];
  List<MartCategoryModel> categories = [];
  String selectedCategory = 'all';

  List<MartOrderModel> orders = [];
  MartOrderModel? currentOrder;
  MartProductModel? productDetails;

  /// Helper to extract a list payload that may be a plain list or a Laravel
  /// paginator ({data: {data: [...]}}).
  List<dynamic> _extractList(dynamic body) {
    final data = body is Map ? body['data'] : null;
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'];
    return const [];
  }

  Future<void> getProducts({String? category, String? search, bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getProducts(category: category ?? selectedCategory, search: search);
    if (response.statusCode == 200) {
      products = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartProductModel.fromJson)
          .toList();
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    if (notify) update();
  }

  Future<void> getCategories({bool notify = true}) async {
    final response = await martServiceInterface.getCategories();
    if (response.statusCode == 200) {
      categories = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartCategoryModel.fromJson)
          .toList();
    }
    if (notify) update();
  }

  void setCategory(String category) {
    selectedCategory = category;
    update();
    getProducts(category: category);
  }

  Future<MartProductModel?> getProductDetails(String id) async {
    isLoading = true;
    update();
    productDetails = null;
    final response = await martServiceInterface.getProductDetails(id);
    if (response.statusCode == 200 && response.body['data'] != null) {
      productDetails = MartProductModel.fromJson(response.body['data']);
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
    return productDetails;
  }

  Future<void> getOrders({bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getOrders();
    if (response.statusCode == 200) {
      orders = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartOrderModel.fromJson)
          .toList();
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    if (notify) update();
  }

  Future<MartOrderModel?> getOrderDetails(String id, {bool notify = true}) async {
    final response = await martServiceInterface.getOrderDetails(id);
    if (response.statusCode == 200 && response.body['data'] != null) {
      currentOrder = MartOrderModel.fromJson(response.body['data']);
      if (notify) update();
      return currentOrder;
    }
    ApiChecker.checkApi(response);
    return null;
  }

  Future<bool> cancelOrder(String id) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.cancelOrder(id);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }

  Future<bool> reviewOrder(String id, int rating, String? comment) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.reviewOrder(id, rating, comment);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }
}
