import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

const _brand = Color(0xFF0F5C52);
const _accent = Color(0xFFD9982B);

/// Static weights cut from the variable font: Flutter's fontWeight does not
/// reliably drive a variable font's weight axis on Android. Chosen for a plain
/// zero, tabular figures, and a peso sign (₱) many UI faces lack.
const appFontFamily = 'Manrope';

/// Shared corners for cards and controls, small details, and modal surfaces.
const appSurfaceRadius = 12.0;
const appDetailRadius = 6.0;
const appModalRadius = 24.0;

ThemeData buildAppTheme({Brightness brightness = Brightness.light}) {
  final dark = brightness == Brightness.dark;
  final seeded = ColorScheme.fromSeed(
    seedColor: _brand,
    brightness: brightness,
    secondary: _accent,
  );
  // Light: a cool mineral ground with white cards and green-black ink, so the
  // teal reads as the one brand colour instead of a tint over everything.
  final scheme = dark
      ? seeded.copyWith(
          surface: const Color(0xFF0C1311),
          surfaceContainerLowest: const Color(0xFF111A17),
          surfaceContainerLow: const Color(0xFF16201D),
          outlineVariant: const Color(0xFF2A3633),
          secondaryContainer: const Color(0xFF173A34),
          onSecondaryContainer: const Color(0xFFBFE9DF),
        )
      : seeded.copyWith(
          primary: _brand,
          onPrimary: Colors.white,
          primaryContainer: const Color(0xFFD3ECE5),
          onPrimaryContainer: const Color(0xFF07332D),
          // Tonal buttons and chips stay in the teal family; amber is for stars only.
          secondaryContainer: const Color(0xFFE2F0EC),
          onSecondaryContainer: const Color(0xFF0B4A41),
          tertiary: const Color(0xFF3E4C9A),
          onTertiary: Colors.white,
          tertiaryContainer: const Color(0xFFE5E8F8),
          onTertiaryContainer: const Color(0xFF1E2766),
          surface: const Color(0xFFF3F5F2),
          surfaceContainerLowest: Colors.white,
          surfaceContainerLow: const Color(0xFFF8FAF8),
          surfaceContainer: const Color(0xFFEDF1EE),
          surfaceContainerHigh: const Color(0xFFE7ECE9),
          surfaceContainerHighest: const Color(0xFFE1E7E3),
          onSurface: const Color(0xFF13211D),
          onSurfaceVariant: const Color(0xFF55655F),
          outline: const Color(0xFF8A9893),
          outlineVariant: const Color(0xFFDDE4E0),
        );
  final surface = scheme.surface;
  final surfaceLowest = scheme.surfaceContainerLowest;
  final hairline = scheme.outlineVariant;
  final textTheme = _textTheme(scheme);

  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: surface,
    fontFamily: appFontFamily,
    textTheme: textTheme,
    // Clerk's sign-in widgets read their colors from here instead of Clerk purple.
    extensions: [
      ClerkThemeExtension(
        colors: ClerkThemeColors(
          background: surfaceLowest,
          altBackground: scheme.surfaceContainerHigh,
          borderSide: scheme.outline,
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
      scrolledUnderElevation: 0,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: textTheme.titleLarge?.copyWith(
        fontSize: 21,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.5,
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        side: BorderSide(color: hairline),
      ),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: surfaceLowest,
      modalBackgroundColor: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      dragHandleColor: scheme.outline,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(appModalRadius),
        ),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: surfaceLowest,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appModalRadius),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: scheme.inverseSurface,
      contentTextStyle: textTheme.bodyMedium?.copyWith(
        color: scheme.onInverseSurface,
        fontWeight: FontWeight.w600,
      ),
      actionTextColor: scheme.inversePrimary,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: surfaceLowest,
      hintStyle: TextStyle(color: scheme.onSurfaceVariant),
      labelStyle: TextStyle(color: scheme.onSurfaceVariant),
      floatingLabelStyle: TextStyle(color: scheme.primary),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      // Field edges need ~3:1 against the fill to read as fields; the card hairline is too faint.
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        borderSide: BorderSide(color: scheme.outline),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        borderSide: BorderSide(color: scheme.outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
        borderSide: BorderSide(color: scheme.primary, width: 1.6),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        textStyle: textTheme.labelLarge?.copyWith(fontSize: 15.5),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        foregroundColor: scheme.onSurface,
        side: BorderSide(color: scheme.outlineVariant, width: 1.2),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        textStyle: textTheme.labelLarge,
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        textStyle: textTheme.labelLarge,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: surfaceLowest,
      indicatorColor: scheme.primary.withValues(alpha: dark ? 0.24 : 0.10),
      indicatorShape: const StadiumBorder(),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      height: 68,
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          size: 24,
          color: states.contains(WidgetState.selected)
              ? scheme.primary
              : scheme.onSurfaceVariant,
        ),
      ),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => textTheme.labelMedium!.copyWith(
          fontSize: 12,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w800
              : FontWeight.w600,
          color: states.contains(WidgetState.selected)
              ? scheme.primary
              : scheme.onSurfaceVariant,
        ),
      ),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: scheme.primary,
      unselectedLabelColor: scheme.onSurfaceVariant,
      labelStyle: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
      unselectedLabelStyle: textTheme.titleSmall?.copyWith(
        fontWeight: FontWeight.w600,
      ),
      indicatorSize: TabBarIndicatorSize.label,
      indicator: UnderlineTabIndicator(
        borderSide: BorderSide(color: scheme.primary, width: 3),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(3)),
      ),
      dividerColor: hairline,
    ),
    badgeTheme: BadgeThemeData(
      backgroundColor: scheme.primary,
      textColor: scheme.onPrimary,
      textStyle: const TextStyle(
        fontFamily: appFontFamily,
        fontSize: 10,
        fontWeight: FontWeight.w800,
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: surfaceLowest,
      shape: const StadiumBorder(),
      side: BorderSide(color: hairline),
      selectedColor: scheme.primary,
      checkmarkColor: scheme.onPrimary,
      labelStyle: textTheme.labelLarge?.copyWith(
        color: scheme.onSurface,
        fontWeight: FontWeight.w600,
      ),
      secondaryLabelStyle: textTheme.labelLarge?.copyWith(
        color: scheme.onPrimary,
      ),
    ),
    // Without this, selected radio tiles fall back to colorScheme.secondary,
    // which is the amber kept for review stars.
    radioTheme: RadioThemeData(
      fillColor: WidgetStateProperty.resolveWith(
        (states) => states.contains(WidgetState.disabled)
            ? scheme.onSurface.withValues(alpha: 0.38)
            : states.contains(WidgetState.selected)
            ? scheme.primary
            : scheme.onSurfaceVariant,
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
      titleTextStyle: textTheme.bodyLarge?.copyWith(
        fontWeight: FontWeight.w600,
      ),
    ),
    dividerTheme: DividerThemeData(color: hairline, thickness: 1, space: 1),
  );
}

