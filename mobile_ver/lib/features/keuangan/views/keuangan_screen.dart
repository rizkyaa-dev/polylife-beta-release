import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/keuangan/models/keuangan_model.dart';
import 'package:mobile_ver/features/keuangan/providers/keuangan_provider.dart';
import 'package:mobile_ver/features/keuangan/utils/category_icon_resolver.dart';
import 'package:mobile_ver/features/keuangan/views/keuangan_all_screen.dart';
import 'package:mobile_ver/features/keuangan/views/keuangan_form_screen.dart';

final NumberFormat _idrFormatter = NumberFormat.currency(
  locale: 'id_ID',
  symbol: 'Rp ',
  decimalDigits: 0,
);

class KeuanganScreen extends ConsumerWidget {
  const KeuanganScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(keuanganProvider);
    final notifier = ref.read(keuanganProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Keuangan'),
      ),
      body: state.isLoading && state.items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => notifier.fetchKeuangan(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(22, 22, 22, 22),
                children: [
                  _buildSaldoHeader(state),
                  const SizedBox(height: 12),
                  _buildMonthChip(
                    state,
                    onTap: () => _showMonthPicker(context, ref, state),
                  ),
                  const SizedBox(height: 20),
                  _buildActionButtons(
                    context,
                    ref,
                  ),
                  const SizedBox(height: 18),
                  _buildMonthlyRecap(state),
                  const SizedBox(height: 30),
                  _buildTransactionHeader(
                    context,
                    onFilterTap: () => _openAllTransactionsView(context),
                  ),
                  const SizedBox(height: 12),
                  if (state.errorMessage != null)
                    _ErrorBanner(message: state.errorMessage!)
                  else
                    const SizedBox.shrink(),
                  if (state.errorMessage != null) const SizedBox(height: 12),
                  if (state.items.isEmpty)
                    _buildEmptyState()
                  else
                    Column(
                      children: state.items
                          .map(
                            (item) => _TransactionRow(
                              item: item,
                              onTap: () => _showTransactionActions(context, ref, item),
                            ),
                          )
                          .toList(),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _buildSaldoHeader(KeuanganState state) {
    return Column(
      children: [
        const Text(
          'RINGKASAN BULAN INI',
          style: TextStyle(
            color: Color(0xFF6366F1),
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.7,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Total Saldo',
          style: TextStyle(
            color: Colors.grey.shade600,
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          _idrFormatter.format(state.summary.saldo),
          style: const TextStyle(
            color: Color(0xFF0F172A),
            fontSize: 46 / 2,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.1,
          ),
        ),
      ],
    );
  }

  Widget _buildMonthChip(KeuanganState state, {required VoidCallback onTap}) {
    final label = state.monthOptions
            .firstWhere(
              (option) => option.value == state.selectedMonth,
              orElse: () => state.monthOptions.isNotEmpty
                  ? state.monthOptions.first
                  : const KeuanganMonthOption(value: '', label: 'Pilih bulan'),
            )
            .label
            .trim()
            .isEmpty
        ? state.selectedMonth
        : state.monthOptions
            .firstWhere(
              (option) => option.value == state.selectedMonth,
              orElse: () => state.monthOptions.isNotEmpty
                  ? state.monthOptions.first
                  : const KeuanganMonthOption(value: '', label: 'Pilih bulan'),
            )
            .label;

    return Center(
      child: OutlinedButton.icon(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFF4B5563),
          side: const BorderSide(color: Color(0xFFE5E7EB)),
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
        icon: const Icon(Icons.calendar_month_outlined, size: 16),
        label: Text(label),
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context, WidgetRef ref) {
    return Row(
      children: [
        Expanded(
          child: _ActionButton(
            label: 'Pemasukan',
            icon: Icons.south_west_rounded,
            textColor: const Color(0xFF15803D),
            bgColor: const Color(0xFFE7F6EE),
            borderColor: const Color(0xFFCDEBD9),
            onTap: () => _openForm(context, ref, presetJenis: 'pemasukan'),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: _ActionButton(
            label: 'Pengeluaran',
            icon: Icons.north_east_rounded,
            textColor: const Color(0xFFB42318),
            bgColor: const Color(0xFFFBEDEE),
            borderColor: const Color(0xFFF0D4D7),
            onTap: () => _openForm(context, ref, presetJenis: 'pengeluaran'),
          ),
        ),
      ],
    );
  }

  Widget _buildMonthlyRecap(KeuanganState state) {
    final shortMonth = _selectedMonthShort(state);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x140F172A),
            blurRadius: 12,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pemasukan ($shortMonth)',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF16A34A),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _idrFormatter.format(state.summary.totalPemasukan),
                    style: const TextStyle(
                      fontSize: 34 / 2,
                      color: Color(0xFF0F172A),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
          Container(
            width: 1,
            height: 54,
            color: const Color(0xFFD7DEE8),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 16, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pengeluaran ($shortMonth)',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFFE11D48),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _idrFormatter.format(state.summary.totalPengeluaran),
                    style: const TextStyle(
                      fontSize: 34 / 2,
                      color: Color(0xFF0F172A),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTransactionHeader(
    BuildContext context, {
    required VoidCallback onFilterTap,
  }) {
    return Row(
      children: [
        Text(
          'Transaksi',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
        ),
        const Spacer(),
        OutlinedButton(
          onPressed: onFilterTap,
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFFE5E7EB)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
            foregroundColor: const Color(0xFF64748B),
            backgroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          ),
          child: const Text(
            'Semua',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    );
  }

  Widget _buildEmptyState() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: const Text(
        'Belum ada transaksi di bulan ini.',
        style: TextStyle(
          color: Color(0xFF64748B),
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }

  Future<void> _openForm(
    BuildContext context,
    WidgetRef ref, {
    KeuanganTransaction? transaction,
    String? presetJenis,
  }) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => KeuanganFormScreen(
          transaction: transaction,
          initialJenis: presetJenis,
        ),
      ),
    );
  }

  Future<void> _openAllTransactionsView(BuildContext context) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const KeuanganAllScreen(),
      ),
    );
  }

  Future<void> _confirmDelete(
    BuildContext context,
    WidgetRef ref,
    KeuanganTransaction item,
  ) async {
    final shouldDelete = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus transaksi?'),
        content: Text('Transaksi "${item.kategori}" akan dihapus permanen.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFE11D48)),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );

    if (shouldDelete != true) return;

    final success = await ref.read(keuanganProvider.notifier).deleteTransaction(item.id);
    if (!context.mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(success ? 'Transaksi dihapus.' : 'Gagal menghapus transaksi.'),
        backgroundColor: success ? const Color(0xFF166534) : const Color(0xFFB91C1C),
      ),
    );
  }

  Future<void> _showTransactionActions(
    BuildContext context,
    WidgetRef ref,
    KeuanganTransaction item,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(height: 8),
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD1D5DB),
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
              const SizedBox(height: 8),
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('Edit transaksi'),
                onTap: () {
                  Navigator.of(ctx).pop();
                  _openForm(context, ref, transaction: item);
                },
              ),
              ListTile(
                leading: const Icon(Icons.delete_outline, color: Color(0xFFE11D48)),
                title: const Text(
                  'Hapus transaksi',
                  style: TextStyle(color: Color(0xFFE11D48)),
                ),
                onTap: () {
                  Navigator.of(ctx).pop();
                  _confirmDelete(context, ref, item);
                },
              ),
              const SizedBox(height: 10),
            ],
          ),
        );
      },
    );
  }

  Future<void> _showMonthPicker(
    BuildContext context,
    WidgetRef ref,
    KeuanganState state,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: [
              const SizedBox(height: 8),
              const ListTile(
                title: Text(
                  'Pilih Bulan',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              ...state.monthOptions.map((option) {
                final selected = option.value == state.selectedMonth;
                return ListTile(
                  title: Text(option.label),
                  trailing: selected ? const Icon(Icons.check, color: Color(0xFF4F46E5)) : null,
                  onTap: () {
                    Navigator.of(ctx).pop();
                    ref.read(keuanganProvider.notifier).selectMonth(option.value);
                  },
                );
              }),
              const SizedBox(height: 10),
            ],
          ),
        );
      },
    );
  }

  String _selectedMonthShort(KeuanganState state) {
    final value = state.selectedMonth;
    final split = value.split('-');
    if (split.length != 2) return 'Bulan';

    final year = int.tryParse(split[0]);
    final month = int.tryParse(split[1]);
    if (year == null || month == null || month < 1 || month > 12) return 'Bulan';

    final date = DateTime(year, month, 1);
    final short = DateFormat('MMM', 'id_ID').format(date);
    return '${short[0].toUpperCase()}${short.substring(1)}';
  }
}

