import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';
import 'package:ride_sharing_user_app/features/parcel/controllers/parcel_controller.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/config_controller.dart';
import 'package:ride_sharing_user_app/features/wallet/widget/custom_title.dart';
import 'package:ride_sharing_user_app/common_widgets/image_widget.dart';
import 'package:shimmer/shimmer.dart';

class ParcelCategoryView extends StatelessWidget {
  final bool isDetails;
  const ParcelCategoryView({super.key,  this.isDetails = false});

  @override
  Widget build(BuildContext context) {
    return GetBuilder<ParcelController>(builder: (parcelController) {
      return Column(
        children: [

          isDetails ? const SizedBox(): CustomTitle(title: "select_your_parcel_type", color: Theme.of(context).primaryColor),

          SizedBox(height: 115, child: parcelController.parcelCategoryList != null ? parcelController.parcelCategoryList!.isNotEmpty ? ListView.builder(
            itemCount: parcelController.parcelCategoryList!.length,
            padding: EdgeInsets.zero,
            scrollDirection: Axis.horizontal,
            itemBuilder: (context, index) {
              final isSelected = parcelController.selectedParcelCategory == index;
              return  SizedBox(height: 105, width: 85, child: Stack(children: [

                InkWell(onTap: () => parcelController.updateParcelCategoryIndex(index),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [

                    Container(height: 70, width: 75,
                      clipBehavior: Clip.antiAlias,
                      decoration: BoxDecoration(borderRadius: BorderRadius.circular(Dimensions.radiusSmall)),
                      child: Padding(padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                        child: ImageWidget(
                          image: '${Get.find<ConfigController>().config!.imageBaseUrl?.parcel}/${parcelController.parcelCategoryList![index].image}',
                          width: 70,height: 75,fit: BoxFit.cover))),
                    const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                    Text(parcelController.parcelCategoryList![index].name!, maxLines: 1,overflow: TextOverflow.ellipsis,
                      style: (isSelected ? textBold : textSemiBold).copyWith(
                        color: isSelected
                            ? Theme.of(context).primaryColor
                            : Theme.of(context).textTheme.bodyMedium!.color!.withValues(alpha:0.8),
                        fontSize: Dimensions.fontSizeSmall)),])),

                isSelected ? Padding(
                  padding: const EdgeInsets.only(top: Dimensions.paddingSizeExtraSmall),
                  child: Icon(Icons.check_circle, color: Theme.of(context).primaryColor),
                ) : const SizedBox(),
              ]));
            },
          ) : Center(child: Text('no_parcel_category_found'.tr)) : const ParcelCategoryShimmer()),
        ],
      );
    });
  }
}

class ParcelCategoryShimmer extends StatelessWidget {
  const ParcelCategoryShimmer({super.key});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return ListView.builder(
      itemCount: 10,
      padding: EdgeInsets.zero,
      scrollDirection: Axis.horizontal,
      itemBuilder: (context, index) {
        return  SizedBox(height: 100, width: 85, child: Stack(children: [

          Shimmer.fromColors(
            baseColor: isDark ? const Color(0xFF303030) : const Color(0xFFE0E0E0),
            highlightColor: isDark ? const Color(0xFF404040) : const Color(0xFFF5F5F5),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [

              Container(height: 70, width: 75,
                decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
                    color: isDark ? const Color(0xFF303030) : Colors.white)),
              const SizedBox(height: Dimensions.paddingSizeExtraSmall),
              Container(height: 15, width: 75, color: isDark ? const Color(0xFF303030) : Colors.white),
            ]),
          ),

        ]));
      },
    );
  }
}
