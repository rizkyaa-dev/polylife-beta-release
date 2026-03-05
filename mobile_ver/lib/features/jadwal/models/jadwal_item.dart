enum JadwalType {
  kuliah,
  tugas,
  ujian,
  rapat,
  personal,
}

extension JadwalTypeLabel on JadwalType {
  String get label {
    switch (this) {
      case JadwalType.kuliah:
        return 'Kuliah';
      case JadwalType.tugas:
        return 'Tugas';
      case JadwalType.ujian:
        return 'Ujian';
      case JadwalType.rapat:
        return 'Rapat';
      case JadwalType.personal:
        return 'Personal';
    }
  }
}

class JadwalItem {
  final int id;
  final String title;
  final JadwalType type;
  final DateTime startAt;
  final DateTime endAt;
  final String location;
  final String notes;
  final bool completed;

  const JadwalItem({
    required this.id,
    required this.title,
    required this.type,
    required this.startAt,
    required this.endAt,
    required this.location,
    required this.notes,
    required this.completed,
  });

  JadwalItem copyWith({
    int? id,
    String? title,
    JadwalType? type,
    DateTime? startAt,
    DateTime? endAt,
    String? location,
    String? notes,
    bool? completed,
  }) {
    return JadwalItem(
      id: id ?? this.id,
      title: title ?? this.title,
      type: type ?? this.type,
      startAt: startAt ?? this.startAt,
      endAt: endAt ?? this.endAt,
      location: location ?? this.location,
      notes: notes ?? this.notes,
      completed: completed ?? this.completed,
    );
  }
}

class JadwalInput {
  final String title;
  final JadwalType type;
  final DateTime startAt;
  final DateTime endAt;
  final String location;
  final String notes;
  final bool completed;

  const JadwalInput({
    required this.title,
    required this.type,
    required this.startAt,
    required this.endAt,
    required this.location,
    required this.notes,
    required this.completed,
  });
}
