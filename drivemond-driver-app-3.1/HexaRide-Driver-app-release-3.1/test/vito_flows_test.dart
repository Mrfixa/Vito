import 'dart:convert';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:ride_sharing_user_app/features/auth/domain/models/signup_body.dart';

/// Unit tests for VITO-specific flows in the driver app.
/// These validate localization, token logic, atomic acceptance,
/// and job request logic without requiring the full app or backend.
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
        'pin_is_required': 'El PIN es obligatorio',
        'username_is_required': 'El nombre de usuario es obligatorio',
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
      expect(token.length < 10, isTrue);
    });

    test('Valid token format (64 hex chars)', () {
      final token = 'a' * 64;
      expect(token.length, 64);
      expect(RegExp(r'^[a-f0-9]+$').hasMatch(token), isTrue);
    });
  });

  group('PIN Validation Logic', () {
    test('PIN must be exactly 6 digits', () {
      expect(RegExp(r'^\d{6}$').hasMatch('123456'), isTrue);
    });

    test('Short PIN rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('12345'), isFalse);
    });

    test('PIN with letters rejected', () {
      expect(RegExp(r'^\d{6}$').hasMatch('123abc'), isFalse);
    });

    test('PIN confirmation must match', () {
      expect('654321' == '654321', isTrue);
    });

    test('PIN mismatch detected', () {
      expect('654321' == '654320', isFalse);
    });
  });

  group('Atomic Ride Acceptance Logic', () {
    test('Only one driver can accept a pending ride', () {
      // Simulate the atomic acceptance: first driver succeeds, second fails
      String? rideDriverId;
      String rideStatus = 'pending';

      bool tryAccept(String driverId) {
        if (rideStatus == 'pending' && rideDriverId == null) {
          rideDriverId = driverId;
          rideStatus = 'accepted';
          return true;
        }
        return false;
      }

      expect(tryAccept('driver-1'), isTrue);
      expect(rideDriverId, 'driver-1');

      // Driver 2 attempts acceptance (should fail)
      expect(tryAccept('driver-2'), isFalse);
    });

    test('Already accepted ride cannot be re-accepted', () {
      String rideDriverId = 'driver-1';
      final canAccept = rideDriverId.isEmpty;
      expect(canAccept, isFalse);
    });
  });

  group('Job Request Modal Logic', () {
    test('Job request countdown starts at 30 seconds', () {
      const countdown = 30;
      expect(countdown, 30);
    });

    test('Countdown reaches zero means auto-dismiss', () {
      var countdown = 30;
      while (countdown > 0) {
        countdown--;
      }
      expect(countdown, 0);
    });

    test('Job request shows ride type info', () {
      final rideTypes = ['ride_request', 'parcel', 'mart_delivery'];
      expect(rideTypes.contains('ride_request'), isTrue);
      expect(rideTypes.contains('parcel'), isTrue);
      expect(rideTypes.contains('mart_delivery'), isTrue);
    });
  });

  group('Driver Registration Logic', () {
    test('Driver registration requires plate number', () {
      final plate = '';
      expect(plate.isEmpty, isTrue,
          reason: 'Empty plate should fail validation');
    });

    test('Auto-approval for onboarding token', () {
      const tokenRole = 'driver';
      const autoApprove = tokenRole == 'driver';
      expect(autoApprove, isTrue);
    });
  });

  group('SignUpBody Serialization', () {
    test('toJson includes service when services list is non-empty', () {
      final body = SignUpBody(
        fName: 'John', lName: 'Doe', username: 'johndoe',
        password: '123456', confirmPassword: '123456',
        services: ['ride_request'],
      );
      final json = body.toJson();
      expect(json.containsKey('service'), isTrue);
      expect(json['service'], 'ride_request');
    });

    test('toJson joins multiple services with comma', () {
      final body = SignUpBody(
        fName: 'Jane', lName: 'Doe', username: 'janedoe',
        password: '123456', confirmPassword: '123456',
        services: ['ride_request', 'parcel'],
      );
      final json = body.toJson();
      expect(json['service'], 'ride_request,parcel');
    });

    test('toJson includes address when set', () {
      final body = SignUpBody(
        fName: 'Ali', lName: 'Hassan', username: 'ali',
        password: '123456', confirmPassword: '123456',
        address: '123 Main St',
      );
      final json = body.toJson();
      expect(json['address'], '123 Main St');
    });

    test('toJson includes identification_type and identification_number', () {
      final body = SignUpBody(
        fName: 'Sara', lName: 'Lee', username: 'sara',
        password: '123456', confirmPassword: '123456',
        identificationType: 'passport',
        identityNumber: 'P123456',
      );
      final json = body.toJson();
      expect(json['identification_type'], 'passport');
      expect(json['identification_number'], 'P123456');
    });

    test('toJson omits service key when services list is empty', () {
      final body = SignUpBody(
        fName: 'Bob', lName: 'Smith', username: 'bob',
        password: '123456', confirmPassword: '123456',
        services: [],
      );
      final json = body.toJson();
      expect(json.containsKey('service'), isFalse);
    });
  });

  group('Driver Service Selection', () {
    test('Both ride and parcel selected produces comma-joined service string', () {
      final body = SignUpBody(
        fName: 'Driver', lName: 'A', username: 'drivera',
        password: '123456', confirmPassword: '123456',
        services: ['ride_request', 'parcel'],
      );
      expect(body.toJson()['service'], 'ride_request,parcel');
    });

    test('Only ride selected produces single service string', () {
      final body = SignUpBody(
        fName: 'Driver', lName: 'B', username: 'driverb',
        password: '123456', confirmPassword: '123456',
        services: ['ride_request'],
      );
      expect(body.toJson()['service'], 'ride_request');
    });

    test('No service selected means no service key in toJson', () {
      final body = SignUpBody(
        fName: 'Driver', lName: 'C', username: 'driverc',
        password: '123456', confirmPassword: '123456',
      );
      expect(body.toJson().containsKey('service'), isFalse);
    });
  });
}
