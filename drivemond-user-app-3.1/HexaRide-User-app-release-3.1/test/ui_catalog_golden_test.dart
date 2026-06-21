import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ride_sharing_user_app/common_widgets/button_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/card_widget.dart';
import 'package:ride_sharing_user_app/common_widgets/skeleton_widget.dart';
import 'package:ride_sharing_user_app/theme/light_theme.dart';
import 'package:ride_sharing_user_app/util/app_colors.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

/// Renders a *legible* visual catalog of the design system + real shared
/// components to a PNG golden, so we can judge and iterate UI quality in CI
/// without a local emulator.
///
/// The bundled SF Pro Text fonts are loaded via [FontLoader] so text renders
/// for real (not as boxes). The CI "UI Goldens" workflow runs this with
/// `--update-goldens` and uploads `test/goldens/ui_catalog.png` as an artifact.
///
/// DI-coupled widgets (e.g. CustomTextField calls Get.find<LocalizationController>)
/// are represented by faithful inline mocks; pure widgets like ButtonWidget are
/// rendered for real.
Future<void> _loadAppFonts() async {
  final loader = FontLoader('SFProText');
  for (final path in const [
    'assets/font/sf-pro-text-regular.ttf',
    'assets/font/sf-pro-text-medium.ttf',
    'assets/font/sf-pro-text-semibold.ttf',
    'assets/font/sf-pro-text-bold.ttf',
  ]) {
    final Uint8List bytes = File(path).readAsBytesSync();
    loader.addFont(Future<ByteData>.value(ByteData.sublistView(bytes)));
  }
  await loader.load();
}

Widget _sectionTitle(String text) => Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 10),
      child: Text(text, style: textBold.copyWith(fontSize: 18, color: const Color(0xFF1D2D2B))),
    );

Widget _swatch(String name, Color color) => Container(
      width: 150,
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.black12),
      ),
      child: Row(children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8)),
        ),
        const SizedBox(width: 8),
        Expanded(child: Text(name, style: textMedium.copyWith(fontSize: 11, color: const Color(0xFF48615E)))),
      ]),
    );

Widget _vehicleCard(String name, String eta, String price, IconData icon, bool selected) => Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: selected ? const Color(0xFFF5B800) : Colors.black12, width: selected ? 2 : 1),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Row(children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(color: const Color(0xFFFFF6DA), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: const Color(0xFFD4A000)),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(name, style: textSemiBold.copyWith(fontSize: 15, color: const Color(0xFF1D2D2B))),
            const SizedBox(height: 2),
            Text('$eta away', style: textRegular.copyWith(fontSize: 12, color: const Color(0xFF6B7675))),
          ]),
        ),
        Text(price, style: textBold.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),
      ]),
    );

Widget _driverCard() => Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 14, offset: const Offset(0, 4))],
      ),
      child: Row(children: [
        const CircleAvatar(radius: 26, backgroundColor: Color(0xFFE8EDF0), child: Icon(Icons.person, color: Color(0xFF48615E))),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('James Carter', style: textSemiBold.copyWith(fontSize: 15, color: const Color(0xFF1D2D2B))),
            const SizedBox(height: 3),
            Row(children: [
              const Icon(Icons.star, size: 14, color: AppColors.ratingAmber),
              const SizedBox(width: 3),
              Text('4.9 · Toyota Vios · ABC-123', style: textRegular.copyWith(fontSize: 12, color: const Color(0xFF6B7675))),
            ]),
          ]),
        ),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: const BoxDecoration(color: Color(0xFFEAF7EC), shape: BoxShape.circle),
          child: const Icon(Icons.call, size: 18, color: AppColors.successGreen),
        ),
      ]),
    );

Widget _inputMock(String hint, {bool focused = false}) => Container(
      height: 50,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: focused ? Colors.white : const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: focused ? const Color(0xFFF5B800) : Colors.black12, width: focused ? 1.5 : 1),
      ),
      alignment: Alignment.centerLeft,
      child: Text(hint, style: textRegular.copyWith(fontSize: 14, color: const Color(0xFF9F9F9F))),
    );

