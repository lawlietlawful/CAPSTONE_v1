import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/constants/app_colors.dart';
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

  // On 401 (expired/invalid token): clear the session and return to login.
  ApiService.onUnauthorized = () async {
    ApiService.clearToken();
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    navigatorKey.currentState
        ?.pushNamedAndRemoveUntil('/login', (route) => false);
  };

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
        theme: ThemeData(
          useMaterial3: true,
          colorScheme: ColorScheme.fromSeed(seedColor: AppColors.accent),
          textTheme: GoogleFonts.interTextTheme(ThemeData.light().textTheme),
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
          '/activate': (_) => const ActivateAccountScreen(),
          '/home': (_) => const MainShell(),
        },
      ),
    );
  }
}
