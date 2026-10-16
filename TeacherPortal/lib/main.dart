import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/theme/app_theme.dart';
import 'core/services/api_service.dart';
import 'providers/auth_provider.dart';
import 'providers/teacher_provider.dart';
import 'screens/auth/activate_account_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/main_shell.dart';

/// Global navigator key so services (e.g. the 401 handler) can navigate
/// without a widget BuildContext.
final navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // On 401 (expired/invalid token): clear the session and everything loaded
  // for it, then return to login.
  ApiService.onUnauthorized = () async {
    ApiService.clearToken();
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    final context = navigatorKey.currentContext;
    if (context != null && context.mounted) {
      context.read<TeacherProvider>().reset();
    }
    navigatorKey.currentState
        ?.pushNamedAndRemoveUntil('/login', (route) => false);
  };

  // Load the app's font weights before the first frame, so nothing is laid
  // out in a fallback font and then clipped or shifted when Public Sans arrives.
  // Capped, so a slow or offline start isn't held up.
  try {
    await GoogleFonts.pendingFonts([
      GoogleFonts.publicSans(),
      GoogleFonts.publicSans(fontWeight: FontWeight.w500),
      GoogleFonts.publicSans(fontWeight: FontWeight.w600),
      GoogleFonts.publicSans(fontWeight: FontWeight.w700),
    ]).timeout(const Duration(seconds: 3));
  } catch (_) {}

  // Restore saved session token if present.
  final prefs = await SharedPreferences.getInstance();
  final savedToken = prefs.getString('auth_token');
  if (savedToken != null && savedToken.isNotEmpty) {
    ApiService.setToken(savedToken);
  }

  runApp(MyApp(hasSession: savedToken != null && savedToken.isNotEmpty));
}

class MyApp extends StatelessWidget {
  final bool hasSession;

  const MyApp({super.key, required this.hasSession});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => TeacherProvider()),
      ],
      child: MaterialApp(
        title: 'Teacher Portal',
        navigatorKey: navigatorKey,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        initialRoute: hasSession ? '/home' : '/login',
        routes: {
          '/login': (_) => const LoginScreen(),
          '/activate': (_) => const ActivateAccountScreen(),
          '/home': (_) => const MainShell(),
        },
      ),
    );
  }
}
