import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/features/notification/controllers/notification_controller.dart';
import 'package:ride_sharing_user_app/features/notification/widgets/notification_card.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/features/notification/widgets/notification_shimmer.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/no_data_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/paginated_list_widget.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({super.key});

  @override
  State<NotificationScreen> createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  ScrollController scrollController = ScrollController();

  @override
  void initState() {
    Get.find<NotificationController>().getNotificationList(1);
    super.initState();
  }

  @override
  void dispose() {
    scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BodyWidget(
      appBar:  AppBarWidget(title: 'notification'.tr, showBackButton: false,),
      body: Padding(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          Expanded(child: GetBuilder<NotificationController>(builder: (notificationController) {
            return notificationController.notificationModel != null ?
            (notificationController.notificationModel!.data != null && notificationController.notificationModel!.data!.isNotEmpty) ?
            RefreshIndicator(
              onRefresh: () async => await notificationController.getNotificationList(1),
              child: SingleChildScrollView(controller: scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              child: PaginatedListWidget(
                scrollController: scrollController,
                totalSize: notificationController.notificationModel!.totalSize,
                offset: (notificationController.notificationModel != null && notificationController.notificationModel!.offset != null) ?
                (() { try { return int.parse(notificationController.notificationModel!.offset.toString()); } catch (e) { debugPrint('Notification offset parse error: $e'); return 0; } })() : null,
                onPaginate: (int? offset) async {
                  await notificationController.getNotificationList(offset!);
                },

                itemView: ListView.builder(
                  itemCount: notificationController.notificationModel!.data!.length,
                  padding: const EdgeInsets.all(0),
                  physics: const NeverScrollableScrollPhysics(),
                  shrinkWrap: true,
                  itemBuilder: (BuildContext context, int index) {
                    return NotificationCard(
                      previousNotification: index == 0 ? null : notificationController.notificationModel!.data![index - 1],
                      notification: notificationController.notificationModel!.data![index],
                      nextNotification: (index == notificationController.notificationModel!.data!.length-1) ? null : notificationController.notificationModel!.data![index + 1],
                      index: index,
                    );
                  },
                ),
              ),
            )) :
            const NoDataWidget(title: 'no_notification_found') :
            const NotificationShimmer();
          })),

          Container(height: 70),

        ]),
      ),
    );
  }
}
