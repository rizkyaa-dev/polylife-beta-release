import 'package:mobile_ver/features/jadwal/models/jadwal_item.dart';

class JadwalDayStats {
  final int total;
  final int completed;
  final int upcoming;

  const JadwalDayStats({
    required this.total,
    required this.completed,
    required this.upcoming,
  });
}

class JadwalSchedulerService {
  const JadwalSchedulerService();

  DateTime normalizeDate(DateTime value) {
    return DateTime(value.year, value.month, value.day);
  }

  List<DateTime> buildDayWindow({
    required DateTime selectedDate,
    int daysBefore = 3,
    int daysAfter = 7,
  }) {
    final normalized = normalizeDate(selectedDate);
    final output = <DateTime>[];
    for (var i = -daysBefore; i <= daysAfter; i++) {
      output.add(normalized.add(Duration(days: i)));
    }
    return output;
  }

  List<DateTime> buildMonthGrid(DateTime monthAnchor) {
    final firstDay = DateTime(monthAnchor.year, monthAnchor.month, 1);
    // Monday-based grid: Monday=1 ... Sunday=7
    final leadingDays = firstDay.weekday - DateTime.monday;
    final gridStart = firstDay.subtract(Duration(days: leadingDays));

    final output = <DateTime>[];
    for (var i = 0; i < 42; i++) {
      output.add(gridStart.add(Duration(days: i)));
    }
    return output;
  }

  List<JadwalItem> filterByDate({
    required List<JadwalItem> allItems,
    required DateTime selectedDate,
    required String searchQuery,
  }) {
    final dayStart = normalizeDate(selectedDate);
    final dayEnd = dayStart.add(const Duration(days: 1));
    final normalizedQuery = searchQuery.trim().toLowerCase();

    final filtered = allItems.where((item) {
      final intersectsDay = item.startAt.isBefore(dayEnd) && item.endAt.isAfter(dayStart);
      if (!intersectsDay) {
        return false;
      }

      if (normalizedQuery.isEmpty) {
        return true;
      }

      final haystack = '${item.title} ${item.location} ${item.notes}'.toLowerCase();
      return haystack.contains(normalizedQuery);
    }).toList();

    filtered.sort((a, b) {
      final byStart = a.startAt.compareTo(b.startAt);
      if (byStart != 0) return byStart;
      return a.id.compareTo(b.id);
    });

    return filtered;
  }

  List<JadwalItem> itemsForDate({
    required List<JadwalItem> allItems,
    required DateTime date,
  }) {
    final dayStart = normalizeDate(date);
    final dayEnd = dayStart.add(const Duration(days: 1));

    final rows = allItems
        .where((item) => item.startAt.isBefore(dayEnd) && item.endAt.isAfter(dayStart))
        .toList()
      ..sort((a, b) {
        final byStart = a.startAt.compareTo(b.startAt);
        if (byStart != 0) return byStart;
        return a.id.compareTo(b.id);
      });
    return rows;
  }

  JadwalType? dominantTypeForDate({
    required List<JadwalItem> allItems,
    required DateTime date,
  }) {
    final rows = itemsForDate(allItems: allItems, date: date);
    if (rows.isEmpty) {
      return null;
    }

    // Priority for visual marker.
    if (rows.any((item) => item.type == JadwalType.personal)) {
      return JadwalType.personal;
    }
    if (rows.any((item) => item.type == JadwalType.ujian)) {
      return JadwalType.ujian;
    }
    if (rows.any((item) => item.type == JadwalType.kuliah)) {
      return JadwalType.kuliah;
    }
    return rows.first.type;
  }

  bool hasConflict({
    required List<JadwalItem> allItems,
    required JadwalItem candidate,
    int? ignoreId,
  }) {
    for (final item in allItems) {
      if (ignoreId != null && item.id == ignoreId) {
        continue;
      }

      final overlaps = candidate.startAt.isBefore(item.endAt) && candidate.endAt.isAfter(item.startAt);
      if (overlaps) {
        return true;
      }
    }
    return false;
  }

  JadwalDayStats buildDayStats(List<JadwalItem> dayItems) {
    final now = DateTime.now();
    final completed = dayItems.where((item) => item.completed).length;
    final upcoming = dayItems.where((item) => !item.completed && item.endAt.isAfter(now)).length;

    return JadwalDayStats(
      total: dayItems.length,
      completed: completed,
      upcoming: upcoming,
    );
  }

  int countForDate({
    required List<JadwalItem> allItems,
    required DateTime date,
  }) {
    final dayStart = normalizeDate(date);
    final dayEnd = dayStart.add(const Duration(days: 1));
    return allItems.where((item) => item.startAt.isBefore(dayEnd) && item.endAt.isAfter(dayStart)).length;
  }
}
