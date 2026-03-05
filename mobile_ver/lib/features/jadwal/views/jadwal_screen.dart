import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/jadwal/models/jadwal_item.dart';
import 'package:mobile_ver/features/jadwal/providers/jadwal_provider.dart';
import 'package:mobile_ver/features/jadwal/views/jadwal_form_screen.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_agenda_feed_section.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_day_agenda_section.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_mode_switcher.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_month_calendar_card.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_month_header_card.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_next_agenda_card.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_summary_card.dart';

class JadwalScreen extends ConsumerStatefulWidget {
  const JadwalScreen({super.key});

  @override
  ConsumerState<JadwalScreen> createState() => _JadwalScreenState();
}

enum _JadwalViewMode {
  focusDay,
  week,
  exam,
  all,
}

class _JadwalScreenState extends ConsumerState<JadwalScreen> {
  _JadwalViewMode _viewMode = _JadwalViewMode.focusDay;
  bool _showCalendar = false;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(jadwalProvider);
    final notifier = ref.read(jadwalProvider.notifier);
    final monthAnchor = DateTime(state.selectedDate.year, state.selectedDate.month, 1);
    final monthGridDays = notifier.buildMonthGrid(monthAnchor);
    final allItemsSorted = [...state.allItems]..sort((a, b) => a.startAt.compareTo(b.startAt));
    final now = DateTime.now();

    final weekItems = _itemsForWeek(allItemsSorted, now);
    final examItems = _itemsForExam(allItemsSorted);
    final nextUpcoming = _nextUpcoming(allItemsSorted, now);
    final allAgendaItems = _buildAllAgendaItems(allItemsSorted, now);

    final modeOptions = <JadwalModeOption>[
      JadwalModeOption(
        key: _JadwalViewMode.focusDay.name,
        label: 'Fokus Tanggal',
        count: state.dayItems.length,
        icon: Icons.today_rounded,
        accentColor: const Color(0xFF4F46E5),
      ),
      JadwalModeOption(
        key: _JadwalViewMode.week.name,
        label: 'Minggu Ini',
        count: weekItems.length,
        icon: Icons.date_range_rounded,
        accentColor: const Color(0xFF0EA5E9),
      ),
      JadwalModeOption(
        key: _JadwalViewMode.exam.name,
        label: 'UTS/UAS',
        count: examItems.length,
        icon: Icons.fact_check_outlined,
        accentColor: const Color(0xFFF59E0B),
      ),
      JadwalModeOption(
        key: _JadwalViewMode.all.name,
        label: 'Semua',
        count: allItemsSorted.length,
        icon: Icons.view_agenda_outlined,
        accentColor: const Color(0xFF16A34A),
      ),
    ];

