import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

const _brand = Color(0xFF14665A);
const _accent = Color(0xFFE0A63B);
const _lightSurface = Color(0xFFF7F6F2);
const _darkSurface = Color(0xFF0F1513);

ThemeData buildAppTheme({Brightness brightness = Brightness.light}) {
  final dark = brightness == Brightness.dark;
  final scheme = ColorScheme.fromSeed(
    seedColor: _brand,
    brightness: brightness,
    secondary: _accent,
    surface: dark ? _darkSurface : _lightSurface,
  );
  final surface = scheme.surface;
  final surfaceLowest = scheme.surfaceContainerLowest;
  final hairline = scheme.outlineVariant.withValues(alpha: 0.6);

  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: surface,
    fontFamily: 'Roboto',
    // Clerk's sign-in widgets read their colors from here instead of Clerk purple.
    extensions: [
      ClerkThemeExtension(
        colors: ClerkThemeColors(
          background: surfaceLowest,
          altBackground: scheme.surfaceContainerHigh,
          borderSide: scheme.outlineVariant,
          text: scheme.onSurface,
          icon: scheme.onSurfaceVariant,
          lightweightText: scheme.onSurfaceVariant,
          error: scheme.error,
          accent: scheme.primary,
        ),
      ),
    ],
    appBarTheme: AppBarTheme(
      backgroundColor: surface,
      foregroundColor: scheme.onSurface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        color: scheme.onSurface,
        fontFamily: 'Roboto',
        fontSize: 22,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.4,
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: hairline),
      ),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: surfaceLowest,
      modalBackgroundColor: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      dragHandleColor: scheme.outline,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: scheme.inverseSurface,
      contentTextStyle: TextStyle(
        color: scheme.onInverseSurface,
        fontFamily: 'Roboto',
      ),
      actionTextColor: scheme.inversePrimary,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: surfaceLowest,
      hintStyle: TextStyle(
        color: scheme.onSurfaceVariant,
        fontFamily: 'Roboto',
      ),
      labelStyle: TextStyle(
        color: scheme.onSurfaceVariant,
        fontFamily: 'Roboto',
      ),
      floatingLabelStyle: TextStyle(
        color: scheme.primary,
        fontFamily: 'Roboto',
      ),
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
        textStyle: const TextStyle(
          fontFamily: 'Roboto',
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: surfaceLowest,
      indicatorColor: scheme.primaryContainer,
      surfaceTintColor: Colors.transparent,
      elevation: 1,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontFamily: 'Roboto',
          fontSize: 11,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w600,
          color: states.contains(WidgetState.selected)
              ? scheme.primary
              : scheme.onSurfaceVariant,
        ),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: surfaceLowest,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      side: BorderSide(color: scheme.outlineVariant),
      selectedColor: scheme.primary,
      checkmarkColor: scheme.onPrimary,
      labelStyle: TextStyle(
        color: scheme.onSurface,
        fontFamily: 'Roboto',
        fontWeight: FontWeight.w600,
      ),
      secondaryLabelStyle: TextStyle(
        color: scheme.onPrimary,
        fontFamily: 'Roboto',
        fontWeight: FontWeight.w700,
      ),
    ),
    progressIndicatorTheme: ProgressIndicatorThemeData(
      color: scheme.primary,
      linearTrackColor: scheme.surfaceContainerHighest,
      circularTrackColor: scheme.surfaceContainerHighest,
    ),
    listTileTheme: ListTileThemeData(
      iconColor: scheme.onSurfaceVariant,
      textColor: scheme.onSurface,
    ),
    dividerTheme: DividerThemeData(color: hairline, thickness: 1),
  );
}

Color statusColour(BuildContext context, String status) {
  final scheme = Theme.of(context).colorScheme;
  final dark = scheme.brightness == Brightness.dark;

  return switch (status) {
    'pending' => dark ? const Color(0xFFF2C879) : const Color(0xFF8A5A12),
    'confirmed' ||
    'processing' => dark ? const Color(0xFF8AB4F8) : const Color(0xFF2B6CB0),
    'shipped' => dark ? const Color(0xFFC4A7F5) : const Color(0xFF6B46C1),
    'delivered' ||
    'completed' => dark ? const Color(0xFF7ADFA0) : const Color(0xFF2F855A),
    'cancelled' || 'rejected' || 'denied' => scheme.error,
    _ => scheme.onSurfaceVariant,
  };
}