class _ActionButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color textColor;
  final Color bgColor;
  final Color borderColor;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.icon,
    required this.textColor,
    required this.bgColor,
    required this.borderColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          height: 52,
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor),
            boxShadow: const [
              BoxShadow(
                color: Color(0x120F172A),
                blurRadius: 8,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                height: 24,
                width: 24,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.85),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 14, color: textColor),
              ),
              const SizedBox(width: 10),
              Text(
                label,
                style: TextStyle(
                  color: textColor,
                  fontWeight: FontWeight.w700,
                  fontSize: 26 / 2,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TransactionRow extends StatelessWidget {
  final KeuanganTransaction item;
  final VoidCallback onTap;

  const _TransactionRow({
    required this.item,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isIncome = item.jenis == 'pemasukan';
    final amountColor = isIncome ? const Color(0xFF166534) : const Color(0xFF0F172A);
    final dateText = DateFormat('dd MMM', 'id_ID').format(item.tanggal);
    final subtitle = (item.deskripsi ?? '').trim().isNotEmpty ? item.deskripsi!.trim() : item.jenis;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: const BoxDecoration(
            border: Border(
              bottom: BorderSide(
                color: Color(0xFFE9EDF3),
              ),
            ),
          ),
          child: Row(
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    height: 48,
                    width: 48,
                    decoration: BoxDecoration(
                      color: isIncome ? const Color(0xFFDCFCE7) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Icon(
                      _resolveCategoryIcon(item),
                      color: isIncome ? const Color(0xFF16A34A) : const Color(0xFF4B5563),
                      size: 22,
                    ),
                  ),
                  Positioned(
                    right: -2,
                    bottom: -2,
                    child: Container(
                      height: 18,
                      width: 18,
                      decoration: BoxDecoration(
                        color: isIncome ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: Colors.white, width: 2),
                      ),
                      child: Icon(
                        isIncome ? Icons.south_west_rounded : Icons.north_east_rounded,
                        color: Colors.white,
                        size: 10,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.kategori,
                      style: const TextStyle(
                        color: Color(0xFF0F172A),
                        fontSize: 29 / 2,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '$dateText • $subtitle',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFF64748B),
                        fontSize: 13.5,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '${isIncome ? '+' : '-'}${_idrFormatter.format(item.nominal)}',
                style: TextStyle(
                  color: isIncome ? amountColor : const Color(0xFF0F172A),
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  IconData _resolveCategoryIcon(KeuanganTransaction row) {
    return resolveKeuanganCategoryIcon(
      kategori: row.kategori,
      jenis: row.jenis,
      emptyFallback: Icons.receipt_long_outlined,
    );
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
