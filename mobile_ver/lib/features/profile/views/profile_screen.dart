import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';

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
  final _displayNameController = TextEditingController();
  final _bioController = TextEditingController();
  final _phoneController = TextEditingController();
  final _dateOfBirthController = TextEditingController();
  final _locationController = TextEditingController();
  final _timezoneController = TextEditingController();
  String _gender = '';
  String _themePreference = 'system';
  String _locale = 'id';
  bool _isEditing = false;
  bool _isSaving = false;
  bool _isAvatarBusy = false;

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
  void dispose() {
    _displayNameController.dispose();
    _bioController.dispose();
    _phoneController.dispose();
    _dateOfBirthController.dispose();
    _locationController.dispose();
    _timezoneController.dispose();
    super.dispose();
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
                onEdit: user == null ? null : () => _startEditing(user),
                onCancelEdit: _isEditing ? _stopEditing : null,
                isEditing: _isEditing,
              ),
              const SizedBox(height: 16),
              if (user == null)
                const _EmptySessionCard()
              else ...[
                _ProfileHeaderCard(user: user),
                const SizedBox(height: 14),
                _AvatarActionCard(
                  isBusy: _isAvatarBusy,
                  hasAvatar: user.profile?.hasAvatar == true,
                  onPickAvatar: _pickAvatar,
                  onDeleteAvatar: _deleteAvatar,
                ),
                const SizedBox(height: 14),
                if (_isEditing)
                  _ProfileEditSection(
                    isSaving: _isSaving,
                    displayNameController: _displayNameController,
                    bioController: _bioController,
                    phoneController: _phoneController,
                    dateOfBirthController: _dateOfBirthController,
                    locationController: _locationController,
                    timezoneController: _timezoneController,
                    gender: _gender,
                    themePreference: _themePreference,
                    locale: _locale,
                    onGenderChanged: (value) =>
                        setState(() => _gender = value ?? ''),
                    onThemeChanged: (value) =>
                        setState(() => _themePreference = value ?? 'system'),
                    onLocaleChanged: (value) =>
                        setState(() => _locale = value ?? 'id'),
                    onSave: _saveProfile,
                    onCancel: _stopEditing,
                  )
                else ...[
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
                ],
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

  void _startEditing(User user) {
    final profile = user.profile;

    _displayNameController.text = profile?.displayName ?? '';
    _bioController.text = profile?.bio ?? '';
    _phoneController.text = profile?.phone ?? '';
    _dateOfBirthController.text = profile?.dateOfBirth ?? '';
    _locationController.text = profile?.location ?? '';
    _timezoneController.text = profile?.timezone ?? 'Asia/Jakarta';
    _gender = _allowedValue(profile?.gender, const {
      '',
      'female',
      'male',
      'other',
      'prefer_not_to_say',
    }, '');
    _themePreference = _allowedValue(profile?.themePreference, const {
      'system',
      'light',
      'dark',
    }, 'system');
    _locale = _allowedValue(profile?.locale, const {'id', 'en'}, 'id');

    setState(() => _isEditing = true);
  }

  void _stopEditing() {
    if (_isSaving) return;
    setState(() => _isEditing = false);
  }

  Future<void> _saveProfile() async {
    if (_isSaving) return;

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isSaving = true);

    final result = await ref
        .read(authProvider.notifier)
        .updateProfile(
          displayName: _displayNameController.text,
          bio: _bioController.text,
          phone: _phoneController.text,
          dateOfBirth: _dateOfBirthController.text,
          gender: _gender,
          location: _locationController.text,
          themePreference: _themePreference,
          timezone: _timezoneController.text,
          locale: _locale,
        );

    if (!mounted) return;
    setState(() {
      _isSaving = false;
      if (result.isSuccess) {
        _isEditing = false;
      }
    });

    messenger.showSnackBar(SnackBar(content: Text(result.message)));
  }

  Future<void> _pickAvatar() async {
    if (_isAvatarBusy) return;

    final messenger = ScaffoldMessenger.of(context);
    final picker = ImagePicker();
    final image = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 256,
      maxHeight: 256,
      imageQuality: 75,
      requestFullMetadata: false,
    );

    if (image == null) return;

    setState(() => _isAvatarBusy = true);
    final result = await ref
        .read(authProvider.notifier)
        .uploadProfileAvatar(image.path);

    if (!mounted) return;
    setState(() => _isAvatarBusy = false);
    messenger.showSnackBar(SnackBar(content: Text(result.message)));
  }

  Future<void> _deleteAvatar() async {
    if (_isAvatarBusy) return;

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isAvatarBusy = true);
    final result = await ref.read(authProvider.notifier).deleteProfileAvatar();

    if (!mounted) return;
    setState(() => _isAvatarBusy = false);
    messenger.showSnackBar(SnackBar(content: Text(result.message)));
  }
}

