import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/features/reminder/models/reminder_list_item.dart';

class ReminderListState {
  final bool isLoading;
  final List<ReminderListItem> items;
  final String? errorMessage;

  const ReminderListState({
    required this.isLoading,
    required this.items,
    required this.errorMessage,
  });

  factory ReminderListState.initial() {
    return const ReminderListState(
      isLoading: true,
      items: <ReminderListItem>[],
      errorMessage: null,
    );
  }

  ReminderListState copyWith({
    bool? isLoading,
    List<ReminderListItem>? items,
    String? errorMessage,
    bool clearError = false,
  }) {
    return ReminderListState(
      isLoading: isLoading ?? this.isLoading,
      items: items ?? this.items,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}

class ReminderListNotifier extends StateNotifier<ReminderListState> {
  ReminderListNotifier() : super(ReminderListState.initial()) {
    load();
  }

  static final List<ReminderListItem> _mockItems = <ReminderListItem>[
    ReminderListItem(
      id: 1,
      title: 'Laporan Praktikum PBO',
      targetType: 'todolist',
      targetLabel: 'To-Do',
      targetContext: 'To-Do: Laporan Praktikum PBO',
      destination: 'todo',
      active: true,
      scheduledAt: DateTime(2026, 4, 22, 1, 2),
      scheduledLabel: 'Rabu, 22 April 2026 • 01:02',
    ),
  ];

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);

    if (AppMode.uiOnly) {
      state = state.copyWith(
        isLoading: false,
        items: List<ReminderListItem>.from(_mockItems),
      );
      return;
    }

    try {
      final response = await ApiClient.get('/reminder');
      if (response.statusCode != 200) {
        state = state.copyWith(
          isLoading: false,
          errorMessage: 'Gagal memuat daftar reminder.',
        );
        return;
      }

      final decoded = jsonDecode(response.body);
      if (decoded is! Map<String, dynamic>) {
        state = state.copyWith(isLoading: false, items: const <ReminderListItem>[]);
        return;
      }

      final rawData = decoded['data'];
      if (rawData is! List) {
        state = state.copyWith(isLoading: false, items: const <ReminderListItem>[]);
        return;
      }

      final items = rawData
          .whereType<Map>()
          .map((row) => ReminderListItem.fromJson(Map<String, dynamic>.from(row)))
          .toList();

      state = state.copyWith(
        isLoading: false,
        items: items,
        clearError: true,
      );
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Gagal memuat daftar reminder.',
      );
    }
  }

  Future<bool> deleteReminder(int id) async {
    if (AppMode.uiOnly) {
      state = state.copyWith(
        items: state.items.where((item) => item.id != id).toList(),
      );
      return true;
    }

    try {
      final response = await ApiClient.delete('/reminder/$id');
      if (response.statusCode != 200) {
        state = state.copyWith(errorMessage: 'Gagal menghapus reminder.');
        return false;
      }

      state = state.copyWith(
        items: state.items.where((item) => item.id != id).toList(),
        clearError: true,
      );

      return true;
    } catch (_) {
      state = state.copyWith(errorMessage: 'Gagal menghapus reminder.');
      return false;
    }
  }
}

final reminderListProvider =
    StateNotifierProvider<ReminderListNotifier, ReminderListState>((ref) {
  return ReminderListNotifier();
});
