import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/jadwal/models/jadwal_item.dart';
import 'package:mobile_ver/features/jadwal/widgets/jadwal_item_card.dart';

class JadwalDayAgendaSection extends StatelessWidget {
  final DateTime selectedDate;
  final List<JadwalItem> dayItems;
  final ValueChanged<JadwalItem> onToggleCompleted;
  final ValueChanged<JadwalItem> onEdit;
  final ValueChanged<JadwalItem> onDelete;

  const JadwalDayAgendaSection({
    super.key,
    required this.selectedDate,
    required this.dayItems,
    required this.onToggleCompleted,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final dateLabel = DateFormat('EEEE, dd MMMM yyyy', 'id_ID').format(selectedDate);
    final isWeekend = selectedDate.weekday == DateTime.saturday || selectedDate.weekday == DateTime.sunday;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'AGENDA TANGGAL',
            style: TextStyle(
              color: Color(0xFF64748B),
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text(
                  dateLabel[0].toUpperCase() + dateLabel.substring(1),
                  style: const TextStyle(
                    color: Color(0xFF0F172A),
                    fontWeight: FontWeight.w800,
                    fontSize: 32 / 2,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFFEEF2FF),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  '${dayItems.length} agenda',
                  style: const TextStyle(
                    color: Color(0xFF4F46E5),
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1),
          if (isWeekend) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F2),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFECACA)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline_rounded, color: Color(0xFFEF4444), size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Akhir pekan: jadwal kuliah otomatis libur.',
                      style: TextStyle(
                        color: Color(0xFFEF4444),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (dayItems.isEmpty)
            _EmptyAgendaCard(
              isWeekend: isWeekend,
            )
          else
            ...dayItems.map(
              (item) => JadwalItemCard(
                item: item,
                onToggleCompleted: () => onToggleCompleted(item),
                onEdit: () => onEdit(item),
                onDelete: () => onDelete(item),
              ),
            ),
        ],
      ),
    );
  }
}

class _EmptyAgendaCard extends StatelessWidget {
  final bool isWeekend;

  const _EmptyAgendaCard({
    required this.isWeekend,
  });

  @override
  Widget build(BuildContext context) {
    final title = isWeekend ? 'Libur kuliah (akhir pekan).' : 'Belum ada agenda hari ini.';
    final subtitle = isWeekend
        ? 'Sabtu/Minggu otomatis bebas perkuliahan.'
        : 'Tekan tombol + Jadwal untuk menambah agenda baru.';
    final icon = isWeekend ? Icons.local_cafe_outlined : Icons.event_available_outlined;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: const Color(0xFFCBD5E1),
          style: BorderStyle.solid,
        ),
      ),
      child: Column(
        children: [
          CircleAvatar(
            radius: 20,
            backgroundColor: const Color(0xFFE2E8F0),
            child: Icon(icon, color: const Color(0xFF64748B)),
          ),
          const SizedBox(height: 10),
          Text(
            title,
            style: const TextStyle(
              color: Color(0xFF0F172A),
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Color(0xFF64748B),
              height: 1.3,
            ),
          ),
        ],
      ),
    );
  }
}
