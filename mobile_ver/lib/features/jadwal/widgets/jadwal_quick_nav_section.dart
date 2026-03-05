import 'package:flutter/material.dart';

class JadwalQuickNavSection extends StatelessWidget {
  final VoidCallback onKelolaMatkul;
  final VoidCallback onKelolaKegiatan;
  final VoidCallback onTambahJadwal;
  final VoidCallback onLihatDashboard;

  const JadwalQuickNavSection({
    super.key,
    required this.onKelolaMatkul,
    required this.onKelolaKegiatan,
    required this.onTambahJadwal,
    required this.onLihatDashboard,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'NAVIGASI CEPAT',
            style: TextStyle(
              color: Color(0xFF64748B),
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Akses fitur terkait',
            style: TextStyle(
              color: Color(0xFF0F172A),
              fontWeight: FontWeight.w800,
              fontSize: 30 / 2,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _QuickActionCard(
                  label: 'Kelola Matkul',
                  icon: Icons.menu_book_outlined,
                  onTap: onKelolaMatkul,
                  highlighted: true,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _QuickActionCard(
                  label: 'Kelola Kegiatan',
                  icon: Icons.waves_outlined,
                  onTap: onKelolaKegiatan,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _QuickActionCard(
                  label: 'Tambah Jadwal',
                  icon: Icons.calendar_month_outlined,
                  onTap: onTambahJadwal,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _QuickActionCard(
                  label: 'Lihat Dashboard',
                  icon: Icons.dashboard_outlined,
                  onTap: onLihatDashboard,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 10),
          const Text(
            'Gunakan menu di atas untuk mempercepat pencatatan matkul, kegiatan, atau meninjau statistik akademik.',
            style: TextStyle(
              color: Color(0xFF94A3B8),
              fontSize: 13,
              height: 1.3,
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final bool highlighted;

  const _QuickActionCard({
    required this.label,
    required this.icon,
    required this.onTap,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    final bg = highlighted ? const Color(0xFFEEF2FF) : Colors.white;
    final border = highlighted ? const Color(0xFFC7D2FE) : const Color(0xFFE5E7EB);
    final iconColor = highlighted ? const Color(0xFF4F46E5) : const Color(0xFF94A3B8);
    final textColor = highlighted ? const Color(0xFF4F46E5) : const Color(0xFF0F172A);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        height: 78,
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: iconColor, size: 20),
            const SizedBox(height: 8),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: textColor,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