    final (agendaTitle, agendaSubtitle, agendaItems, emptyTitle, emptySubtitle) = _agendaContent(
      mode: _viewMode,
      selectedDate: state.selectedDate,
      dayItems: state.dayItems,
      weekItems: weekItems,
      examItems: examItems,
      allItems: allAgendaItems,
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Jadwal'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: notifier.load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: state.isLoading && state.allItems.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: notifier.load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(8, 8, 8, 94),
                children: [
                  if (state.errorMessage != null) ...[
                    _ErrorBanner(message: state.errorMessage!),
                    const SizedBox(height: 10),
                  ],
                  JadwalSummaryCard(
                    selectedDate: state.selectedDate,
                    stats: state.dayStats,
                  ),
                  const SizedBox(height: 10),
                  JadwalNextAgendaCard(
                    item: nextUpcoming,
                    onOpenAgenda: () {
                      setState(() {
                        _viewMode = _JadwalViewMode.all;
                      });
                    },
                  ),
                  const SizedBox(height: 10),
                  JadwalModeSwitcher(
                    selectedKey: _viewMode.name,
                    options: modeOptions,
                    onSelected: (key) {
                      final selected = _JadwalViewMode.values.firstWhere(
                        (mode) => mode.name == key,
                        orElse: () => _JadwalViewMode.focusDay,
                      );
                      setState(() => _viewMode = selected);
                    },
                  ),
                  const SizedBox(height: 10),
                  JadwalMonthHeaderCard(
                    monthAnchor: monthAnchor,
                    onPreviousMonth: () => notifier.selectDate(
                      DateTime(monthAnchor.year, monthAnchor.month - 1, 1),
                    ),
                    onNextMonth: () => notifier.selectDate(
                      DateTime(monthAnchor.year, monthAnchor.month + 1, 1),
                    ),
                    onToday: () => notifier.selectDate(DateTime.now()),
                    onCreate: () => _openCreateForm(context, ref),
                    showCreateAction: false,
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => setState(() => _showCalendar = !_showCalendar),
                      icon: Icon(
                        _showCalendar ? Icons.expand_less_rounded : Icons.expand_more_rounded,
                        size: 18,
                      ),
                      label: Text(_showCalendar ? 'Sembunyikan kalender' : 'Tampilkan kalender'),
                    ),
                  ),
                  if (_showCalendar) ...[
                    const SizedBox(height: 6),
                    JadwalMonthCalendarCard(
                      monthAnchor: monthAnchor,
                      selectedDate: state.selectedDate,
                      monthGridDays: monthGridDays,
                      onSelectedDate: (date) {
                        notifier.selectDate(date);
                        setState(() => _viewMode = _JadwalViewMode.focusDay);
                      },
                      countResolver: notifier.countForDate,
                      dominantTypeResolver: notifier.dominantTypeForDate,
                    ),
                    const SizedBox(height: 14),
                  ] else
                    const SizedBox(height: 8),
                  if (_viewMode == _JadwalViewMode.focusDay)
                    JadwalDayAgendaSection(
                      selectedDate: state.selectedDate,
                      dayItems: state.dayItems,
                      onToggleCompleted: notifier.toggleCompleted,
                      onEdit: (item) => _openEditForm(context, ref, item),
                      onDelete: (item) => _confirmDelete(context, ref, item),
                    )
                  else
                    JadwalAgendaFeedSection(
                      title: agendaTitle,
                      subtitle: agendaSubtitle,
                      items: agendaItems,
                      emptyTitle: emptyTitle,
                      emptySubtitle: emptySubtitle,
                      onToggleCompleted: notifier.toggleCompleted,
                      onEdit: (item) => _openEditForm(context, ref, item),
                      onDelete: (item) => _confirmDelete(context, ref, item),
                    ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: () => context.go('/'),
                      icon: const Icon(Icons.dashboard_outlined, size: 18),
                      label: const Text('Kembali ke Beranda'),
                    ),
                  ),
                ],
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openCreateForm(context, ref),
        backgroundColor: const Color(0xFF4F46E5),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Tambah'),
      ),
    );
  }

  Future<void> _openCreateForm(BuildContext context, WidgetRef ref) async {
    final input = await Navigator.of(context).push<JadwalInput>(
      MaterialPageRoute(
        builder: (_) => const JadwalFormScreen(),
      ),
    );
    if (input == null || !context.mounted) return;

    final result = await ref.read(jadwalProvider.notifier).createFromInput(input);
    if (!context.mounted) return;
    _showActionResult(context, result);
  }

  Future<void> _openEditForm(BuildContext context, WidgetRef ref, JadwalItem item) async {
    final input = await Navigator.of(context).push<JadwalInput>(
      MaterialPageRoute(
        builder: (_) => JadwalFormScreen(initialItem: item),
      ),
    );
    if (input == null || !context.mounted) return;

    final result = await ref.read(jadwalProvider.notifier).updateFromInput(
          id: item.id,
          input: input,
        );
    if (!context.mounted) return;
    _showActionResult(context, result);
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref, JadwalItem item) async {
    final remove = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: const Text('Hapus Jadwal'),
          content: Text('Hapus agenda "${item.title}"?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Hapus'),
            ),
          ],
        );
      },
    );

    if (remove != true) return;
    await ref.read(jadwalProvider.notifier).delete(item.id);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Jadwal berhasil dihapus.')),
    );
  }

  void _showActionResult(BuildContext context, JadwalActionResult result) {
    if (result.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Jadwal berhasil disimpan.')),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result.message ?? 'Gagal menyimpan jadwal.'),
      ),
    );
  }

  List<JadwalItem> _itemsForWeek(List<JadwalItem> items, DateTime anchorDate) {
    final dayStart = DateTime(anchorDate.year, anchorDate.month, anchorDate.day);
    final startWeek = dayStart.subtract(Duration(days: dayStart.weekday - DateTime.monday));
    final endWeek = startWeek.add(const Duration(days: 7));

    return items.where((item) {
      return item.startAt.isBefore(endWeek) && item.endAt.isAfter(startWeek);
    }).toList()
      ..sort((a, b) => a.startAt.compareTo(b.startAt));
  }

  List<JadwalItem> _buildAllAgendaItems(List<JadwalItem> items, DateTime now) {
    final upcoming = items.where((item) => item.endAt.isAfter(now)).toList()..sort((a, b) => a.startAt.compareTo(b.startAt));
    final past = items.where((item) => !item.endAt.isAfter(now)).toList()..sort((a, b) => b.startAt.compareTo(a.startAt));
    return [...upcoming, ...past];
  }

  List<JadwalItem> _itemsForExam(List<JadwalItem> items) {
    return items.where((item) {
      if (item.type == JadwalType.ujian) return true;
      final title = item.title.toLowerCase();
      return title.contains('uts') || title.contains('uas');
    }).toList()
      ..sort((a, b) => a.startAt.compareTo(b.startAt));
  }

  JadwalItem? _nextUpcoming(List<JadwalItem> items, DateTime now) {
    final upcoming = items.where((item) => !item.completed && item.endAt.isAfter(now)).toList()
      ..sort((a, b) => a.startAt.compareTo(b.startAt));
    if (upcoming.isEmpty) return null;
    return upcoming.first;
  }

  (String, String, List<JadwalItem>, String, String) _agendaContent({
    required _JadwalViewMode mode,
    required DateTime selectedDate,
    required List<JadwalItem> dayItems,
    required List<JadwalItem> weekItems,
    required List<JadwalItem> examItems,
    required List<JadwalItem> allItems,
  }) {
    final selectedLabel = DateFormat('EEEE, dd MMMM yyyy', 'id_ID').format(selectedDate);
    switch (mode) {
      case _JadwalViewMode.focusDay:
        return (
          'Agenda Tanggal',
          selectedLabel[0].toUpperCase() + selectedLabel.substring(1),
          dayItems,
          'Belum ada agenda di tanggal ini.',
          'Pilih tanggal lain atau tambah jadwal baru.',
        );
      case _JadwalViewMode.week:
        return (
          'Agenda Minggu Ini',
          'Fokus ke kelas dan kegiatan dalam 7 hari.',
          weekItems,
          'Belum ada jadwal minggu ini.',
          'Tambah jadwal mingguan agar rencana kuliah lebih terstruktur.',
        );
      case _JadwalViewMode.exam:
        return (
          'UTS / UAS',
          'Prioritas jadwal ujian dan deadline penting.',
          examItems,
          'Belum ada jadwal UTS/UAS.',
          'Tambahkan agenda ujian agar mudah dipantau.',
        );
      case _JadwalViewMode.all:
        return (
          'Semua Agenda',
          'Seluruh jadwal akademik dan personal.',
          allItems,
          'Belum ada jadwal tersimpan.',
          'Tekan tombol tambah untuk membuat jadwal pertama.',
        );
    }
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;

  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        border: Border.all(color: const Color(0xFFFDA4AF)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        message,
        style: const TextStyle(
          color: Color(0xFFBE123C),
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