/// Material type roles in the brand face: heavier titles with tight tracking,
/// relaxed body leading, and tabular figures wherever prices appear.
TextTheme _textTheme(ColorScheme scheme) {
  const tabular = [FontFeature.tabularFigures()];
  final ink = scheme.onSurface;

  TextStyle style(
    double size,
    FontWeight weight, {
    double height = 1.3,
    double tracking = 0,
  }) => TextStyle(
    fontFamily: appFontFamily,
    fontSize: size,
    fontWeight: weight,
    height: height,
    letterSpacing: tracking,
    color: ink,
    fontFeatures: tabular,
  );

  return TextTheme(
    displayLarge: style(52, FontWeight.w800, height: 1.05, tracking: -1.6),
    displayMedium: style(42, FontWeight.w800, height: 1.06, tracking: -1.2),
    displaySmall: style(34, FontWeight.w800, height: 1.08, tracking: -0.9),
    headlineLarge: style(30, FontWeight.w800, height: 1.12, tracking: -0.8),
    headlineMedium: style(26, FontWeight.w800, height: 1.14, tracking: -0.6),
    headlineSmall: style(22, FontWeight.w800, height: 1.18, tracking: -0.45),
    titleLarge: style(20, FontWeight.w700, height: 1.22, tracking: -0.35),
    titleMedium: style(16, FontWeight.w700, height: 1.3, tracking: -0.15),
    titleSmall: style(14, FontWeight.w700, height: 1.3, tracking: -0.05),
    bodyLarge: style(16, FontWeight.w400, height: 1.45),
    bodyMedium: style(14, FontWeight.w400, height: 1.45),
    bodySmall: style(
      12.5,
      FontWeight.w400,
      height: 1.4,
    ).copyWith(color: scheme.onSurfaceVariant),
    labelLarge: style(14, FontWeight.w700, height: 1.25, tracking: 0.05),
    labelMedium: style(12.5, FontWeight.w600, height: 1.3),
    labelSmall: style(11, FontWeight.w700, height: 1.25, tracking: 0.2),
  );
}

/// Fill and ink for solid brand panels. Dark mode's primary is a light tone,
/// so panels switch to the deep container teal to stay a slab, not a glow.
({Color fill, Color ink}) brandSlab(ColorScheme scheme) =>
    scheme.brightness == Brightness.dark
    ? (fill: scheme.primaryContainer, ink: scheme.onPrimaryContainer)
    : (fill: scheme.primary, ink: scheme.onPrimary);

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
