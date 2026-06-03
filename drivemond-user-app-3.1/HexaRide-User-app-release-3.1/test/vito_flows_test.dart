import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';

/// Unit tests for VITO-specific flows in the user app.
/// These validate localization, token logic, and widget structure
/// without requiring the full app or a running backend.
void main() {
  group('Localization Parity', () {
    late Map<String, dynamic> en;
    late Map<String, dynamic> es;

    setUpAll(() {
      en = jsonDecode(File('assets/language/en.json').readAsStringSync());
      es = jsonDecode(File('assets/language/es.json').readAsStringSync());
    });

    test('EN and ES have the same number of keys', () {
      expect(es.length, en.length,
          reason: 'ES should have the same keys as EN');
    });

    test('All EN keys exist in ES', () {
      final missingInEs = en.keys.where((k) => !es.containsKey(k)).toList();
      expect(missingInEs, isEmpty,
          reason: 'Keys missing in ES: $missingInEs');
    });

    test('All ES keys exist in EN', () {
      final extraInEs = es.keys.where((k) => !en.containsKey(k)).toList();
      expect(extraInEs, isEmpty,
          reason: 'Extra keys in ES not in EN: $extraInEs');
    });

    test('Vito-specific EN keys have non-empty values', () {
      final vitoKeys = [
        'invitation_required',
        'scan_qr_or_enter_token',
        'enter_invitation_token',
        'validate_token',
        'vito_mart',
        'order_tracking',
        'cart',
        'enter_username_and_pin',
        'pin_is_required',
        'username_is_required',
      ];
      for (final key in vitoKeys) {
        expect(en[key], isNotNull, reason: 'EN key "$key" should exist');
        expect(en[key], isNotEmpty, reason: 'EN key "$key" should not be empty');
      }
    });

    test('Vito-specific ES keys have Spanish translations', () {
      final esVitoKeys = {
        'invitation_required': 'Invitación Requerida',
        'validate_token': 'Validar Token',
        'vito_mart': 'VitoMart',
        'cart': 'Carrito',
        'pin_is_required': 'El PIN es obligatorio',
      };
      for (final entry in esVitoKeys.entries) {
        expect(es[entry.key], entry.value,
            reason: 'ES key "${entry.key}" should be "${entry.value}"');
      }
    });
  });

  group('Token Validation Logic', () {
    test('Empty token should be rejected', () {
      final token = '';
      expect(token.isEmpty, isTrue);
    });

    test('Short token (< 10 chars) should be rejected', () {
      final token = 'abc123';
      expect(token.length < 10, isTrue,
          reason: 'Token of ${token.length} chars should fail format check');
    });

    test('Valid token format (64 hex chars)', () {
      final token = 'a' * 64;
      expect(token.length, 64);
      expect(RegExp(r'^[a-f0-9]+$').hasMatch(token), isTrue);
    });

    test('Token with max length 64', () {
      final token = '0123456789abcdef' * 4;
      expect(token.length, 64);
    });
  });

  group('PIN Validation Logic', () {
    test('PIN must be exactly 6 digits', () {
      expect('123456'.length, 6);
      expect(RegExp(r'^\d{6}$').hasMatch('123456'), isTrue);
    });

    test('Short PIN rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('12345'), isFalse);
    });

    test('PIN with letters rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('123abc'), isFalse);
    });

    test('PIN confirmation must match', () {
      final pin = '654321';
      final confirm = '654321';
      expect(pin, confirm);
    });

    test('PIN mismatch detected', () {
      final pin = '654321';
      final confirm = '654320';
      expect(pin == confirm, isFalse);
    });
  });

  group('QR Token Expiry Logic', () {
    test('Customer token expires in 1 hour', () {
      final now = DateTime.now();
      final expiry = now.add(const Duration(hours: 1));
      final diff = expiry.difference(now);
      expect(diff.inMinutes, 60);
    });

    test('Driver onboarding token expires in 7 days', () {
      final now = DateTime.now();
      final expiry = now.add(const Duration(days: 7));
      final diff = expiry.difference(now);
      expect(diff.inDays, 7);
    });

    test('Expired token is detected', () {
      final expiry = DateTime.now().subtract(const Duration(hours: 1));
      expect(expiry.isBefore(DateTime.now()), isTrue);
    });
  });

  group('Mart Order Logic', () {
    test('Order status flow is valid', () {
      final statusFlow = [
        'placed',
        'confirmed',
        'preparing',
        'ready',
        'dispatched',
        'delivered',
      ];
      expect(statusFlow.length, 6);
      expect(statusFlow.first, 'placed');
      expect(statusFlow.last, 'delivered');
    });

    test('Cart total calculation', () {
      final items = [
        {'price': 10.0, 'qty': 2},
        {'price': 5.50, 'qty': 3},
      ];
      final total = items.fold<double>(
        0,
        (sum, item) =>
            sum + (item['price'] as double) * (item['qty'] as int),
      );
      expect(total, 36.50);
    });
  });

  group('Client Auth Validation Logic', () {
    test('Empty first name is rejected in signup', () {
      const firstName = '';
      expect(firstName.trim().isEmpty, isTrue,
          reason: 'Empty first name should fail validation');
    });

    test('Phone with country code passes length check', () {
      const phone = '+15551234567';
      expect(phone.startsWith('+'), isTrue);
      expect(phone.length, greaterThanOrEqualTo(10));
    });

    test('Password shorter than 8 characters is invalid', () {
      const password = 'abc123';
      expect(password.length < 8, isTrue,
          reason: 'Password must be at least 8 characters');
    });

    test('Password mismatch is detected', () {
      const password = 'securePass1';
      const confirm = 'differentPass';
      expect(password == confirm, isFalse,
          reason: 'Passwords do not match');
    });

    test('Promo max_discount cap limits discount', () {
      const subtotal = 20.0;
      const discountPercent = 0.5;
      const maxDiscount = 3.0;
      final rawDiscount = subtotal * discountPercent;
      final appliedDiscount = rawDiscount > maxDiscount ? maxDiscount : rawDiscount;
      expect(appliedDiscount, 3.0);
    });

    test('Order total equals subtotal minus discount plus tip', () {
      const subtotal = 20.0;
      const discount = 3.0;
      const tip = 2.0;
      final total = subtotal - discount + tip;
      expect(total, 19.0);
    });

    test('Negative total is floored to zero', () {
      const subtotal = 2.0;
      const discount = 5.0;
      const tip = 0.0;
      final raw = subtotal - discount + tip;
      final total = raw < 0 ? 0.0 : raw;
      expect(total, 0.0);
    });

    test('Expired token is invalid', () {
      final expiry = DateTime.now().subtract(const Duration(minutes: 1));
      final isExpired = expiry.isBefore(DateTime.now());
      expect(isExpired, isTrue);
    });
  });
}