class _TitleBar extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onRefresh;
  final VoidCallback? onEdit;
  final VoidCallback? onCancelEdit;
  final bool isEditing;

  const _TitleBar({
    required this.onBack,
    required this.onRefresh,
    required this.onEdit,
    required this.onCancelEdit,
    required this.isEditing,
  });

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
        const SizedBox(width: 8),
        _CircleActionButton(
          icon: isEditing ? Icons.close_rounded : Icons.edit_outlined,
          onTap: isEditing ? onCancelEdit : onEdit,
        ),
      ],
    );
  }
}

class _AvatarActionCard extends StatelessWidget {
  final bool isBusy;
  final bool hasAvatar;
  final VoidCallback onPickAvatar;
  final VoidCallback onDeleteAvatar;

  const _AvatarActionCard({
    required this.isBusy,
    required this.hasAvatar,
    required this.onPickAvatar,
    required this.onDeleteAvatar,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          const Icon(
            Icons.photo_camera_outlined,
            color: ProfileScreen._primary,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              isBusy ? 'Memproses foto...' : 'Foto profil',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: ProfileScreen._text,
              ),
            ),
          ),
          _SmallActionButton(
            label: 'Pilih',
            onTap: isBusy ? null : onPickAvatar,
          ),
          if (hasAvatar) ...[
            const SizedBox(width: 8),
            _SmallActionButton(
              label: 'Hapus',
              isDanger: true,
              onTap: isBusy ? null : onDeleteAvatar,
            ),
          ],
        ],
      ),
    );
  }
}

class _SmallActionButton extends StatelessWidget {
  final String label;
  final VoidCallback? onTap;
  final bool isPrimary;
  final bool isDanger;

  const _SmallActionButton({
    required this.label,
    required this.onTap,
    this.isPrimary = false,
    this.isDanger = false,
  });

  @override
  Widget build(BuildContext context) {
    final disabled = onTap == null;
    final background = isPrimary
        ? ProfileScreen._primary
        : isDanger
        ? const Color(0xFFFFF1F2)
        : const Color(0xFFF5F4FA);
    final foreground = isPrimary
        ? Colors.white
        : isDanger
        ? const Color(0xFFE25555)
        : ProfileScreen._text;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 160),
          opacity: disabled ? 0.55 : 1,
          child: Container(
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isPrimary
                    ? ProfileScreen._primary
                    : isDanger
                    ? const Color(0xFFFFD4D4)
                    : const Color(0xFFE7E3F3),
              ),
            ),
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: foreground,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ProfileTextField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String? hintText;
  final int? maxLength;
  final int maxLines;
  final TextInputType? keyboardType;

  const _ProfileTextField({
    required this.label,
    required this.controller,
    this.hintText,
    this.maxLength,
    this.maxLines = 1,
    this.keyboardType,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: controller,
        maxLength: maxLength,
        maxLines: maxLines,
        keyboardType: keyboardType,
        style: GoogleFonts.plusJakartaSans(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          color: ProfileScreen._text,
        ),
        decoration: _fieldDecoration(label, hintText),
      ),
    );
  }
}

class _ProfileSelectField extends StatelessWidget {
  final String label;
  final String value;
  final Map<String, String> items;
  final ValueChanged<String?> onChanged;

  const _ProfileSelectField({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        initialValue: items.containsKey(value) ? value : items.keys.first,
        isExpanded: true,
        icon: const Icon(Icons.keyboard_arrow_down_rounded),
        style: GoogleFonts.plusJakartaSans(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          color: ProfileScreen._text,
        ),
        decoration: _fieldDecoration(label, null),
        items: items.entries
            .map(
              (entry) => DropdownMenuItem<String>(
                value: entry.key,
                child: Text(
                  entry.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            )
            .toList(),
        onChanged: onChanged,
      ),
    );
  }
}

class _ProfileEditSection extends StatelessWidget {
  final bool isSaving;
  final TextEditingController displayNameController;
  final TextEditingController bioController;
  final TextEditingController phoneController;
  final TextEditingController dateOfBirthController;
  final TextEditingController locationController;
  final TextEditingController timezoneController;
  final String gender;
  final String themePreference;
  final String locale;
  final ValueChanged<String?> onGenderChanged;
  final ValueChanged<String?> onThemeChanged;
  final ValueChanged<String?> onLocaleChanged;
  final VoidCallback onSave;
  final VoidCallback onCancel;

