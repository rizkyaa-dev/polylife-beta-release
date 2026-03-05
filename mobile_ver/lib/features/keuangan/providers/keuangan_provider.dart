import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/features/keuangan/models/keuangan_model.dart';

class KeuanganState {
  final bool isLoading;
  final List<KeuanganTransaction> items;
  final KeuanganSummary summary;
  final List<KeuanganMonthOption> monthOptions;
  final String selectedMonth;
  final String? errorMessage;

  const KeuanganState({
    required this.isLoading,
    required this.items,
    required this.summary,
    required this.monthOptions,
    required this.selectedMonth,
    required this.errorMessage,
  });

  factory KeuanganState.initial() {
    final now = DateTime.now();
    final monthKey = _monthKey(now);

    return KeuanganState(
      isLoading: true,
      items: const [],
      summary: const KeuanganSummary.zero(),
      monthOptions: [
        KeuanganMonthOption(
          value: monthKey,
          label: _monthLabel(monthKey),
        ),
      ],
      selectedMonth: monthKey,
      errorMessage: null,
    );
  }

  KeuanganState copyWith({
    bool? isLoading,
    List<KeuanganTransaction>? items,
    KeuanganSummary? summary,
    List<KeuanganMonthOption>? monthOptions,
    String? selectedMonth,
    String? errorMessage,
    bool clearError = false,
  }) {
    return KeuanganState(
      isLoading: isLoading ?? this.isLoading,
      items: items ?? this.items,
      summary: summary ?? this.summary,
      monthOptions: monthOptions ?? this.monthOptions,
      selectedMonth: selectedMonth ?? this.selectedMonth,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}

class KeuanganNotifier extends StateNotifier<KeuanganState> {
  static final List<KeuanganTransaction> _mockStore = [
    KeuanganTransaction(
      id: 1,
      jenis: 'pemasukan',
      kategori: 'Uang Saku',
      deskripsi: 'Transfer bulanan dari orang tua',
      nominal: 1250000,
      tanggal: DateTime(2026, 2, 4),
    ),
    KeuanganTransaction(
      id: 2,
      jenis: 'pengeluaran',
      kategori: 'Makan',
      deskripsi: 'Makan siang kantin',
      nominal: 28000,
      tanggal: DateTime(2026, 2, 5),
    ),
    KeuanganTransaction(
      id: 3,
      jenis: 'pengeluaran',
      kategori: 'Transport',
      deskripsi: 'Ojek ke kampus',
      nominal: 22000,
      tanggal: DateTime(2026, 2, 7),
    ),
    KeuanganTransaction(
      id: 4,
      jenis: 'pemasukan',
      kategori: 'Freelance',
      deskripsi: 'Desain poster acara',
      nominal: 350000,
      tanggal: DateTime(2026, 1, 27),
    ),
    KeuanganTransaction(
      id: 5,
      jenis: 'pengeluaran',
      kategori: 'Buku',
      deskripsi: 'Beli buku referensi',
      nominal: 120000,
      tanggal: DateTime(2026, 1, 30),
    ),
  ];

  KeuanganNotifier() : super(KeuanganState.initial()) {
    fetchKeuangan();
  }

  Future<List<KeuanganTransaction>> fetchAllTransactions() async {
    if (AppMode.uiOnly) {
      final all = List<KeuanganTransaction>.from(_mockStore)
        ..sort((a, b) {
          final byDate = b.tanggal.compareTo(a.tanggal);
          if (byDate != 0) return byDate;
          return b.id.compareTo(a.id);
        });
      return all;
    }

    final months = <String>{
      ...state.monthOptions.map((option) => option.value).where((value) => value.trim().isNotEmpty),
    };

    if (months.isEmpty && state.selectedMonth.trim().isNotEmpty) {
      months.add(state.selectedMonth);
    }

    if (months.isEmpty) {
      return const <KeuanganTransaction>[];
    }

    final merged = <int, KeuanganTransaction>{};

    for (final month in months) {
      final response = await ApiClient.get('/keuangan?bulan=$month&per_page=100');
      if (response.statusCode != 200) {
        continue;
      }

      final body = _decodeToMap(response.body);
      final rawData = body['data'];
      final dataList = rawData is List ? rawData : const [];
      for (final row in dataList.whereType<Map>()) {
        final trx = KeuanganTransaction.fromJson(Map<String, dynamic>.from(row));
        merged[trx.id] = trx;
      }
    }

    final all = merged.values.toList()
      ..sort((a, b) {
        final byDate = b.tanggal.compareTo(a.tanggal);
        if (byDate != 0) return byDate;
        return b.id.compareTo(a.id);
      });

    return all;
  }

  Future<void> fetchKeuangan({String? bulan, bool showLoader = true}) async {
    final selectedMonth = bulan ?? state.selectedMonth;

    if (showLoader) {
      state = state.copyWith(
        isLoading: true,
        selectedMonth: selectedMonth,
        clearError: true,
      );
    } else {
      state = state.copyWith(
        selectedMonth: selectedMonth,
        clearError: true,
      );
    }

    if (AppMode.uiOnly) {
      _loadMockData(selectedMonth);
      return;
    }

    try {
      final response = await ApiClient.get('/keuangan?bulan=$selectedMonth&per_page=100');
      final body = _decodeToMap(response.body);

      if (response.statusCode != 200) {
        state = state.copyWith(
          isLoading: false,
          errorMessage: _extractMessage(body) ?? 'Gagal memuat data keuangan.',
        );
        return;
      }

      final rawData = body['data'];
      final rawMeta = body['meta'];
      final dataList = rawData is List ? rawData : const [];
      final meta = rawMeta is Map<String, dynamic>
          ? rawMeta
          : rawMeta is Map
              ? Map<String, dynamic>.from(rawMeta)
              : <String, dynamic>{};

      final items = dataList
          .whereType<Map>()
          .map((row) => KeuanganTransaction.fromJson(Map<String, dynamic>.from(row)))
          .toList();

      final summaryMap = meta['summary'];
      final summary = summaryMap is Map<String, dynamic>
          ? KeuanganSummary.fromJson(summaryMap)
          : summaryMap is Map
              ? KeuanganSummary.fromJson(Map<String, dynamic>.from(summaryMap))
              : _buildSummary(items);

      final selectedFromApi = (meta['selected_month'] ?? selectedMonth).toString();
      final options = _parseMonthOptions(meta['month_options'], selectedFromApi, items);

      state = state.copyWith(
        isLoading: false,
        items: items,
        summary: summary,
        selectedMonth: selectedFromApi,
        monthOptions: options,
        clearError: true,
      );
    } catch (_) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Terjadi kendala jaringan saat memuat keuangan.',
      );
    }
  }

