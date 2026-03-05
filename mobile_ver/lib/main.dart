import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'core/config/app_mode.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/providers/auth_provider.dart';
import 'features/auth/views/login_screen.dart';
import 'features/dashboard/views/main_layout.dart';
import 'features/dashboard/views/home_screen.dart';
import 'features/catatan/views/catatan_list_screen.dart';
import 'features/pengumuman/views/pengumuman_list_screen.dart';
import 'features/keuangan/views/keuangan_screen.dart';
import 'features/jadwal/views/jadwal_screen.dart';
import 'features/todo/views/todo_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID', null);

  runApp(
    const ProviderScope(
      child: MyApp(),
    ),
  );
}

class MyApp extends ConsumerWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final isLoading = ref.watch(authLoadingProvider);

    final router = GoRouter(
      initialLocation: '/',
      redirect: (context, state) {
        if (AppMode.uiOnly) {
          if (state.matchedLocation == '/login') {
            return '/';
          }
          return null;
        }

        if (isLoading) return null; // wait for check to finish
        final isAuthRoute = state.matchedLocation == '/login';
        
        if (!authState && !isAuthRoute) {
          return '/login';
        }
        if (authState && isAuthRoute) {
          return '/';
        }
        return null;
      },
      routes: [
        GoRoute(
          path: '/login',
          builder: (context, state) => const LoginScreen(),
        ),
        ShellRoute(
          builder: (context, state, child) => MainLayout(child: child),
          routes: [
            GoRoute(
              path: '/keuangan',
              builder: (context, state) => const KeuanganScreen(),
            ),
            GoRoute(
              path: '/jadwal',
              builder: (context, state) => const JadwalScreen(),
            ),
            GoRoute(
              path: '/',
              builder: (context, state) => const HomeScreen(),
            ),
            GoRoute(
              path: '/todo',
              builder: (context, state) => const TodoScreen(),
            ),
            GoRoute(
              path: '/catatan',
              builder: (context, state) => const CatatanListScreen(),
            ),
            GoRoute(
              path: '/pengumuman',
              builder: (context, state) => const PengumumanListScreen(),
            ),
            // Placeholder for profil
          ],
        ),
      ],
    );

    if (isLoading && !AppMode.uiOnly) {
      return MaterialApp(
        theme: AppTheme.lightTheme,
        home: const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        ),
      );
    }

    return MaterialApp.router(
      title: 'PolyLife',
      theme: AppTheme.lightTheme,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