  const _ProfileEditSection({
    required this.isSaving,
    required this.displayNameController,
    required this.bioController,
    required this.phoneController,
    required this.dateOfBirthController,
    required this.locationController,
    required this.timezoneController,
    required this.gender,
    required this.themePreference,
    required this.locale,
    required this.onGenderChanged,
    required this.onThemeChanged,
    required this.onLocaleChanged,
    required this.onSave,
    required this.onCancel,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
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
            'Edit Profil',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: ProfileScreen._text,
            ),
          ),
          const SizedBox(height: 14),
          _ProfileTextField(
            label: 'Nama tampilan',
            controller: displayNameController,
            maxLength: 100,
          ),
          _ProfileTextField(
            label: 'Nomor kontak',
            controller: phoneController,
            maxLength: 30,
            keyboardType: TextInputType.phone,
          ),
          _ProfileTextField(
            label: 'Tanggal lahir',
            controller: dateOfBirthController,
            hintText: 'YYYY-MM-DD',
            keyboardType: TextInputType.datetime,
          ),
          _ProfileSelectField(
            label: 'Gender',
            value: gender,
            onChanged: onGenderChanged,
            items: const {
              '': 'Tidak diisi',
              'female': 'Perempuan',
              'male': 'Laki-laki',
              'other': 'Lainnya',
              'prefer_not_to_say': 'Pilih untuk tidak menyebutkan',
            },
          ),
          _ProfileTextField(
            label: 'Lokasi',
            controller: locationController,
            maxLength: 120,
          ),
          _ProfileSelectField(
            label: 'Tema profil',
            value: themePreference,
            onChanged: onThemeChanged,
            items: const {
              'system': 'Ikuti perangkat',
              'light': 'Light',
              'dark': 'Dark',
            },
          ),
          _ProfileTextField(
            label: 'Zona waktu',
            controller: timezoneController,
            maxLength: 64,
            hintText: 'Asia/Jakarta',
          ),
          _ProfileSelectField(
            label: 'Bahasa',
            value: locale,
            onChanged: onLocaleChanged,
            items: const {'id': 'Indonesia', 'en': 'English'},
          ),
          _ProfileTextField(
            label: 'Bio singkat',
            controller: bioController,
            maxLength: 500,
            maxLines: 4,
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _SmallActionButton(
                  label: 'Batal',
                  onTap: isSaving ? null : onCancel,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _SmallActionButton(
                  label: isSaving ? 'Menyimpan...' : 'Simpan',
                  isPrimary: true,
                  onTap: isSaving ? null : onSave,
                ),
              ),
            ],
          ),
        ],
      ),
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
  final VoidCallback? onTap;

  const _CircleActionButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 160),
          opacity: onTap == null ? 0.5 : 1,
          child: SizedBox(
            height: 42,
            width: 42,
            child: Icon(icon, color: const Color(0xFF565C75), size: 21),
          ),
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

InputDecoration _fieldDecoration(String label, String? hintText) {
  return InputDecoration(
    labelText: label,
    hintText: hintText,
    counterText: '',
    filled: true,
    fillColor: const Color(0xFFF8F7FC),
    labelStyle: GoogleFonts.plusJakartaSans(
      fontSize: 12,
      fontWeight: FontWeight.w700,
      color: ProfileScreen._muted,
    ),
    hintStyle: GoogleFonts.plusJakartaSans(
      fontSize: 12,
      fontWeight: FontWeight.w600,
      color: const Color(0xFF9DA3B8),
    ),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: Color(0xFFE7E3F3)),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: Color(0xFFE7E3F3)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: ProfileScreen._primary, width: 1.4),
    ),
  );
}

String _allowedValue(String? value, Set<String> allowed, String fallback) {
  final normalized = value?.trim() ?? '';
  return allowed.contains(normalized) ? normalized : fallback;
}
