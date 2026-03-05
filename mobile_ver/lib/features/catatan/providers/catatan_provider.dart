import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'dart:convert';
import '../models/catatan_model.dart';

class CatatanNotifier extends StateNotifier<AsyncValue<List<Catatan>>> {
  static final List<Catatan> _mockSeed = [
    Catatan(
      id: 1,
      judul: 'Materi Web',
      isi: 'Ringkasan HTML, CSS, JavaScript untuk latihan minggu ini.',
      tanggal: '2026-02-28',
      statusSampah: false,
    ),
    Catatan(
      id: 2,
      judul: 'To-Do UTS',
      isi: 'Revisi catatan kuliah, latihan soal, dan cek jadwal ujian.',
      tanggal: '2026-02-27',
      statusSampah: false,
    ),
  ];

  CatatanNotifier() : super(const AsyncValue.loading()) {
    fetchCatatan();
  }

  Future<bool> fetchCatatan({bool showLoader = true}) async {
    if (AppMode.uiOnly) {
      state = AsyncValue.data(List<Catatan>.from(_mockSeed));
      return true;
    }

    final previous = state.valueOrNull;
    if (showLoader || previous == null) {
      state = const AsyncValue.loading();
    }

    try {
      final activeResponse = await ApiClient.get('/catatan?per_page=100');
      final trashResponse = await ApiClient.get('/catatan/trash?per_page=100');

      if (activeResponse.statusCode != 200 || trashResponse.statusCode != 200) {
        if (previous != null && !showLoader) {
          return false;
        }
        state = AsyncValue.error('Failed to load catatan', StackTrace.current);
        return false;
      }

      final activeList = _parseCatatanList(activeResponse.body);
      final trashList = _parseCatatanList(trashResponse.body);

      final mergedById = <int, Catatan>{
        for (final row in activeList) row.id: row.copyWith(statusSampah: false),
        for (final row in trashList) row.id: row.copyWith(statusSampah: true),
      };

      final merged = mergedById.values.toList()
        ..sort((a, b) {
          final byDate = b.tanggalAsDate.compareTo(a.tanggalAsDate);
          if (byDate != 0) return byDate;
          return b.id.compareTo(a.id);
        });

      state = AsyncValue.data(merged);
      return true;
    } catch (e, st) {
      if (previous != null && !showLoader) {
        return false;
      }
      state = AsyncValue.error(e, st);
      return false;
    }
  }

  Future<bool> createCatatan(String judul, String isi, String tanggal) async {
    if (AppMode.uiOnly) {
      final current = state.value ?? <Catatan>[];
      final nextId = current.isEmpty ? 1 : current.map((e) => e.id).reduce((a, b) => a > b ? a : b) + 1;
      final newItem = Catatan(
        id: nextId,
        judul: judul,
        isi: isi,
        tanggal: tanggal,
        statusSampah: false,
      );
      state = AsyncValue.data([newItem, ...current]);
      return true;
    }

    try {
      final response = await ApiClient.post('/catatan', {
        'judul': judul,
        'isi': isi,
        'tanggal': tanggal,
      });
      if (response.statusCode == 201) {
        return fetchCatatan(showLoader: false);
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> updateCatatan(int id, String judul, String isi, String tanggal) async {
    if (AppMode.uiOnly) {
      final current = state.value ?? <Catatan>[];
      final updated = current
          .map((item) => item.id == id
              ? Catatan(
                  id: item.id,
                  judul: judul,
                  isi: isi,
                  tanggal: tanggal,
                  statusSampah: item.statusSampah,
                )
              : item)
          .toList();
      state = AsyncValue.data(updated);
      return true;
    }

    try {
      final response = await ApiClient.put('/catatan/$id', {
        'judul': judul,
        'isi': isi,
        'tanggal': tanggal,
      });
      if (response.statusCode == 200) {
        return fetchCatatan(showLoader: false);
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteCatatan(int id) async {
    if (AppMode.uiOnly) {
      final current = state.value ?? <Catatan>[];
      final next = current
          .map((item) => item.id == id ? item.copyWith(statusSampah: true) : item)
          .toList();
      state = AsyncValue.data(next);
      return true;
    }

    try {
      final response = await ApiClient.delete('/catatan/$id');
      if (response.statusCode == 200) {
        return fetchCatatan(showLoader: false);
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> restoreCatatan(int id) async {
    if (AppMode.uiOnly) {
      final current = state.value ?? <Catatan>[];
      final next = current
          .map((item) => item.id == id ? item.copyWith(statusSampah: false) : item)
          .toList();
      state = AsyncValue.data(next);
      return true;
    }

    try {
      final response = await ApiClient.patch('/catatan/$id/restore', {});
      if (response.statusCode == 200) {
        return fetchCatatan(showLoader: false);
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> forceDeleteCatatan(int id) async {
    if (AppMode.uiOnly) {
      final current = state.value ?? <Catatan>[];
      state = AsyncValue.data(current.where((item) => item.id != id).toList());
      return true;
    }

    try {
      final response = await ApiClient.delete('/catatan/$id/force-delete');
      if (response.statusCode == 200) {
        return fetchCatatan(showLoader: false);
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  List<Catatan> _parseCatatanList(String rawBody) {
    final decoded = jsonDecode(rawBody);
    if (decoded is! Map<String, dynamic>) return const <Catatan>[];

    final rawData = decoded['data'];
    if (rawData is! List) return const <Catatan>[];

    return rawData
        .whereType<Map>()
        .map((row) => Catatan.fromJson(Map<String, dynamic>.from(row)))
        .toList();
  }
}

final catatanProvider = StateNotifierProvider<CatatanNotifier, AsyncValue<List<Catatan>>>((ref) {
  return CatatanNotifier();
});
