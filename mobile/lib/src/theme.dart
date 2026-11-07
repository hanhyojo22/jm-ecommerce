import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

const _brand = Color(0xFF14665A);
const _accent = Color(0xFFE0A63B);

ThemeData buildAppTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: _brand,
    brightness: Brightness.light,
    secondary: _accent,
    surface: const Color(0xFFF7F6F2),
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: const Color(0xFFF7F6F2),
    fontFamily: 'Roboto',
    // Clerk's sign-in widgets read their colors from here instead of Clerk purple.
    extensions: [
      ClerkThemeExtension(
        colors: ClerkThemeColors(
          background: Colors.white,
          altBackground: Color(0xFFE4E2DB),
          borderSide: Color(0xFFD9D6CE),
          text: Color(0xFF1E2422),
          icon: Color(0xFF5B615E),
          lightweightText: Color(0xFF5B615E),
          error: Color(0xFFB3261E),
          accent: scheme.primary,
        ),
      ),
    ],
    appBarTheme: AppBarTheme(
      backgroundColor: const Color(0xFFF7F6F2),
      foregroundColor: scheme.onSurface,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        color: scheme.onSurface,
        fontSize: 22,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.4,
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: Colors.white,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.5)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: scheme.outlineVariant),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: scheme.outlineVariant),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: scheme.primary, width: 1.6),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: scheme.primaryContainer,
      elevation: 1,
      labelTextStyle: WidgetStatePropertyAll(
        TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: scheme.onSurface,
        ),
      ),
    ),
    chipTheme: ChipThemeData(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      side: BorderSide(color: scheme.outlineVariant),
      selectedColor: scheme.primary,
      labelStyle: TextStyle(
        color: scheme.onSurface,
        fontWeight: FontWeight.w600,
      ),
      secondaryLabelStyle: TextStyle(
        color: scheme.onPrimary,
        fontWeight: FontWeight.w700,
      ),
    ),
    dividerTheme: DividerThemeData(
      color: scheme.outlineVariant.withValues(alpha: 0.5),
      thickness: 1,
    ),
  );
}

Color statusColour(BuildContext context, String status) {
  final scheme = Theme.of(context).colorScheme;

  return switch (status) {
    'pending' => const Color(0xFFB7791F),
    'confirmed' || 'processing' => const Color(0xFF2B6CB0),
    'shipped' => const Color(0xFF6B46C1),
    'delivered' || 'completed' => const Color(0xFF2F855A),
    'cancelled' || 'rejected' || 'denied' => scheme.error,
    _ => scheme.onSurfaceVariant,
  };
}
