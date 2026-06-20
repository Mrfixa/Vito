import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';

/// Renders a visual catalog of the design system (color tokens, spacing,
/// common component shapes) to PNG goldens so we get *visual* feedback on the
/// UI in CI without a local emulator.
///
/// Run with `flutter test --update-goldens test/ui_catalog_golden_test.dart`
/// (the CI "UI Goldens" workflow does this) and download the produced PNG
/// artifact. Goldens are always regenerated, so this never fails on pixel diffs.
///
/// NOTE: the Flutter test environment uses a placeholder font, so text glyphs
/// render as filled boxes — colors, spacing and layout are accurate. A follow-up
/// can load the app font (FontLoader / golden_toolkit) to make labels legible.
void main() {
  Widget _swatch(String name, Color color) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Dimensions.paddingSizeExtraSmall),
      child: Row(
        children: [
          Container(
            width: 72,
            height: 44,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(Dimensions.radiusSmall),
              border: Border.all(color: Colors.black12),
            ),
          ),
          const SizedBox(width: Dimensions.paddingSizeDefault),
          Expanded(
            child: Text(
              name,
              style: const TextStyle(fontSize: 18, color: Colors.black87),
            ),
          ),
        ],
      ),
    );
  }

  testWidgets('design-system catalog golden', (WidgetTester tester) async {
    tester.view.physicalSize = const Size(1080, 1700);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final tokens = <String, Color>{
      'rideService': AppColors.rideService,
      'parcelService': AppColors.parcelService,
      'offlineWarning': AppColors.offlineWarning,
      'successGreen': AppColors.successGreen,
      'ratingAmber': AppColors.ratingAmber,
      'shimmerBaseLight': AppColors.shimmerBaseLight,
      'shimmerHighlightLight': AppColors.shimmerHighlightLight,
      'shimmerBaseDark': AppColors.shimmerBaseDark,
      'shimmerHighlightDark': AppColors.shimmerHighlightDark,
    };

    await tester.pumpWidget(
      MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: ThemeData(useMaterial3: true, primaryColor: AppColors.rideService),
        home: Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Vito Design Tokens',
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: Colors.black),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeLarge),
                  for (final entry in tokens.entries) _swatch(entry.key, entry.value),
                  const SizedBox(height: Dimensions.paddingSizeLarge),
                  Row(
                    children: [
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: AppColors.rideService),
                        onPressed: () {},
                        child: const Text('Primary'),
                      ),
                      const SizedBox(width: Dimensions.paddingSizeDefault),
                      OutlinedButton(onPressed: () {}, child: const Text('Secondary')),
                    ],
                  ),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.star, color: AppColors.ratingAmber),
                      title: const Text('Sample card'),
                      subtitle: const Text('subtitle text'),
                      trailing: const Icon(Icons.chevron_right),
                    ),
                  ),
                  const SizedBox(height: Dimensions.paddingSizeDefault),
                  Row(
                    children: const [
                      Icon(Icons.check_circle, color: AppColors.successGreen),
                      SizedBox(width: Dimensions.paddingSizeSmall),
                      Icon(Icons.wifi_off, color: AppColors.offlineWarning),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('goldens/ui_catalog.png'),
    );
  });
}
