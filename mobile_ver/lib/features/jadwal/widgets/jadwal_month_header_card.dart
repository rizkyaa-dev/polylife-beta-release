import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class JadwalMonthHeaderCard extends StatelessWidget {
  final DateTime monthAnchor;
  final VoidCallback onPreviousMonth;
  final VoidCallback onNextMonth;
  final VoidCallback onToday;
  final VoidCallback onCreate;
  final bool showCreateAction;

  const JadwalMonthHeaderCard({
    super.key,
    required this.monthAnchor,
    required this.onPreviousMonth,
    required this.onNextMonth,
    required this.onToday,
    required this.onCreate,
    this.showCreateAction = true,
  });

  @override
  Widget build(BuildContext context) {
    final monthLabel = DateFormat('MMMM yyyy', 'id_ID').format(monthAnchor);
    final normalized = monthLabel[0].toUpperCase() + monthLabel.substring(1);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'AGENDA AKADEMIK',
            style: TextStyle(
              color: Color(0xFF4F46E5),
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  normalized,
                  style: const TextStyle(
                    color: Color(0xFF0F172A),
                    fontWeight: FontWeight.w800,
                    fontSize: 34 / 2,
                  ),
                ),
              ),
              IconButton(
                visualDensity: VisualDensity.compact,
                onPressed: onPreviousMonth,
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              IconButton(
                visualDensity: VisualDensity.compact,
                onPressed: onNextMonth,
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (showCreateAction)
            Row(
              children: [
                Expanded(
                  child: FilledButton.icon(
                    onPressed: onCreate,
                    icon: const Icon(Icons.add_rounded, size: 18),
                    label: const Text('Jadwal'),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF4F46E5),
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(44),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                OutlinedButton(
                  onPressed: onToday,
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(88, 44),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    side: const BorderSide(color: Color(0xFFD1D5DB)),
                  ),
                  child: const Text('Hari Ini'),
                ),
              ],
            )
          else
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                onPressed: onToday,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(96, 42),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  side: const BorderSide(color: Color(0xFFD1D5DB)),
                ),
                icon: const Icon(Icons.today_outlined, size: 16),
                label: const Text('Hari Ini'),
              ),
            ),
        ],
      ),
    );
  }
}
