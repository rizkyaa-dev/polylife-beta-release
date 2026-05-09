import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:mobile_ver/features/auth/models/user_model.dart';
import 'package:mobile_ver/features/auth/providers/auth_provider.dart';
import 'package:mobile_ver/features/profile/widgets/profile_avatar.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  static const Color _background = Color(0xFFF5F4FA);
  static const Color _primary = Color(0xFF4B3FF2);
  static const Color _text = Color(0xFF211C31);
  static const Color _muted = Color(0xFF7A819C);

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _refreshProfile(showError: false);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(userProvider);

    return Scaffold(
      backgroundColor: ProfileScreen._background,
      body: SafeArea(
        child: RefreshIndicator(
          color: ProfileScreen._primary,
          onRefresh: _refreshProfile,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              _TitleBar(
                onBack: () {
                  if (context.canPop()) {
                    context.pop();
                    return;
                  }

                  context.go('/');
                },
                onRefresh: _refreshProfile,
              ),
              const SizedBox(height: 16),
              if (user == null)
                const _EmptySessionCard()
              else ...[
                _ProfileHeaderCard(user: user),
                const SizedBox(height: 14),
                _InfoSection(
                  title: 'Akun',
                  rows: [
                    _InfoRowData(
                      icon: Icons.badge_outlined,
                      label: 'Nama akun',
                      value: user.name,
                    ),
                    _InfoRowData(
                      icon: Icons.mail_outline_rounded,
                      label: 'Email',
                      value: user.email,
                    ),
                    _InfoRowData(
                      icon: Icons.verified_user_outlined,
                      label: 'Status akun',
                      value: _accountStatusLabel(user.accountStatus),
                    ),
                    _InfoRowData(
                      icon: Icons.mark_email_read_outlined,
                      label: 'Verifikasi email',
                      value: user.hasVerifiedEmail
                          ? 'Terverifikasi'
                          : 'Belum terverifikasi',
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _InfoSection(
                  title: 'Profil',
                  rows: [
                    _InfoRowData(
                      icon: Icons.person_outline_rounded,
                      label: 'Nama tampilan',
                      value: _displayValue(user.profile?.displayName),
                    ),
                    _InfoRowData(
                      icon: Icons.phone_outlined,
                      label: 'Telepon',
                      value: _displayValue(user.profile?.phone),
                    ),
                    _InfoRowData(
                      icon: Icons.place_outlined,
                      label: 'Lokasi',
                      value: _displayValue(user.profile?.location),
                    ),
                    _InfoRowData(
                      icon: Icons.palette_outlined,
                      label: 'Tema profil',
                      value: _themeLabel(user.profile?.themePreference),
                    ),
                    _InfoRowData(
                      icon: Icons.schedule_rounded,
                      label: 'Zona waktu',
                      value: _displayValue(user.profile?.timezone),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _InfoSection(
                  title: 'Kampus',
                  rows: [
                    _InfoRowData(
                      icon: Icons.school_outlined,
                      label: 'Institusi',
                      value: _displayValue(user.affiliation?.name),
                    ),
                    _InfoRowData(
                      icon: Icons.account_tree_outlined,
                      label: 'Tipe',
                      value: _affiliationTypeLabel(user.affiliation?.type),
                    ),
                    _InfoRowData(
                      icon: Icons.confirmation_number_outlined,
                      label: _studentIdLabel(user.affiliation?.studentIdType),
                      value: _displayValue(user.affiliation?.studentIdNumber),
                    ),
                    _InfoRowData(
                      icon: Icons.fact_check_outlined,
                      label: 'Status',
                      value: _affiliationStatusLabel(user.affiliation?.status),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _LogoutButton(onTap: _logout),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _refreshProfile({bool showError = true}) async {
    final messenger = ScaffoldMessenger.of(context);
    final result = await ref.read(authProvider.notifier).refreshCurrentUser();
    if (!mounted || result.isSuccess || !showError) return;

    messenger.showSnackBar(SnackBar(content: Text(result.message)));
  }

  Future<void> _logout() async {
    final router = GoRouter.of(context);
    await ref.read(authProvider.notifier).logout();
    if (mounted) {
      router.go('/login');
    }
  }
}

class _TitleBar extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onRefresh;

  const _TitleBar({required this.onBack, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _CircleActionButton(icon: Icons.arrow_back_rounded, onTap: onBack),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            'Profil',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 19,
              fontWeight: FontWeight.w800,
              color: ProfileScreen._text,
            ),
          ),
        ),
        _CircleActionButton(icon: Icons.refresh_rounded, onTap: onRefresh),
      ],
    );
  }
}

class _ProfileHeaderCard extends StatelessWidget {
  final User user;

  const _ProfileHeaderCard({required this.user});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x100F172A),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          ProfileAvatar(
            user: user,
            fallbackName: user.displayName,
            size: 64,
            borderWidth: 3,
            gradientColors: const [Color(0xFFE3E8FF), Color(0xFF6C63FF)],
            boxShadow: const [
              BoxShadow(
                color: Color(0x244B3FF2),
                blurRadius: 20,
                offset: Offset(0, 10),
              ),
            ],
            initialsStyle: GoogleFonts.plusJakartaSans(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user.displayName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: ProfileScreen._text,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  user.email,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: ProfileScreen._muted,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _StatusChip(
                      icon: Icons.person_rounded,
                      label: user.roleLabel,
                      foreground: const Color(0xFF4B3FF2),
                      background: const Color(0xFFEDEBFF),
                    ),
                    _StatusChip(
                      icon: user.hasVerifiedEmail
                          ? Icons.verified_rounded
                          : Icons.error_outline_rounded,
                      label: user.hasVerifiedEmail
                          ? 'Email aman'
                          : 'Email belum valid',
                      foreground: user.hasVerifiedEmail
                          ? const Color(0xFF14804A)
                          : const Color(0xFFB45309),
                      background: user.hasVerifiedEmail
                          ? const Color(0xFFE5F8ED)
                          : const Color(0xFFFFF3DA),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final List<_InfoRowData> rows;

  const _InfoSection({required this.title, required this.rows});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: ProfileScreen._text,
            ),
          ),
          const SizedBox(height: 8),
          for (var i = 0; i < rows.length; i++) ...[
            _InfoRow(data: rows[i]),
            if (i != rows.length - 1)
              const Divider(height: 1, color: Color(0xFFF0EEF6)),
          ],
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final _InfoRowData data;

  const _InfoRow({required this.data});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        children: [
          Container(
            height: 34,
            width: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFF3F1FF),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(data.icon, size: 18, color: ProfileScreen._primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  data.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: ProfileScreen._muted,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  data.value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: ProfileScreen._text,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color foreground;
  final Color background;

  const _StatusChip({
    required this.icon,
    required this.label,
    required this.foreground,
    required this.background,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: foreground),
          const SizedBox(width: 5),
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: foreground,
            ),
          ),
        ],
      ),
    );
  }
}

class _LogoutButton extends StatelessWidget {
  final VoidCallback onTap;

  const _LogoutButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFFFD4D4)),
          ),
          child: Row(
            children: [
              const Icon(Icons.logout_rounded, color: Color(0xFFE25555)),
              const SizedBox(width: 12),
              Text(
                'Keluar',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFFE25555),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptySessionCard extends StatelessWidget {
  const _EmptySessionCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        'Sesi profil belum tersedia.',
        style: GoogleFonts.plusJakartaSans(
          fontSize: 14,
          fontWeight: FontWeight.w700,
          color: ProfileScreen._muted,
        ),
      ),
    );
  }
}

class _CircleActionButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _CircleActionButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          height: 42,
          width: 42,
          child: Icon(icon, color: const Color(0xFF565C75), size: 21),
        ),
      ),
    );
  }
}

