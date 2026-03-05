import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/auth/providers/auth_provider.dart';
import 'package:mobile_ver/features/catatan/providers/catatan_provider.dart';
import 'package:mobile_ver/features/dashboard/widgets/dashboard_focus_panel.dart';
import 'package:mobile_ver/features/dashboard/widgets/dashboard_header_card.dart';
import 'package:mobile_ver/features/dashboard/widgets/dashboard_metrics_grid.dart';
import 'package:mobile_ver/features/dashboard/widgets/dashboard_quick_actions.dart';
import 'package:mobile_ver/features/dashboard/widgets/dashboard_today_overview_card.dart';
import 'package:mobile_ver/features/jadwal/providers/jadwal_provider.dart';
import 'package:mobile_ver/features/keuangan/providers/keuangan_provider.dart';
import 'package:mobile_ver/features/pengumuman/providers/pengumuman_provider.dart';
import 'package:mobile_ver/features/todo/providers/todo_provider.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(userProvider);
    final keuanganState = ref.watch(keuanganProvider);
    final jadwalState = ref.watch(jadwalProvider);
    final todoState = ref.watch(todoProvider);
    final catatanAsync = ref.watch(catatanProvider);
    final pengumumanAsync = ref.watch(pengumumanProvider);

    final idr = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    );

    final today = DateTime.now();
    final jadwalTodayCount = ref.read(jadwalProvider.notifier).countForDate(today);

    final catatanRows = catatanAsync.value ?? const [];
    final activeCatatanCount = catatanRows.where((row) => !row.statusSampah).length;
    final trashCatatanCount = catatanRows.where((row) => row.statusSampah).length;
    final latestCatatan = catatanRows.where((row) => !row.statusSampah).isEmpty
        ? null
        : (catatanRows.where((row) => !row.statusSampah).toList()
              ..sort((a, b) => b.tanggalAsDate.compareTo(a.tanggalAsDate)))
            .first;

    final pengumumanRows = pengumumanAsync.value ?? const [];
    final latestPengumuman = pengumumanRows.isEmpty ? null : pengumumanRows.first;

    final quickActions = <DashboardQuickAction>[
      DashboardQuickAction(
        label: 'Keuangan',
        icon: Icons.account_balance_wallet_outlined,
        color: const Color(0xFF16A34A),
        onTap: () => context.go('/keuangan'),
      ),
      DashboardQuickAction(
        label: 'Jadwal',
        icon: Icons.calendar_month_outlined,
        color: const Color(0xFF4F46E5),
        onTap: () => context.go('/jadwal'),
      ),
      DashboardQuickAction(
        label: 'To-Do',
        icon: Icons.checklist_rounded,
        color: const Color(0xFFEA580C),
        onTap: () => context.go('/todo'),
      ),
      DashboardQuickAction(
        label: 'Catatan',
        icon: Icons.bookmark_border_rounded,
        color: const Color(0xFF0EA5E9),
        onTap: () => context.go('/catatan'),
      ),
      DashboardQuickAction(
        label: 'Pengumuman',
        icon: Icons.campaign_outlined,
        color: const Color(0xFF9333EA),
        onTap: () => context.go('/pengumuman'),
      ),
    ];

    final metrics = <DashboardMetric>[
      DashboardMetric(
        title: 'Saldo',
        value: idr.format(keuanganState.summary.saldo),
        caption: 'Keuangan bulan ini',
        color: const Color(0xFF4F46E5),
        icon: Icons.pie_chart_outline_rounded,
        onTap: () => context.go('/keuangan'),
      ),
      DashboardMetric(
        title: 'Jadwal Hari Ini',
        value: '$jadwalTodayCount',
        caption: 'Agenda terjadwal',
        color: const Color(0xFF0EA5E9),
        icon: Icons.today_outlined,
        onTap: () => context.go('/jadwal'),
      ),
      DashboardMetric(
        title: 'To-Do Aktif',
        value: '${todoState.ongoingItems.length}',
        caption: 'Perlu diselesaikan',
        color: const Color(0xFFEA580C),
        icon: Icons.pending_actions_outlined,
        onTap: () => context.go('/todo'),
      ),
      DashboardMetric(
        title: 'To-Do Selesai',
        value: '${todoState.completedItems.length}',
        caption: 'Task terselesaikan',
        color: const Color(0xFF16A34A),
        icon: Icons.check_circle_outline_rounded,
        onTap: () => context.go('/todo'),
      ),
      DashboardMetric(
        title: 'Catatan Aktif',
        value: '$activeCatatanCount',
        caption: 'Sampah: $trashCatatanCount',
        color: const Color(0xFF0284C7),
        icon: Icons.note_alt_outlined,
        onTap: () => context.go('/catatan'),
      ),
      DashboardMetric(
        title: 'Pengumuman',
        value: '${pengumumanRows.length}',
        caption: 'Feed afiliasi',
        color: const Color(0xFF9333EA),
        icon: Icons.campaign_outlined,
        onTap: () => context.go('/pengumuman'),
      ),
    ];

    final focusItems = <DashboardFocusItem>[
      if (jadwalState.dayItems.isNotEmpty)
        DashboardFocusItem(
          title: jadwalState.dayItems.first.title,
          subtitle: 'Agenda terdekat hari ini',
          icon: Icons.calendar_today_outlined,
          color: const Color(0xFF4F46E5),
          onTap: () => context.go('/jadwal'),
        ),
      if (todoState.ongoingItems.isNotEmpty)
        DashboardFocusItem(
          title: todoState.ongoingItems.first.title,
          subtitle: 'Prioritas tugas berjalan',
          icon: Icons.checklist_rounded,
          color: const Color(0xFFEA580C),
          onTap: () => context.go('/todo'),
        ),
      if (latestCatatan != null)
        DashboardFocusItem(
          title: latestCatatan.judul,
          subtitle: 'Catatan terbaru',
          icon: Icons.menu_book_rounded,
          color: const Color(0xFF0284C7),
          onTap: () => context.go('/catatan'),
        ),
      if (latestPengumuman != null)
        DashboardFocusItem(
          title: latestPengumuman.title,
          subtitle: 'Pengumuman terbaru',
          icon: Icons.campaign_outlined,
          color: const Color(0xFF9333EA),
          onTap: () => context.go('/pengumuman'),
        ),
    ];

    final bool hasDataIssue =
        catatanAsync.hasError || pengumumanAsync.hasError || jadwalState.errorMessage != null || todoState.errorMessage != null;

    Future<void> refreshAll() async {
      await Future.wait([
        ref.read(keuanganProvider.notifier).fetchKeuangan(showLoader: false),
        ref.read(jadwalProvider.notifier).load(),
        ref.read(todoProvider.notifier).load(),
        ref.read(catatanProvider.notifier).fetchCatatan(),
        ref.read(pengumumanProvider.notifier).fetchPengumuman(),
      ]);
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Beranda'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: refreshAll,
          ),
          PopupMenuButton<String>(
            tooltip: 'Menu akun',
            onSelected: (value) {
              if (value == 'logout') {
                ref.read(authProvider.notifier).logout();
              }
            },
            itemBuilder: (context) => const [
              PopupMenuItem<String>(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout_rounded, size: 18),
                    SizedBox(width: 8),
                    Text('Logout'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: refreshAll,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 22),
          children: [
            DashboardHeaderCard(userName: user?.name ?? 'Pengguna'),
            const SizedBox(height: 12),
            DashboardTodayOverviewCard(
              jadwalTodayCount: jadwalTodayCount,
              todoOngoingCount: todoState.ongoingItems.length,
              todoCompletedCount: todoState.completedItems.length,
              pengumumanCount: pengumumanRows.length,
              onOpenJadwal: () => context.go('/jadwal'),
              onOpenTodo: () => context.go('/todo'),
              onOpenPengumuman: () => context.go('/pengumuman'),
            ),
            const SizedBox(height: 12),
            if (hasDataIssue) ...[
              const _DashboardWarningBanner(
                message: 'Sebagian data belum sinkron. Tarik layar untuk memuat ulang.',
              ),
              const SizedBox(height: 12),
            ],
            DashboardQuickActions(actions: quickActions),
            const SizedBox(height: 12),
            DashboardMetricsGrid(metrics: metrics),
            const SizedBox(height: 12),
            DashboardFocusPanel(items: focusItems),
          ],
        ),
      ),
    );
  }
}

class _DashboardWarningBanner extends StatelessWidget {
  final String message;

  const _DashboardWarningBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7ED),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFED7AA)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline_rounded, color: Color(0xFFEA580C), size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: Color(0xFF9A3412),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