void main() {
  testWidgets('component gallery golden', (WidgetTester tester) async {
    await _loadAppFonts();
    tester.view.physicalSize = const Size(1120, 2280);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: lightTheme,
      home: Scaffold(
        backgroundColor: const Color(0xFFF4F5F7),
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(Dimensions.paddingSizeLarge),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Vito — UI Catalog', style: textBold.copyWith(fontSize: 26, color: const Color(0xFF1D2D2B))),
              Text('Shared components · ride-booking patterns', style: textRegular.copyWith(fontSize: 13, color: const Color(0xFF6B7675))),

              _sectionTitle('Buttons (ButtonWidget)'),
              Row(children: [
                ButtonWidget(buttonText: 'Confirm', width: 150, onPressed: () {}),
                const SizedBox(width: 10),
                ButtonWidget(buttonText: 'Cancel', width: 150, transparent: true, showBorder: true, textColor: const Color(0xFF1D2D2B), onPressed: () {}),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                ButtonWidget(buttonText: 'Disabled', width: 150, onPressed: null),
                const SizedBox(width: 10),
                ButtonWidget(buttonText: 'Book ride', width: 150, icon: Icons.local_taxi, onPressed: () {}),
              ]),
              const SizedBox(height: 10),
              ButtonWidget(buttonText: 'Loading…', width: 320, isLoading: true, onPressed: () {}),

              _sectionTitle('Card surface (CardWidget)'),
              CardWidget(
                child: Row(children: [
                  const Icon(Icons.receipt_long, color: Color(0xFFD4A000)),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Trip receipt', style: textSemiBold.copyWith(fontSize: 15, color: const Color(0xFF1D2D2B))),
                    Text('Rounded surface · soft shadow', style: textRegular.copyWith(fontSize: 12, color: const Color(0xFF6B7675))),
                  ])),
                  Text('\$12.50', style: textBold.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),
                ]),
              ),

              _sectionTitle('Typography (SF Pro Text)'),
              Text('Regular — find your ride', style: textRegular.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),
              Text('Medium — where to?', style: textMedium.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),
              Text('SemiBold — driver found', style: textSemiBold.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),
              Text('Bold — \$12.50', style: textBold.copyWith(fontSize: 16, color: const Color(0xFF1D2D2B))),

              _sectionTitle('Inputs'),
              _inputMock('Pickup location'),
              const SizedBox(height: 10),
              _inputMock('Where to?', focused: true),

              _sectionTitle('Color tokens'),
              Wrap(spacing: 10, runSpacing: 10, children: [
                _swatch('primary', const Color(0xFFF5B800)),
                _swatch('successGreen', AppColors.successGreen),
                _swatch('ratingAmber', AppColors.ratingAmber),
                _swatch('rideService', AppColors.rideService),
                _swatch('parcelService', AppColors.parcelService),
                _swatch('offlineWarning', AppColors.offlineWarning),
              ]),

              _sectionTitle('Ride patterns'),
              _vehicleCard('Vito Go', '3 min', '\$8.40', Icons.directions_car, true),
              _vehicleCard('Vito XL', '5 min', '\$12.90', Icons.airport_shuttle, false),
              const SizedBox(height: 8),
              _driverCard(),

              _sectionTitle('States'),
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Column(children: [
                    const Icon(Icons.search_off, size: 34, color: Color(0xFFBABFC4)),
                    const SizedBox(height: 6),
                    Text('No rides yet', style: textMedium.copyWith(fontSize: 13, color: const Color(0xFF6B7675))),
                  ]),
                ),
                Expanded(
                  child: Column(children: [
                    Container(height: 12, width: 120, decoration: BoxDecoration(color: AppColors.shimmerBaseLight, borderRadius: BorderRadius.circular(6))),
                    const SizedBox(height: 8),
                    Container(height: 12, width: 80, decoration: BoxDecoration(color: AppColors.shimmerBaseLight, borderRadius: BorderRadius.circular(6))),
                  ]),
                ),
                Expanded(
                  child: Column(children: [
                    const Icon(Icons.wifi_off, size: 34, color: AppColors.offlineWarning),
                    const SizedBox(height: 6),
                    Text('Tap to retry', style: textMedium.copyWith(fontSize: 13, color: const Color(0xFF6B7675))),
                  ]),
                ),
              ]),

              _sectionTitle('Loading skeleton'),
              Row(children: const [
                SkeletonWidget(width: 48, height: 48, radius: 12),
                SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  SkeletonWidget(width: double.infinity, height: 12),
                  SizedBox(height: 8),
                  SkeletonWidget(width: 160, height: 12),
                ])),
              ]),
            ]),
          ),
        ),
      ),
    ));
    // Fixed pump (not pumpAndSettle) — the Shimmer skeleton animates forever.
    await tester.pump(const Duration(milliseconds: 300));

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/ui_catalog.png'));
  });
}
