import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/custom_pop_scope_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/no_data_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/paginated_list_view_widget.dart';
import 'package:ride_sharing_user_app/features/chat/controllers/chat_controller.dart';
import 'package:ride_sharing_user_app/features/chat/widgets/message_bubble_widget.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/localization/localization_controller.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'dart:math' as math;

class MartDriverMessageScreen extends StatefulWidget {
  final String channelId;
  final String orderId;
  final String userName;

  const MartDriverMessageScreen({
    super.key,
    required this.channelId,
    required this.orderId,
    required this.userName,
  });

  @override
  State<MartDriverMessageScreen> createState() => _MartDriverMessageScreenState();
}

class _MartDriverMessageScreenState extends State<MartDriverMessageScreen> {
  ScrollController scrollController = ScrollController();

  @override
  void initState() {
    Get.find<ChatController>().findChannelRideStatus(widget.channelId);
    Get.find<ChatController>().getConversation(widget.channelId, 1);
    Get.find<ChatController>().subscribeMartMessageChannel(widget.orderId);
    super.initState();
  }

  @override
  void dispose() {
    scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: CustomPopScopeWidget(
        child: Scaffold(
          body: GetBuilder<ChatController>(builder: (chatController) {
            return Column(children: [
              AppBarWidget(title: '${'chat_with'.tr} ${widget.userName}', regularAppbar: true),

              (chatController.messageModel != null && chatController.messageModel!.data != null)
                  ? chatController.messageModel!.data!.isNotEmpty
                      ? Expanded(
                          child: SingleChildScrollView(
                            controller: scrollController,
                            reverse: true,
                            child: Padding(
                              padding: const EdgeInsets.only(top: Dimensions.paddingSizeSmall),
                              child: PaginatedListViewWidget(
                                reverse: true,
                                scrollController: scrollController,
                                totalSize: chatController.messageModel!.totalSize,
                                offset: (chatController.messageModel != null &&
                                        chatController.messageModel!.offset != null)
                                    ? int.parse(chatController.messageModel!.offset.toString())
                                    : null,
                                onPaginate: (int? offset) async =>
                                    await chatController.getConversation(widget.channelId, offset!),
                                itemView: ListView.builder(
                                  reverse: true,
                                  itemCount: chatController.messageModel!.data!.length,
                                  padding: const EdgeInsets.all(0),
                                  physics: const NeverScrollableScrollPhysics(),
                                  shrinkWrap: true,
                                  itemBuilder: (BuildContext context, int index) {
                                    if (index != 0) {
                                      return ConversationBubbleWidget(
                                        message: chatController.messageModel!.data![index],
                                        previousMessage: chatController.messageModel!.data![index - 1],
                                        index: index,
                                        length: chatController.messageModel!.data!.length,
                                      );
                                    } else {
                                      return ConversationBubbleWidget(
                                        message: chatController.messageModel!.data![index],
                                        index: index,
                                        length: chatController.messageModel!.data!.length,
                                      );
                                    }
                                  },
                                ),
                              ),
                            ),
                          ),
                        )
                      : const Expanded(child: NoDataWidget(title: 'no_message_found'))
                  : Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [SpinKitCircle(color: Theme.of(context).primaryColor, size: 40.0)],
                      ),
                    ),

              chatController.pickedImageFile != null && chatController.pickedImageFile!.isNotEmpty
                  ? Container(
                      height: 90,
                      width: Get.width,
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        itemBuilder: (context, index) {
                          return Stack(children: [
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 5),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: SizedBox(
                                  height: 80,
                                  width: 80,
                                  child: Image.file(
                                    File(chatController.pickedImageFile![index].path),
                                    fit: BoxFit.cover,
                                  ),
                                ),
                              ),
                            ),
                            Positioned(
                              right: 5,
                              child: InkWell(
                                child: Icon(Icons.cancel_outlined,
                                    color: Theme.of(context).colorScheme.error),
                                onTap: () => chatController.pickMultipleImage(true, index: index),
                              ),
                            ),
                          ]);
                        },
                        itemCount: chatController.pickedImageFile!.length,
                      ),
                    )
                  : const SizedBox(),

              chatController.otherFile != null
                  ? Stack(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 25),
                        height: 25,
                        child: Text(chatController.otherFile!.names.toString()),
                      ),
                      Positioned(
                        top: 0,
                        right: 0,
                        child: InkWell(
                          child: Icon(Icons.cancel_outlined,
                              color: Theme.of(context).colorScheme.error),
                          onTap: () => chatController.pickOtherFile(true),
                        ),
                      ),
                    ])
                  : const SizedBox(),

              Padding(
                padding: const EdgeInsets.only(
                  left: Dimensions.paddingSizeDefault,
                  right: Dimensions.paddingSizeDefault,
                  bottom: Dimensions.paddingSizeDefault,
                ),
                child: Divider(color: Theme.of(context).hintColor.withValues(alpha: 0.15)),
              ),

              chatController.channelRideStatus
                  ? Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                      Expanded(
                        child: Container(
                          margin: const EdgeInsets.only(
                            left: Dimensions.paddingSizeSmall,
                            right: Dimensions.paddingSizeSmall,
                            bottom: Dimensions.paddingSizeExtraLarge,
                          ),
                          decoration: BoxDecoration(
                            border: Border.all(color: Theme.of(context).primaryColor),
                            color: Theme.of(context).cardColor,
                            borderRadius: const BorderRadius.all(Radius.circular(100)),
                          ),
                          child: Form(
                            key: chatController.conversationKey,
                            child: Row(children: [
                              const SizedBox(width: Dimensions.paddingSizeDefault),
                              Expanded(
                                child: TextField(
                                  cursorColor: Theme.of(context).primaryColor,
                                  minLines: 1,
                                  controller: chatController.conversationController,
                                  textCapitalization: TextCapitalization.sentences,
                                  style: textMedium.copyWith(
                                    fontSize: Dimensions.fontSizeLarge,
                                    color: Theme.of(context)
                                        .textTheme
                                        .bodyMedium!
                                        .color!
                                        .withValues(alpha: 0.8),
                                  ),
                                  keyboardType: TextInputType.multiline,
                                  maxLines: 2,
                                  decoration: InputDecoration(
                                    border: InputBorder.none,
                                    hintText: "type_here".tr,
                                    hintStyle: textRegular.copyWith(
                                      color: Theme.of(context)
                                          .textTheme
                                          .bodyMedium!
                                          .color!
                                          .withValues(alpha: 0.8),
                                      fontSize: Dimensions.fontSizeLarge,
                                    ),
                                  ),
                                  onChanged: (String newText) {},
                                ),
                              ),
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: Dimensions.paddingSizeSmall),
                                child: InkWell(
                                  onTap: () => chatController.pickMultipleImage(false),
                                  child: Image.asset(
                                    height: 20,
                                    width: 20,
                                    Images.pickImage,
                                    color: Get.isDarkMode
                                        ? Colors.white.withValues(alpha: 0.5)
                                        : Colors.black.withValues(alpha: 0.5),
                                  ),
                                ),
                              ),
                            ]),
                          ),
                        ),
                      ),
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: Theme.of(context).primaryColor),
                          color: Theme.of(context).primaryColor,
                          borderRadius: const BorderRadius.all(Radius.circular(50)),
                        ),
                        margin: EdgeInsets.only(
                          bottom: Dimensions.paddingSizeExtraLarge,
                          right: Get.find<LocalizationController>().isLtr
                              ? Dimensions.paddingSizeDefault
                              : 0,
                          left: Get.find<LocalizationController>().isLtr
                              ? 0
                              : Dimensions.paddingSizeDefault,
                        ),
                        padding: const EdgeInsets.all(Dimensions.paddingSizeSmall),
                        child: chatController.isLoading
                            ? SizedBox(
                                height: 20,
                                width: 20,
                                child: Center(child: SpinKitCircle(
                                  color: Theme.of(context).cardColor, size: 20,
                                )),
                              )
                            : chatController.isSending
                                ? SpinKitCircle(color: Theme.of(context).cardColor, size: 20)
                                : chatController.isPickedImage
                                    ? SpinKitCircle(color: Theme.of(context).primaryColor, size: 20)
                                    : InkWell(
                                        onTap: chatController.isSending ? null : () {
                                          if (chatController.conversationController.text.trim().isEmpty &&
                                              (chatController.pickedImageFile?.isEmpty ?? true) &&
                                              chatController.otherFile == null) {
                                            showCustomSnackBar('write_something'.tr, isError: true);
                                            return;
                                          }
                                          if (chatController.conversationKey.currentState?.validate() ?? false) {
                                            chatController.sendMartMessage(
                                                widget.channelId, widget.orderId);
                                          }
                                          chatController.conversationController.clear();
                                        },
                                        child: Transform(
                                          alignment: Alignment.center,
                                          transform: Get.find<LocalizationController>().isLtr
                                              ? Matrix4.rotationY(0)
                                              : Matrix4.rotationY(math.pi),
                                          child: Image.asset(
                                            Images.sendMessage,
                                            width: Dimensions.iconSizeMedium,
                                            height: Dimensions.iconSizeMedium,
                                            color: Theme.of(context).cardColor,
                                          ),
                                        ),
                                      ),
                      ),
                    ])
                  : SizedBox(
                      height: 50,
                      child: Container(
                        padding: EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
                        decoration: BoxDecoration(
                          color: Theme.of(context).hintColor.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                        ),
                        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Icon(Icons.block, color: Theme.of(context).hintColor),
                          const SizedBox(width: 5),
                          Flexible(child: Text("order_chat_unavailable".tr,
                              style: textRegular.copyWith(color: Theme.of(context).hintColor))),
                        ]),
                      ),
                    ),
            ]);
          }),
        ),
      ),
    );
  }
}
