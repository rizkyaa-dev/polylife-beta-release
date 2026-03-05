class Catatan {
  final int id;
  final String judul;
  final String isi;
  final String tanggal;
  final bool statusSampah;

  Catatan({
    required this.id,
    required this.judul,
    required this.isi,
    required this.tanggal,
    required this.statusSampah,
  });

  factory Catatan.fromJson(Map<String, dynamic> json) {
    final rawStatus = json['status_sampah'];
    final isTrash = rawStatus == true || rawStatus == 1 || rawStatus == '1';

    return Catatan(
      id: int.tryParse((json['id'] ?? '').toString()) ?? 0,
      judul: (json['judul'] ?? '').toString(),
      isi: (json['isi'] ?? '').toString(),
      tanggal: (json['tanggal'] ?? '').toString(),
      statusSampah: isTrash,
    );
  }

  DateTime get tanggalAsDate {
    return DateTime.tryParse(tanggal) ?? DateTime.now();
  }

  Catatan copyWith({
    int? id,
    String? judul,
    String? isi,
    String? tanggal,
    bool? statusSampah,
  }) {
    return Catatan(
      id: id ?? this.id,
      judul: judul ?? this.judul,
      isi: isi ?? this.isi,
      tanggal: tanggal ?? this.tanggal,
      statusSampah: statusSampah ?? this.statusSampah,
    );
  }
}
