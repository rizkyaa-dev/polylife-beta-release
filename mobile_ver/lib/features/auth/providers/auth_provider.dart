import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/core/storage/local_storage.dart';
import 'package:mobile_ver/core/sync/sync_service.dart';

import '../models/user_model.dart';

class AuthActionResult {
  const AuthActionResult._({required this.isSuccess, required this.message});

  const AuthActionResult.success(String message)
    : this._(isSuccess: true, message: message);

  const AuthActionResult.failure(String message)
    : this._(isSuccess: false, message: message);

  final bool isSuccess;
  final String message;
}

final userProvider = StateProvider<User?>((ref) => null);
final authLoadingProvider = StateProvider<bool>((ref) => true);

class AuthController extends StateNotifier<bool> {
  final Ref ref;

  AuthController(this.ref) : super(false) {
    _checkAuthStatus();
  }

  static final User _mockUser = User(
    id: 999,
    name: 'UI Tester',
    email: 'ui.tester@polylife.local',
    role: 'user',
    roleLabel: 'Pengguna',
    accountStatus: 'active',
    emailVerifiedAt: DateTime.now().toIso8601String(),
    affiliation: const UserAffiliation(
      type: 'university',
      name: 'PolyLife UI Lab',
      studentIdType: 'nim',
      studentIdNumber: '000000',
      status: 'verified',
    ),
    profile: const UserProfile(
      displayName: 'UI Tester',
      themePreference: 'system',
      timezone: 'Asia/Jakarta',
      locale: 'id',
    ),
  );

  Future<void> _checkAuthStatus() async {
    if (AppMode.uiOnly) {
      ref.read(userProvider.notifier).state = _mockUser;
      state = true;
      ref.read(authLoadingProvider.notifier).state = false;
      return;
    }

    final token = await LocalStorage.getToken();
    if (token != null) {
      try {
        final response = await ApiClient.get('/auth/me');
        if (response.statusCode == 200) {
          final data = jsonDecode(response.body)['data'];
          final user = User.fromJson(Map<String, dynamic>.from(data));
          await LocalStorage.saveUser(user);
          ref.read(userProvider.notifier).state = user;
          state = true;
          await const SyncService().syncNow(user.id);
        } else {
          await logout();
        }
      } on StateError {
        await LocalStorage.removeToken();
        ref.read(userProvider.notifier).state = null;
        state = false;
      } catch (_) {
        // Keep local session on transient network/startup failure.
        // Invalid token is still handled by non-200 response above.
        final cachedUser = await LocalStorage.getCachedUser();
        ref.read(userProvider.notifier).state = cachedUser;
        state = cachedUser != null;
      }
    } else {
      ref.read(userProvider.notifier).state = null;
      state = false;
    }
    ref.read(authLoadingProvider.notifier).state = false;
  }

  Future<String?> login(String email, String password) async {
    if (AppMode.uiOnly) {
      ref.read(userProvider.notifier).state = _mockUser;
      state = true;
      return null;
    }

    try {
      final response = await ApiClient.post('/auth/login', {
        'email': email,
        'password': password,
        'device_name': 'flutter-app',
      });

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        final data =
            body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
        final token = data['access_token']?.toString() ?? '';
        final userData = data['user'];

        if (token.isEmpty || userData is! Map<String, dynamic>) {
          return 'Respons login tidak valid.';
        }

        final user = User.fromJson(userData);
        await LocalStorage.saveToken(token);
        await LocalStorage.saveUser(user);
        ref.read(userProvider.notifier).state = user;
        state = true;
        await const SyncService().syncNow(user.id);
        return null; // success
      } else {
        return _messageFromResponse(response.body, 'Login gagal.');
      }
    } on StateError catch (e) {
      return e.message;
    } catch (e) {
      return 'Network error occurred';
    }
  }

  Future<AuthActionResult> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (AppMode.uiOnly) {
      return const AuthActionResult.success(
        'Akun berhasil dibuat. Silakan login kembali.',
      );
    }

    try {
      final response = await ApiClient.post('/auth/register', {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      });

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _messageFromResponse(
        response.body,
        isSuccess ? 'Akun berhasil dibuat.' : 'Registrasi gagal.',
      );

      return isSuccess
          ? AuthActionResult.success(message)
          : AuthActionResult.failure(message);
    } on StateError catch (e) {
      return AuthActionResult.failure(e.message);
    } catch (_) {
      return const AuthActionResult.failure('Network error occurred');
    }
  }

  Future<AuthActionResult> requestPasswordReset(String email) async {
    if (AppMode.uiOnly) {
      return const AuthActionResult.success(
        'Jika email terdaftar, link reset password akan dikirim.',
      );
    }

    try {
      final response = await ApiClient.post('/auth/forgot-password', {
        'email': email,
      });

      final isSuccess = response.statusCode >= 200 && response.statusCode < 300;
      final message = _messageFromResponse(
        response.body,
        isSuccess
            ? 'Jika email terdaftar, link reset password akan dikirim.'
            : 'Permintaan reset password gagal.',
      );

      return isSuccess
          ? AuthActionResult.success(message)
          : AuthActionResult.failure(message);
    } on StateError catch (e) {
      return AuthActionResult.failure(e.message);
    } catch (_) {
      return const AuthActionResult.failure('Network error occurred');
    }
  }

  Future<AuthActionResult> refreshCurrentUser() async {
    if (AppMode.uiOnly) {
      ref.read(userProvider.notifier).state = _mockUser;
      state = true;
      return const AuthActionResult.success('Profil diperbarui.');
    }

    try {
      final response = await ApiClient.get('/auth/me');
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body)['data'];
        final user = User.fromJson(Map<String, dynamic>.from(data));
        await LocalStorage.saveUser(user);
        ref.read(userProvider.notifier).state = user;
        state = true;
        await const SyncService().syncNow(user.id);
        return const AuthActionResult.success('Profil diperbarui.');
      }

      if (response.statusCode == 401 || response.statusCode == 403) {
        await logout();
      }

      return AuthActionResult.failure(
        _messageFromResponse(response.body, 'Gagal memuat profil.'),
      );
    } on StateError catch (e) {
      return AuthActionResult.failure(e.message);
    } catch (_) {
      return const AuthActionResult.failure(
        'Profil lokal tetap dipakai. Koneksi belum tersedia.',
      );
    }
  }

  Future<void> logout() async {
    if (AppMode.uiOnly) {
      ref.read(userProvider.notifier).state = _mockUser;
      state = true;
      return;
    }

    try {
      await ApiClient.post('/auth/logout', {});
    } catch (_) {
      // Keep logout local even if server request fails.
    }
    await LocalStorage.removeToken();
    await LocalStorage.removeUser();
    ref.read(userProvider.notifier).state = null;
    state = false;
  }

  String _messageFromResponse(String responseBody, String fallback) {
    try {
      final body = jsonDecode(responseBody) as Map<String, dynamic>;
      final errors = body['errors'];

      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final firstError = errors.values.first;
        if (firstError is List && firstError.isNotEmpty) {
          return firstError.first.toString();
        }
        return firstError.toString();
      }

      return body['message']?.toString() ?? fallback;
    } catch (_) {
      return fallback;
    }
  }
}

final authProvider = StateNotifierProvider<AuthController, bool>((ref) {
  return AuthController(ref);
});
