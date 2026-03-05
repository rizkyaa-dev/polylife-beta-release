import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:mobile_ver/features/catatan/models/catatan_model.dart';
import 'package:mobile_ver/features/catatan/providers/catatan_provider.dart';
import 'package:mobile_ver/features/catatan/views/catatan_form_screen.dart';
import 'package:mobile_ver/features/catatan/widgets/catatan_empty_state.dart';
import 'package:mobile_ver/features/catatan/widgets/catatan_header_card.dart';
import 'package:mobile_ver/features/catatan/widgets/catatan_note_card.dart';
import 'package:mobile_ver/features/catatan/widgets/catatan_success_banner.dart';

class CatatanListScreen extends ConsumerStatefulWidget {
  const CatatanListScreen({super.key});

  @override
  ConsumerState<CatatanListScreen> createState() => _CatatanListScreenState();
}

class _CatatanListScreenState extends ConsumerState<CatatanListScreen> {
  String? _successMessage;

  @override
  Widget build(BuildContext context) {
    final catatanAsyncValue = ref.watch(catatanProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catatan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.read(catatanProvider.notifier).fetchCatatan(showLoader: false),
          ),
        ],
      ),
      body: catatanAsyncValue.when(
        data: (rows) {
          final activeNotes = rows.where((item) => !item.statusSampah).toList()
            ..sort((a, b) => b.tanggalAsDate.compareTo(a.tanggalAsDate));
          final trashNotes = rows.where((item) => item.statusSampah).toList()
            ..sort((a, b) => b.tanggalAsDate.compareTo(a.tanggalAsDate));

          return RefreshIndicator(
            onRefresh: () async {
              await ref.read(catatanProvider.notifier).fetchCatatan(showLoader: false);
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 20),
              children: [
                if (_successMessage != null) ...[
                  CatatanSuccessBanner(
                    message: _successMessage!,
                    onClose: () => setState(() => _successMessage = null),
                  ),
                  const SizedBox(height: 12),
                ],
                CatatanHeaderCard(
                  trashCount: trashNotes.length,
                  onCreate: _openCreateForm,
                  onOpenTrash: () => _openTrashSheet(trashNotes),
                ),
                const SizedBox(height: 12),
                if (activeNotes.isEmpty)
                  const CatatanEmptyState()
                else
                  ...activeNotes.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: CatatanNoteCard(
                        item: item,
                        onEdit: () => _openEditForm(item),
                        onDelete: () => _confirmMoveToTrash(item),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Text('Error: $err'),
        ),
      ),
    );
  }

  Future<void> _openCreateForm() async {
    final result = await Navigator.of(context).push<CatatanFormResult>(
      MaterialPageRoute(
        builder: (_) => const CatatanFormScreen(),
      ),
    );
    if (!mounted) return;

    if (result == CatatanFormResult.created) {
      setState(() {
        _successMessage = 'Catatan berhasil ditambahkan.';
      });
    }
  }

  Future<void> _openEditForm(Catatan catatan) async {
    final result = await Navigator.of(context).push<CatatanFormResult>(
      MaterialPageRoute(
        builder: (_) => CatatanFormScreen(catatan: catatan),
      ),
    );
    if (!mounted) return;

    if (result == CatatanFormResult.updated) {
      setState(() {
        _successMessage = 'Catatan berhasil diperbarui.';
      });
    }
  }

  Future<void> _confirmMoveToTrash(Catatan catatan) async {
    final move = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: const Text('Pindahkan ke Sampah'),
          content: Text('Pindahkan "${catatan.judul}" ke sampah?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(ctx).pop(true),
              child: const Text('Pindahkan'),
            ),
          ],
        );
      },
    );

    if (move != true || !mounted) return;

    final success = await ref.read(catatanProvider.notifier).deleteCatatan(catatan.id);
    if (!mounted) return;
    if (success) {
      setState(() {
        _successMessage = 'Catatan dipindahkan ke sampah.';
      });
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Gagal memindahkan catatan ke sampah.')),
    );
  }

  Future<void> _openTrashSheet(List<Catatan> trashNotes) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    const Text(
                      'Sampah Catatan',
                      style: TextStyle(
                        color: Color(0xFF0F172A),
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${trashNotes.length} item',
                      style: const TextStyle(
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                if (trashNotes.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 18),
                    child: Text(
                      'Belum ada catatan di sampah.',
                      style: TextStyle(color: Color(0xFF64748B)),
                    ),
                  )
                else
                  SizedBox(
                    height: MediaQuery.of(ctx).size.height * 0.5,
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: trashNotes.length,
                      separatorBuilder: (context, index) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final item = trashNotes[index];
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(item.judul),
                          subtitle: Text(
                            item.isi.trim().isEmpty ? '(Tanpa isi)' : item.isi,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: Wrap(
                            spacing: 4,
                            children: [
                              IconButton(
                                tooltip: 'Pulihkan',
                                onPressed: () => _restoreFromTrash(item),
                                icon: const Icon(Icons.restore_rounded),
                              ),
                              IconButton(
                                tooltip: 'Hapus permanen',
                                onPressed: () => _forceDelete(item),
                                icon: const Icon(Icons.delete_forever_rounded, color: Color(0xFFDC2626)),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _restoreFromTrash(Catatan item) async {
    final success = await ref.read(catatanProvider.notifier).restoreCatatan(item.id);
    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
      setState(() {
        _successMessage = 'Catatan dipulihkan dari sampah.';
      });
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Gagal memulihkan catatan.')),
    );
  }

  Future<void> _forceDelete(Catatan item) async {
    final success = await ref.read(catatanProvider.notifier).forceDeleteCatatan(item.id);
    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
      setState(() {
        _successMessage = 'Catatan dihapus permanen.';
      });
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Gagal menghapus catatan.')),
    );
  }
}
