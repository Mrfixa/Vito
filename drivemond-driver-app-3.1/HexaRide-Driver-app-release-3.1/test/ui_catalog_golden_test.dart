import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ride_sharing_user_app/theme/light_theme.dart';
import 'package:ride_sharing_user_app/util/dimensions.dart';
import 'package:ride_sharing_user_app/util/styles.dart';

/// Driver-app UI catalog golden — legible (SF Pro loaded) reference of shared
/// components + trip patterns, rendered to a PNG artifact so UI quality can be
/// judged in CI without an emulator. Colors are inlined (driver app has no
/// AppColors token file) to keep this self-contained.
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

const _ink = Color(0xFF1D2D2B);
const _muted = Color(0xFF6B7675);
const _primary = Color(0xFFF5B800);
const _success = Color(0xFF4CAF50);
const _amber = Color(0xFFFFC107);

Widget _sectionTitle(String text) => Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 10),
      child: Text(text, style: textBold.copyWith(fontSize: 18, color: _ink)),
    );

// Inline button mock (the real ButtonWidget wraps a Center that fills both axes,
// which is unrenderable in isolation — that build is fixed in the component pass).
Widget _btn(String label, {bool outline = false, bool disabled = false, IconData? icon}) => Container(
      height: 46,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: disabled ? const Color(0xFFBABFC4) : outline ? Colors.transparent : _primary,
        borderRadius: BorderRadius.circular(12),
        border: outline ? Border.all(color: _primary, width: 1.4) : null,
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) ...[Icon(icon, size: 18, color: outline ? _primary : Colors.white), const SizedBox(width: 6)],
        Text(label, style: textSemiBold.copyWith(fontSize: 15, color: disabled ? Colors.white : outline ? _ink : Colors.white)),
      ]),
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
        Container(width: 34, height: 34, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8))),
        const SizedBox(width: 8),
        Expanded(child: Text(name, style: textMedium.copyWith(fontSize: 11, color: _muted))),
      ]),
    );

Widget _requestCard() => Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 14, offset: const Offset(0, 4))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const CircleAvatar(radius: 22, backgroundColor: Color(0xFFE8EDF0), child: Icon(Icons.person, color: Color(0xFF48615E))),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Sarah Lee', style: textSemiBold.copyWith(fontSize: 15, color: _ink)),
              Row(children: [
                const Icon(Icons.star, size: 13, color: _amber),
                const SizedBox(width: 3),
                Text('4.8', style: textRegular.copyWith(fontSize: 12, color: _muted)),
              ]),
            ]),
          ),
          Text('\$9.20', style: textBold.copyWith(fontSize: 16, color: _ink)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          const Icon(Icons.my_location, size: 16, color: _success),
          const SizedBox(width: 8),
          Expanded(child: Text('Jl. Sudirman No. 21', style: textRegular.copyWith(fontSize: 13, color: _ink))),
        ]),
        Container(margin: const EdgeInsets.only(left: 7), width: 2, height: 14, color: Colors.black12),
        Row(children: [
          const Icon(Icons.location_on, size: 16, color: Color(0xFFE53935)),
          const SizedBox(width: 8),
          Expanded(child: Text('Plaza Indonesia, Level 2', style: textRegular.copyWith(fontSize: 13, color: _ink))),
        ]),
      ]),
    );

void main() {
  testWidgets('driver component gallery golden', (WidgetTester tester) async {
    await _loadAppFonts();
    tester.view.physicalSize = const Size(1120, 1900);
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
              Text('Vito Driver — UI Catalog', style: textBold.copyWith(fontSize: 26, color: _ink)),
              Text('Shared components · trip patterns', style: textRegular.copyWith(fontSize: 13, color: _muted)),

              _sectionTitle('Buttons'),
              Row(children: [
                Expanded(child: _btn('Accept')),
                const SizedBox(width: 10),
                Expanded(child: _btn('Decline', outline: true)),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(child: _btn('Offline', disabled: true)),
                const SizedBox(width: 10),
                Expanded(child: _btn('Navigate', icon: Icons.navigation)),
              ]),

              _sectionTitle('Typography (SF Pro Text)'),
              Text('Regular — new ride request', style: textRegular.copyWith(fontSize: 16, color: _ink)),
              Text('Medium — pick up rider', style: textMedium.copyWith(fontSize: 16, color: _ink)),
              Text('SemiBold — arriving now', style: textSemiBold.copyWith(fontSize: 16, color: _ink)),
              Text('Bold — \$9.20 earned', style: textBold.copyWith(fontSize: 16, color: _ink)),

              _sectionTitle('Color tokens'),
              Wrap(spacing: 10, runSpacing: 10, children: [
                _swatch('primary', _primary),
                _swatch('success', _success),
                _swatch('amber', _amber),
                _swatch('danger', const Color(0xFFE53935)),
              ]),

              _sectionTitle('Trip request'),
              _requestCard(),

              _sectionTitle('States'),
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.inbox, size: 34, color: Color(0xFFBABFC4)),
                    const SizedBox(height: 6),
                    Text('No requests', style: textMedium.copyWith(fontSize: 13, color: _muted)),
                  ]),
                ),
                Expanded(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Container(height: 12, width: 120, decoration: BoxDecoration(color: const Color(0xFFE0E0E0), borderRadius: BorderRadius.circular(6))),
                    const SizedBox(height: 8),
                    Container(height: 12, width: 80, decoration: BoxDecoration(color: const Color(0xFFE0E0E0), borderRadius: BorderRadius.circular(6))),
                  ]),
                ),
                Expanded(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.wifi_off, size: 34, color: Color(0xFFE53935)),
                    const SizedBox(height: 6),
                    Text('Tap to retry', style: textMedium.copyWith(fontSize: 13, color: _muted)),
                  ]),
                ),
              ]),
            ]),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/ui_catalog.png'));
  });
}
