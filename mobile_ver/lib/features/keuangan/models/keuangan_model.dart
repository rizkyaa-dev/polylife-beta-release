class KeuanganTransaction {
  final int id;
  final String jenis;
  final String kategori;
  final String? deskripsi;
  final double nominal;
  final DateTime tanggal;

  const KeuanganTransaction({
    required this.id,
    required this.jenis,
    required this.kategori,
    required this.deskripsi,
    required this.nominal,
    required this.tanggal,
  });

  bool get isPemasukan => jenis == 'pemasukan';

  factory KeuanganTransaction.fromJson(Map<String, dynamic> json) {
    return KeuanganTransaction(
      id: _toInt(json['id']),
      jenis: (json['jenis'] ?? 'pengeluaran').toString(),
      kategori: (json['kategori'] ?? '').toString(),
      deskripsi: json['deskripsi']?.toString(),
      nominal: _toDouble(json['nominal']),
      tanggal: _toDate(json['tanggal']),
    );
  }

  Map<String, dynamic> toPayload() {
    return {
      'jenis': jenis,
      'kategori': kategori,
      'deskripsi': deskripsi,
      'nominal': nominal,
      'tanggal': _dateKey(tanggal),
    };
  }

  KeuanganTransaction copyWith({
    int? id,
    String? jenis,
    String? kategori,
    String? deskripsi,
    double? nominal,
    DateTime? tanggal,
  }) {
    return KeuanganTransaction(
      id: id ?? this.id,
      jenis: jenis ?? this.jenis,
      kategori: kategori ?? this.kategori,
      deskripsi: deskripsi ?? this.deskripsi,
      nominal: nominal ?? this.nominal,
      tanggal: tanggal ?? this.tanggal,
    );
  }
}

class KeuanganSummary {
  final double totalPemasukan;
  final double totalPengeluaran;
  final double saldo;

  const KeuanganSummary({
    required this.totalPemasukan,
    required this.totalPengeluaran,
    required this.saldo,
  });

  const KeuanganSummary.zero()
      : totalPemasukan = 0,
        totalPengeluaran = 0,
        saldo = 0;

  factory KeuanganSummary.fromJson(Map<String, dynamic> json) {
    return KeuanganSummary(
      totalPemasukan: _toDouble(json['total_pemasukan']),
      totalPengeluaran: _toDouble(json['total_pengeluaran']),
      saldo: _toDouble(json['saldo']),
    );
  }
}

class KeuanganMonthOption {
  final String value;
  final String label;

  const KeuanganMonthOption({
    required this.value,
    required this.label,
  });

  factory KeuanganMonthOption.fromJson(Map<String, dynamic> json) {
    return KeuanganMonthOption(
      value: (json['value'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
    );
  }
}

int _toInt(dynamic value) {
  if (value is int) return value;
  return int.tryParse((value ?? '').toString()) ?? 0;
}

double _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse((value ?? '').toString()) ?? 0;
}

DateTime _toDate(dynamic value) {
  final raw = (value ?? '').toString();
  return DateTime.tryParse(raw) ?? DateTime.now();
}

String _dateKey(DateTime date) {
  final y = date.year.toString().padLeft(4, '0');
  final m = date.month.toString().padLeft(2, '0');
  final d = date.day.toString().padLeft(2, '0');
  return '$y-$m-$d';
}
