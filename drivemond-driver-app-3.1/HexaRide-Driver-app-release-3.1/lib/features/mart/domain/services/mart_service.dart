import 'package:ride_sharing_user_app/features/mart/domain/repositories/mart_repository_interface.dart';
import 'package:ride_sharing_user_app/features/mart/domain/services/mart_service_interface.dart';

class MartService implements MartServiceInterface {
  final MartRepositoryInterface martRepositoryInterface;
  MartService({required this.martRepositoryInterface});

  @override
  Future getPendingOrders({int limit = 20}) async =>
      await martRepositoryInterface.getPendingOrders(limit: limit);

  @override
  Future getMyOrders({int limit = 20}) async => await martRepositoryInterface.getMyOrders(limit: limit);

  @override
  Future getOrderDetails(String id) async => await martRepositoryInterface.getOrderDetails(id);

  @override
  Future acceptOrder(String orderId) async => await martRepositoryInterface.acceptOrder(orderId);

  @override
  Future updateStatus(String orderId, String status, {String? reason}) async =>
      await martRepositoryInterface.updateStatus(orderId, status, reason: reason);
}
