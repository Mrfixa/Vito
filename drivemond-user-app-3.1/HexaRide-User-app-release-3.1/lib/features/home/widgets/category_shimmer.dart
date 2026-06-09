import 'package:flutter/material.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/images.dart';
import 'package:ride_sharing_user_app/common_widgets/image_widget.dart';
import 'package:shimmer/shimmer.dart';

class CategoryShimmer extends StatelessWidget {
  const CategoryShimmer({super.key});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical : Dimensions.paddingSizeSmall),
      child: ListView.builder(
        itemCount: 5,
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        scrollDirection: Axis.horizontal,
        itemBuilder: (context, item) => Shimmer.fromColors(
          baseColor: isDark ? const Color(0xFF2D2D2D) : Colors.grey[300]!,
          highlightColor: isDark ? const Color(0xFF404040) : Colors.grey[100]!,
          child: Padding(
            padding: const EdgeInsets.only(right: Dimensions.paddingSizeSmall),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: Dimensions.paddingSizeSmall),
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withValues(alpha:0.07),
                borderRadius: const BorderRadius.all(Radius.circular(Dimensions.radiusLarge)),
              ),
              child: Column(
                children:  [
                   const ImageWidget(
                     image: '',
                     radius: Dimensions.radiusDefault,
                     height: 50, width: 50,
                     placeholder: Images.carPlaceholder,
                   ),
                  const SizedBox(height: Dimensions.paddingSizeSmall,),
                  Padding(
                    padding: const EdgeInsets.all(Dimensions.paddingSizeExtraSmall),
                    child: Container(width: 70, height: 5,
                      color: isDark ? const Color(0xFF303030) : Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
