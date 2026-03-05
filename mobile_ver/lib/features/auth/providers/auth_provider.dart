import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_ver/core/config/app_mode.dart';
import 'package:mobile_ver/core/network/api_client.dart';
import 'package:mobile_ver/core/storage/local_storage.dart';
import 'dart:convert';
import '../models/user_model.dart';

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
          ref.read(userProvider.notifier).state = User.fromJson(data);
          state = true;
        } else {
          await logout();
        }
      } catch (e) {
        await LocalStorage.removeToken();
        ref.read(userProvider.notifier).state = null;
        state = false;
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
        'device_name': 'flutter-app'
      });

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
        final token = data['access_token']?.toString() ?? '';
        final userData = data['user'];

        if (token.isEmpty || userData is! Map<String, dynamic>) {
          return 'Respons login tidak valid.';
        }

        await LocalStorage.saveToken(token);
        ref.read(userProvider.notifier).state = User.fromJson(userData);
        state = true;
        return null; // success
      } else {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        return body['message']?.toString() ?? 'Login failed';
      }
    } catch (e) {
      return 'Network error occurred';
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
    ref.read(userProvider.notifier).state = null;
    state = false;
  }
}

final authProvider = StateNotifierProvider<AuthController, bool>((ref) {
  return AuthController(ref);
});