  Future<void> selectMonth(String monthKey) async {
    await fetchKeuangan(bulan: monthKey, showLoader: false);
  }

  Future<bool> createTransaction({
    required String jenis,
    required String kategori,
    required String? deskripsi,
    required double nominal,
    required DateTime tanggal,
  }) async {
    final payload = {
      'jenis': jenis,
      'kategori': kategori.trim(),
      'deskripsi': (deskripsi ?? '').trim().isEmpty ? null : deskripsi?.trim(),
      'nominal': nominal,
      'tanggal': _dateKey(tanggal),
    };

    if (AppMode.uiOnly) {
      final nextId = _mockStore.isEmpty
          ? 1
          : _mockStore.map((item) => item.id).reduce((a, b) => a > b ? a : b) + 1;

      _mockStore.insert(
        0,
        KeuanganTransaction(
          id: nextId,
          jenis: jenis,
          kategori: kategori.trim(),
          deskripsi: (deskripsi ?? '').trim().isEmpty ? null : deskripsi?.trim(),
          nominal: nominal,
          tanggal: tanggal,
        ),
      );
      await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
      return true;
    }

    try {
      final response = await ApiClient.post('/keuangan', payload);
      if (response.statusCode == 201) {
        await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> updateTransaction({
    required int id,
    required String jenis,
    required String kategori,
    required String? deskripsi,
    required double nominal,
    required DateTime tanggal,
  }) async {
    final payload = {
      'jenis': jenis,
      'kategori': kategori.trim(),
      'deskripsi': (deskripsi ?? '').trim().isEmpty ? null : deskripsi?.trim(),
      'nominal': nominal,
      'tanggal': _dateKey(tanggal),
    };

    if (AppMode.uiOnly) {
      final index = _mockStore.indexWhere((item) => item.id == id);
      if (index == -1) return false;

      _mockStore[index] = _mockStore[index].copyWith(
        jenis: jenis,
        kategori: kategori.trim(),
        deskripsi: (deskripsi ?? '').trim().isEmpty ? null : deskripsi?.trim(),
        nominal: nominal,
        tanggal: tanggal,
      );

      await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
      return true;
    }

    try {
      final response = await ApiClient.put('/keuangan/$id', payload);
      if (response.statusCode == 200) {
        await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteTransaction(int id) async {
    if (AppMode.uiOnly) {
      _mockStore.removeWhere((item) => item.id == id);
      await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
      return true;
    }

    try {
      final response = await ApiClient.delete('/keuangan/$id');
      if (response.statusCode == 200) {
        await fetchKeuangan(bulan: state.selectedMonth, showLoader: false);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  void _loadMockData(String monthKey) {
    final filtered = _mockStore
        .where((item) => _monthKey(item.tanggal) == monthKey)
        .toList()
      ..sort((a, b) {
        final byDate = b.tanggal.compareTo(a.tanggal);
        if (byDate != 0) return byDate;
        return b.id.compareTo(a.id);
      });

    final monthOptions = _buildMockMonthOptions(monthKey);

    state = state.copyWith(
      isLoading: false,
      items: filtered,
      summary: _buildSummary(filtered),
      selectedMonth: monthKey,
      monthOptions: monthOptions,
      clearError: true,
    );
  }

  KeuanganSummary _buildSummary(List<KeuanganTransaction> items) {
    final totalPemasukan = items
        .where((item) => item.jenis == 'pemasukan')
        .fold<double>(0, (sum, item) => sum + item.nominal);
    final totalPengeluaran = items
        .where((item) => item.jenis == 'pengeluaran')
        .fold<double>(0, (sum, item) => sum + item.nominal);

    return KeuanganSummary(
      totalPemasukan: totalPemasukan,
      totalPengeluaran: totalPengeluaran,
      saldo: totalPemasukan - totalPengeluaran,
    );
  }

  List<KeuanganMonthOption> _buildMockMonthOptions(String selectedMonth) {
    final keys = <String>{
      ..._mockStore.map((item) => _monthKey(item.tanggal)),
      selectedMonth,
    }.toList()
      ..sort((a, b) => b.compareTo(a));

    return keys
        .map((key) => KeuanganMonthOption(
              value: key,
              label: _monthLabel(key),
            ))
        .toList();
  }

  List<KeuanganMonthOption> _parseMonthOptions(
    dynamic rawOptions,
    String selectedMonth,
    List<KeuanganTransaction> items,
  ) {
    final list = rawOptions is List ? rawOptions : const [];
    final parsed = list
        .whereType<Map>()
        .map((row) => KeuanganMonthOption.fromJson(Map<String, dynamic>.from(row)))
        .where((item) => item.value.isNotEmpty)
        .toList();

    if (parsed.any((item) => item.value == selectedMonth) == false) {
      parsed.add(KeuanganMonthOption(
        value: selectedMonth,
        label: _monthLabel(selectedMonth),
      ));
    }

    if (parsed.isEmpty) {
      final fallbackKeys = <String>{
        ...items.map((item) => _monthKey(item.tanggal)),
        selectedMonth,
      }.toList()
        ..sort((a, b) => b.compareTo(a));

      return fallbackKeys
          .map((key) => KeuanganMonthOption(
                value: key,
                label: _monthLabel(key),
              ))
          .toList();
    }

    parsed.sort((a, b) => b.value.compareTo(a.value));
    return parsed;
  }

  Map<String, dynamic> _decodeToMap(String source) {
    try {
      final decoded = jsonDecode(source);
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {
      // fall through
    }
    return <String, dynamic>{};
  }

  String? _extractMessage(Map<String, dynamic> payload) {
    final message = payload['message'];
    if (message == null) return null;
    return message.toString();
  }
}

final keuanganProvider = StateNotifierProvider<KeuanganNotifier, KeuanganState>((ref) {
  return KeuanganNotifier();
});

String _dateKey(DateTime date) {
  final y = date.year.toString().padLeft(4, '0');
  final m = date.month.toString().padLeft(2, '0');
  final d = date.day.toString().padLeft(2, '0');
  return '$y-$m-$d';
}

String _monthKey(DateTime date) {
  final y = date.year.toString().padLeft(4, '0');
  final m = date.month.toString().padLeft(2, '0');
  return '$y-$m';
}

String _monthLabel(String monthKey) {
  final parts = monthKey.split('-');
  if (parts.length != 2) return monthKey;

  final year = int.tryParse(parts[0]);
  final month = int.tryParse(parts[1]);
  if (year == null || month == null || month < 1 || month > 12) {
    return monthKey;
  }

  const names = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];

  return '${names[month - 1]} $year';
}
