import 'package:flutter/material.dart';

import 'package:mobile_ver/core/config/api_config.dart';
import 'package:mobile_ver/core/storage/local_storage.dart';
import 'package:mobile_ver/features/auth/models/user_model.dart';

class ProfileAvatar extends StatelessWidget {
  final User? user;
  final String fallbackName;
  final double size;
  final double borderWidth;
  final TextStyle initialsStyle;
  final List<Color> gradientColors;
  final List<BoxShadow> boxShadow;

  const ProfileAvatar({
    super.key,
    required this.user,
    required this.fallbackName,
    required this.size,
    required this.initialsStyle,
    required this.gradientColors,
    this.borderWidth = 2,
    this.boxShadow = const [],
  });

  @override
  Widget build(BuildContext context) {
    final avatarUrl = _resolveAvatarUrl(user);

    return Container(
      height: size,
      width: size,
      padding: EdgeInsets.all(borderWidth),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white,
        boxShadow: boxShadow,
      ),
      child: ClipOval(
        child: avatarUrl == null
            ? _InitialsAvatar(
                name: fallbackName,
                style: initialsStyle,
                gradientColors: gradientColors,
              )
            : FutureBuilder<String?>(
                future: LocalStorage.getToken(),
                builder: (context, snapshot) {
                  final token = snapshot.data?.trim();

                  if (snapshot.connectionState != ConnectionState.done ||
                      token == null ||
                      token.isEmpty) {
                    return _InitialsAvatar(
                      name: fallbackName,
                      style: initialsStyle,
                      gradientColors: gradientColors,
                    );
                  }

                  return Image.network(
                    avatarUrl,
                    headers: {
                      'Authorization': 'Bearer $token',
                      'Accept': 'image/webp,image/*',
                    },
                    fit: BoxFit.cover,
                    width: size,
                    height: size,
                    errorBuilder: (context, error, stackTrace) {
                      return _InitialsAvatar(
                        name: fallbackName,
                        style: initialsStyle,
                        gradientColors: gradientColors,
                      );
                    },
                    loadingBuilder: (context, child, loadingProgress) {
                      if (loadingProgress == null) {
                        return child;
                      }

                      return _InitialsAvatar(
                        name: fallbackName,
                        style: initialsStyle,
                        gradientColors: gradientColors,
                      );
                    },
                  );
                },
              ),
      ),
    );
  }

  String? _resolveAvatarUrl(User? user) {
    final profile = user?.profile;
    if (profile?.hasAvatar != true) {
      return null;
    }

    final resolved =
        ApiConfig.resolveMediaUrl(profile?.avatarUrl) ??
        ApiConfig.endpointUri('/profile/avatar').toString();
    final version = profile?.avatarUpdatedAt?.trim();

    if (version == null || version.isEmpty) {
      return resolved;
    }

    final uri = Uri.tryParse(resolved);
    if (uri == null) {
      return resolved;
    }

    return uri
        .replace(queryParameters: {...uri.queryParameters, 'v': version})
        .toString();
  }
}

class _InitialsAvatar extends StatelessWidget {
  final String name;
  final TextStyle style;
  final List<Color> gradientColors;

  const _InitialsAvatar({
    required this.name,
    required this.style,
    required this.gradientColors,
  });

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          colors: gradientColors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(child: Text(_initialsFromName(name), style: style)),
    );
  }
}

String _initialsFromName(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList();

  if (parts.isEmpty) {
    return 'PL';
  }

  if (parts.length == 1) {
    final chunk = parts.first;
    return chunk.substring(0, chunk.length >= 2 ? 2 : 1).toUpperCase();
  }

  return (parts.first[0] + parts.last[0]).toUpperCase();
}
