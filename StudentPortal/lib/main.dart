import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import 'core/constants/app_colors.dart';
import 'core/services/api_service.dart';
import 'core/services/auth_service.dart';
import 'providers/auth_provider.dart';
import 'providers/student_provider.dart';
import 'providers/seminar_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/session_reset.dart';
import 'providers/settings_provider.dart';
import 'screens/auth/login_screen.dart';
import 'screens/main_shell.dart';

/// Global navigator key so services (e.g. the 401 handler) can navigate
/// without a widget BuildContext.
final navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Lock to portrait orientation
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // On 401 (expired/invalid token): clear the session and everything loaded
  // for it, then return to login.
  ApiService.onUnauthorized = () async {
    await AuthService.clearLocalSession();
    final context = navigatorKey.currentContext;
    if (context != null && context.mounted) resetStudentData(context);
    navigatorKey.currentState
        ?.pushNamedAndRemoveUntil('/login', (route) => false);
  };

  // Load the app's font weights before the first frame, so nothing is laid
  // out in a fallback font and then clipped or shifted when Inter arrives.
  try {
    await GoogleFonts.pendingFonts([
      GoogleFonts.inter(),
      GoogleFonts.inter(fontWeight: FontWeight.w500),
      GoogleFonts.inter(fontWeight: FontWeight.w600),
      GoogleFonts.inter(fontWeight: FontWeight.w700),
    ]).timeout(const Duration(seconds: 3));
  } catch (_) {}

  // Restore the saved session (kept in secure storage).
  final savedToken = await AuthService.getSavedToken();
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
        ChangeNotifierProvider(create: (_) => StudentProvider()),
        ChangeNotifierProvider(create: (_) => SeminarProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
        ChangeNotifierProvider(create: (_) => SettingsProvider()),
      ],
      child: MaterialApp(
        title: 'Student Portal',
        navigatorKey: navigatorKey,
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          useMaterial3: true,
          colorScheme: ColorScheme.fromSeed(seedColor: AppColors.accent),
          textTheme: GoogleFonts.interTextTheme(
            ThemeData.light().textTheme,
          ),
          scaffoldBackgroundColor: AppColors.background,
          appBarTheme: const AppBarTheme(
            backgroundColor: AppColors.navy,
            foregroundColor: AppColors.navyText,
            elevation: 0,
            systemOverlayStyle: SystemUiOverlayStyle.light,
          ),
        ),
        initialRoute: hasSession ? '/home' : '/login',
        routes: {
          '/login': (_) => const LoginScreen(),
          '/home': (_) => const MainShell(),
        },
      ),
    );
  }
}

