import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'dart:convert';
import '../models/pengumuman_model.dart';

class PengumumanNotifier extends StateNotifier<AsyncValue<List<Pengumuman>>> {
  static final List<Pengumuman> _mockFeed = [
    Pengumuman(
      id: 1,
      title: 'Briefing Organisasi Mingguan',
      body: 'Rapat koordinasi terbuka untuk seluruh anggota.',
      excerpt: 'Rapat koordinasi terbuka untuk seluruh anggota pada Jumat 19.00.',
      imageUrl: null,
      targetMode: 'afiliasi',
      publishedAt: '2026-02-28 08:00:00',
      creator: {'name': 'Admin Kampus'},
    ),
    Pengumuman(
      id: 2,
      title: 'Pengumuman Jadwal Ujian',
      body: 'Silakan cek perubahan jadwal ujian terbaru.',
      excerpt: 'Ada pembaruan jadwal ujian, pastikan cek sebelum hari H.',
      imageUrl: null,
      targetMode: 'global',
      publishedAt: '2026-02-27 16:30:00',
      creator: {'name': 'Biro Akademik'},
    ),
  ];

  PengumumanNotifier() : super(const AsyncValue.loading()) {
    fetchPengumuman();
  }

  Future<void> fetchPengumuman() async {
    if (AppMode.uiOnly) {
      state = AsyncValue.data(List<Pengumuman>.from(_mockFeed));
      return;
    }

    state = const AsyncValue.loading();
    try {
      final response = await ApiClient.get('/pengumuman?per_page=20');
      if (response.statusCode == 200) {
        final List data = jsonDecode(response.body)['data'];
        final list = data.map((e) => Pengumuman.fromJson(e)).toList();
        state = AsyncValue.data(list);
      } else {
        state = AsyncValue.error('Failed to load pengumuman', StackTrace.current);
      }
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }
}

final pengumumanProvider = StateNotifierProvider<PengumumanNotifier, AsyncValue<List<Pengumuman>>>((ref) {
  return PengumumanNotifier();
});