class _InfoRowData {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRowData({
    required this.icon,
    required this.label,
    required this.value,
  });
}

String _displayValue(String? value) {
  final text = value?.trim() ?? '';
  return text.isEmpty ? 'Belum diisi' : text;
}

String _accountStatusLabel(String value) {
  switch (value.toLowerCase()) {
    case 'active':
      return 'Aktif';
    case 'banned':
      return 'Diblokir';
    case 'inactive':
      return 'Tidak aktif';
  }

  return _displayValue(value);
}

String _affiliationStatusLabel(String? value) {
  switch ((value ?? '').toLowerCase()) {
    case 'verified':
      return 'Terverifikasi';
    case 'rejected':
      return 'Ditolak';
    case 'pending':
      return 'Menunggu verifikasi';
  }

  return _displayValue(value);
}

String _affiliationTypeLabel(String? value) {
  switch ((value ?? '').toLowerCase()) {
    case 'university':
      return 'Universitas';
    case 'school':
      return 'Sekolah';
    case 'organization':
      return 'Organisasi';
    case 'other':
      return 'Lainnya';
  }

  return _displayValue(value);
}

String _studentIdLabel(String? value) {
  final normalized = (value ?? '').trim().toUpperCase();
  return normalized.isEmpty ? 'Identitas' : normalized;
}

String _themeLabel(String? value) {
  switch ((value ?? '').toLowerCase()) {
    case 'light':
      return 'Light';
    case 'dark':
      return 'Dark';
    case 'system':
      return 'System';
  }

  return _displayValue(value);
}
