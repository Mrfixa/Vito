abstract class MartServiceInterface {
  Future<dynamic> getPendingOrders({int limit});
  Future<dynamic> getMyOrders({int limit});
  Future<dynamic> getOrderDetails(String id);
  Future<dynamic> acceptOrder(String orderId);
  Future<dynamic> updateStatus(String orderId, String status, {String? reason});
}
