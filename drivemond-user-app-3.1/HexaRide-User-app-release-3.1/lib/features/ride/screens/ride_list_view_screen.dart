import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shimmer/shimmer.dart';
import 'package:ride_sharing_user_app/common_widgets/app_bar_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/body_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/no_data_widget.dart';
import 'package:ride_sharing_user_app/features/ride/controllers/ride_controller.dart';
import 'package:ride_sharing_user_app/features/ride/widgets/ride_item_widget.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';

class RideListViewScreen extends StatefulWidget {
  const RideListViewScreen({super.key});

  @override
  State<RideListViewScreen> createState() => _RideListViewScreenState();
}

class _RideListViewScreenState extends State<RideListViewScreen> {
  bool _loading = false;
  bool _error = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = false;
    });
    try {
      await Get.find<RideController>().getRunningRideList();
    } catch (_) {
      if (mounted) setState(() => _error = true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Scaffold(
        body: BodyWidget(
          appBar: AppBarWidget(title: 'ride_list_view'.tr),
          body: GetBuilder<RideController>(builder: (rideController) {
            if (_loading) return _buildShimmer(context);
            if (_error) return _buildError(context);
            final data = rideController.runningRideList?.data;
            if (data == null || data.isEmpty) {
              return const NoDataWidget(title: 'no_trip_found');
            }
            return RefreshIndicator(
              onRefresh: _load,
              child: ListView.builder(
                padding: EdgeInsets.zero,
                shrinkWrap: true,
                itemCount: data.length,
                itemBuilder: (context, index) =>
                    RideItemWidget(tripDetails: data[index], index: index),
              ),
            );
          }),
        ),
      ),
    );
  }

  Widget _buildShimmer(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Theme.of(context).brightness == Brightness.dark
          ? Colors.grey[800]!
          : Colors.grey[300]!,
      highlightColor: Theme.of(context).brightness == Brightness.dark
          ? Colors.grey[700]!
          : Colors.grey[100]!,
      child: ListView.builder(
        padding: const EdgeInsets.all(Dimensions.paddingSizeDefault),
        itemCount: 5,
        itemBuilder: (_, __) => Container(
          margin: const EdgeInsets.only(bottom: Dimensions.paddingSizeDefault),
          height: 80,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(Dimensions.radiusDefault),
          ),
        ),
      ),
    );
  }

  Widget _buildError(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.wifi_off_outlined, size: 48, color: Theme.of(context).hintColor),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          Text('something_went_wrong'.tr, style: TextStyle(color: Theme.of(context).hintColor)),
          const SizedBox(height: Dimensions.paddingSizeDefault),
          TextButton(onPressed: _load, child: Text('retry'.tr)),
        ],
      ),
    );
  }
}
