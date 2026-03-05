import 'dart:convert';

import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/features/jadwal/models/jadwal_item.dart';
import 'package:mobile_ver/features/jadwal/repositories/jadwal_repository.dart';

class ApiJadwalRepository implements JadwalRepository {
  @override
  Future<List<JadwalItem>> fetchAll() async {
    final response = await ApiClient.get('/jadwal?per_page=200');
    if (response.statusCode != 200) {
      throw Exception('Failed to load jadwal');
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      return const <JadwalItem>[];
    }

    final rawData = decoded['data'];
    if (rawData is! List) {
      return const <JadwalItem>[];
    }

    final rows = rawData
        .whereType<Map>()
        .map((row) => _fromApiJson(Map<String, dynamic>.from(row)))
        .toList()
      ..sort((a, b) {
        final byDate = a.startAt.compareTo(b.startAt);
        if (byDate != 0) return byDate;
        return a.id.compareTo(b.id);
      });

    return rows;
  }

  @override
  Future<JadwalItem> create(JadwalItem item) async {
    final response = await ApiClient.post('/jadwal', _toApiPayload(item));
    if (response.statusCode != 201) {
      throw Exception('Failed to create jadwal');
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Invalid response');
    }

    final rawData = decoded['data'];
    if (rawData is! Map) {
      throw Exception('Invalid response');
    }

    return _fromApiJson(Map<String, dynamic>.from(rawData));
  }

  @override
  Future<JadwalItem> update(JadwalItem item) async {
    final response = await ApiClient.put('/jadwal/${item.id}', _toApiPayload(item));
    if (response.statusCode != 200) {
      throw Exception('Failed to update jadwal');
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Invalid response');
    }

    final rawData = decoded['data'];
    if (rawData is! Map) {
      throw Exception('Invalid response');
    }

    return _fromApiJson(Map<String, dynamic>.from(rawData));
  }

  @override
  Future<void> delete(int id) async {
    final response = await ApiClient.delete('/jadwal/$id');
    if (response.statusCode != 200) {
      throw Exception('Failed to delete jadwal');
    }
  }

  Map<String, dynamic> _toApiPayload(JadwalItem item) {
    return <String, dynamic>{
      'title': item.title.trim(),
      'type': _typeToApi(item.type),
      'start_at': item.startAt.toIso8601String(),
      'end_at': item.endAt.toIso8601String(),
      'location': item.location.trim(),
      'notes': item.notes.trim(),
      'completed': item.completed,
    };
  }

  JadwalItem _fromApiJson(Map<String, dynamic> json) {
    final startAt = DateTime.tryParse((json['start_at'] ?? '').toString()) ?? DateTime.now();
    var endAt = DateTime.tryParse((json['end_at'] ?? '').toString()) ?? startAt.add(const Duration(hours: 1));
    if (!endAt.isAfter(startAt)) {
      endAt = startAt.add(const Duration(hours: 1));
    }

    final rawCompleted = json['completed'];
    final completed = rawCompleted == true || rawCompleted == 1 || rawCompleted == '1';

    return JadwalItem(
      id: int.tryParse((json['id'] ?? '').toString()) ?? 0,
      title: (json['title'] ?? '').toString(),
      type: _typeFromApi((json['type'] ?? '').toString()),
      startAt: startAt,
      endAt: endAt,
      location: (json['location'] ?? '').toString(),
      notes: (json['notes'] ?? '').toString(),
      completed: completed,
    );
  }

  String _typeToApi(JadwalType type) {
    switch (type) {
      case JadwalType.kuliah:
        return 'kuliah';
      case JadwalType.tugas:
        return 'tugas';
      case JadwalType.ujian:
        return 'ujian';
      case JadwalType.rapat:
        return 'rapat';
      case JadwalType.personal:
        return 'personal';
    }
  }

  JadwalType _typeFromApi(String raw) {
    switch (raw.toLowerCase().trim()) {
      case 'kuliah':
        return JadwalType.kuliah;
      case 'tugas':
        return JadwalType.tugas;
      case 'ujian':
        return JadwalType.ujian;
      case 'rapat':
        return JadwalType.rapat;
      default:
        return JadwalType.personal;
    }
  }
}
