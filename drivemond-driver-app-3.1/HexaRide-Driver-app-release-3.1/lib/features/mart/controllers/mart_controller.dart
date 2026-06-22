import 'package:get/get.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/features/mart/domain/models/mart_order_model.dart';
import 'package:ride_sharing_user_app/features/mart/domain/services/mart_service_interface.dart';

class MartController extends GetxController implements GetxService {
  final MartServiceInterface martServiceInterface;
  MartController({required this.martServiceInterface});

  bool isLoading = false;
  bool isActionLoading = false;

  List<MartOrderModel> pendingOrders = [];
  List<MartOrderModel> myOrders = [];
  MartOrderModel? currentOrder;

  List<dynamic> _extractList(dynamic body) {
    final data = body is Map ? body['data'] : null;
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'];
    return const [];
  }

  Future<void> getPendingOrders({bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getPendingOrders();
    if (response.statusCode == 200) {
      pendingOrders = _extractList(response.body)
          .whereType<Map<String, dynamic>>()
          .map(MartOrderModel.fromJson)
          .toList();
    } else {
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    if (notify) update();
  }

  Future<void> getMyOrders({bool notify = true}) async {
    isLoading = true;
    if (notify) update();
    final response = await martServiceInterface.getMyOrders();
    if (response.statusCode == 200) {
      myOrders = _extractList(response.body)
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

  Future<bool> acceptOrder(String orderId) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.acceptOrder(orderId);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }

  Future<bool> updateStatus(String orderId, String status, {String? reason}) async {
    isActionLoading = true;
    update();
    final response = await martServiceInterface.updateStatus(orderId, status, reason: reason);
    isActionLoading = false;
    update();
    if (response.statusCode == 200) {
      return true;
    }
    ApiChecker.checkApi(response);
    return false;
  }
}
