import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/features/reminder/models/upcoming_reminder.dart';

class UpcomingReminderNotifier
    extends StateNotifier<AsyncValue<UpcomingReminder?>> {
  static const Duration _freshWindow = Duration(seconds: 20);
  static final UpcomingReminder _mockReminder = UpcomingReminder(
    id: 1,
    title: 'Laporan Praktikum PBO',
    targetType: 'tugas',
    scheduledAt: DateTime(2026, 4, 22, 1, 2),
    scheduledLabel: 'Rabu, 22 April 2026 01:02',
    relativeLabel: '1 minggu lagi',
    timeLeftText: 'Sisa 1 minggu 2 hari',
    secondsLeft: 9 * 24 * 3600,
  );

  Future<void>? _activeRequest;
  DateTime? _lastFetchedAt;

  UpcomingReminderNotifier() : super(const AsyncValue.loading()) {
    fetchReminder();
  }

  Future<void> fetchReminder({bool showLoader = true, bool force = false}) {
    if (!force &&
        _lastFetchedAt != null &&
        DateTime.now().difference(_lastFetchedAt!) < _freshWindow &&
        state.hasValue) {
      return Future.value();
    }

    if (_activeRequest != null) {
      return _activeRequest!;
    }

    final request = _performFetch(showLoader: showLoader);
    _activeRequest = request;

    return request.whenComplete(() {
      if (identical(_activeRequest, request)) {
        _activeRequest = null;
      }
    });
  }

  Future<void> _performFetch({required bool showLoader}) async {
    if (AppMode.uiOnly) {
      state = AsyncValue.data(_mockReminder);
      _lastFetchedAt = DateTime.now();
      return;
    }

    final previous = state.valueOrNull;
    if (showLoader || previous == null) {
      state = const AsyncValue.loading();
    }

    try {
      final response = await ApiClient.get('/reminder/next');
      if (response.statusCode != 200) {
        state = AsyncValue.error('Failed to load reminder', StackTrace.current);
        return;
      }

      final payload = _decodeToMap(response.body);
      final rawData = payload['data'];
      if (rawData == null) {
        state = const AsyncValue.data(null);
        _lastFetchedAt = DateTime.now();
        return;
      }

      if (rawData is Map<String, dynamic>) {
        state = AsyncValue.data(UpcomingReminder.fromJson(rawData));
        _lastFetchedAt = DateTime.now();
        return;
      }

      if (rawData is Map) {
        state = AsyncValue.data(
          UpcomingReminder.fromJson(Map<String, dynamic>.from(rawData)),
        );
        _lastFetchedAt = DateTime.now();
        return;
      }

      state = AsyncValue.error('Invalid reminder response', StackTrace.current);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }

  Map<String, dynamic> _decodeToMap(String raw) {
    final decoded = jsonDecode(raw);
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }

    if (decoded is Map) {
      return Map<String, dynamic>.from(decoded);
    }

    return <String, dynamic>{};
  }
}

final upcomingReminderProvider =
    StateNotifierProvider<
      UpcomingReminderNotifier,
      AsyncValue<UpcomingReminder?>
    >((ref) {
      return UpcomingReminderNotifier();
    });
