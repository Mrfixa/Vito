import 'package:ride_sharing_user_app/interface/repository_interface.dart';

abstract class MartRepositoryInterface implements RepositoryInterface {
  Future<dynamic> getPendingOrders({int limit});
  Future<dynamic> getMyOrders({int limit});
  Future<dynamic> getOrderDetails(String id);
  Future<dynamic> acceptOrder(String orderId);
  Future<dynamic> updateStatus(String orderId, String status, {String? reason});
}
