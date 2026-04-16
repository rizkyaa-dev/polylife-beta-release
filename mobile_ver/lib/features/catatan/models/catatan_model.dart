class Catatan {
  final int id;
  final String judul;
  final String isi;
  final String previewIsi;
  final bool hasFullIsi;
  final String tanggal;
  final bool statusSampah;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  Catatan({
    required this.id,
    required this.judul,
    required this.isi,
    required this.previewIsi,
    required this.hasFullIsi,
    required this.tanggal,
    required this.statusSampah,
    this.createdAt,
    this.updatedAt,
  });

  factory Catatan.fromJson(Map<String, dynamic> json) {
    final rawStatus = json['status_sampah'];
    final isTrash = rawStatus == true || rawStatus == 1 || rawStatus == '1';
    final hasFullIsi = json['has_full_isi'] == true || json.containsKey('isi');
    final previewIsi = (json['preview_isi'] ?? '').toString();
    final fullIsi = hasFullIsi ? (json['isi'] ?? '').toString() : previewIsi;

    return Catatan(
      id: int.tryParse((json['id'] ?? '').toString()) ?? 0,
      judul: (json['judul'] ?? '').toString(),
      isi: fullIsi,
      previewIsi: previewIsi,
      hasFullIsi: hasFullIsi,
      tanggal: (json['tanggal'] ?? '').toString(),
      statusSampah: isTrash,
      createdAt: DateTime.tryParse((json['created_at'] ?? '').toString()),
      updatedAt: DateTime.tryParse((json['updated_at'] ?? '').toString()),
    );
  }

  DateTime get tanggalAsDate {
    return DateTime.tryParse(tanggal) ?? DateTime(1970, 1, 1);
  }

  String get listPreview {
    final preview = previewIsi.trim();
    if (preview.isNotEmpty) {
      return preview;
    }

    final full = isi.trim();
    return full.isNotEmpty ? full : '';
  }

  Catatan copyWith({
    int? id,
    String? judul,
    String? isi,
    String? previewIsi,
    bool? hasFullIsi,
    String? tanggal,
    bool? statusSampah,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Catatan(
      id: id ?? this.id,
      judul: judul ?? this.judul,
      isi: isi ?? this.isi,
      previewIsi: previewIsi ?? this.previewIsi,
      hasFullIsi: hasFullIsi ?? this.hasFullIsi,
      tanggal: tanggal ?? this.tanggal,
      statusSampah: statusSampah ?? this.statusSampah,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}
