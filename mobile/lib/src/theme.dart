import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

/// Everyday Pharmacy logo colours: "PHARMACY" navy, "EVERYDAY" sun yellow.
const _brand = Color(0xFF071C94);
const _accent = Color(0xFFF8B800);

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
    onSecondary: _brand,
  );
  // Light: a cool sky-white ground with white cards and navy ink, so the navy
  // reads as the one brand colour and the sun yellow stays an accent.
  final scheme = dark
      ? seeded.copyWith(
          surface: const Color(0xFF0B0F1C),
          surfaceContainerLowest: const Color(0xFF10162A),
          surfaceContainerLow: const Color(0xFF151C33),
          outlineVariant: const Color(0xFF2A3350),
          secondaryContainer: const Color(0xFF1A2659),
          onSecondaryContainer: const Color(0xFFD2DCFF),
          tertiary: const Color(0xFFFFC94A),
          onTertiary: const Color(0xFF3D2800),
          tertiaryContainer: const Color(0xFF4A3300),
          onTertiaryContainer: const Color(0xFFFFE3A3),
        )
      : seeded.copyWith(
          primary: _brand,
          onPrimary: Colors.white,
          // The logo's cloud blue.
          primaryContainer: const Color(0xFFDCEBFA),
          onPrimaryContainer: const Color(0xFF05105C),
          // Tonal buttons and chips stay in the navy family; yellow is for fills
          // with navy ink and stars only, since yellow text fails on white.
          secondaryContainer: const Color(0xFFE6EEFB),
          onSecondaryContainer: const Color(0xFF0B1E73),
          // Rx and caution notes: the logo's sun, deepened for readable text.
          tertiary: const Color(0xFF9A5B00),
          onTertiary: Colors.white,
          tertiaryContainer: const Color(0xFFFFEDB8),
          onTertiaryContainer: const Color(0xFF4A2C00),
          surface: const Color(0xFFF3F6FB),
          surfaceContainerLowest: Colors.white,
          surfaceContainerLow: const Color(0xFFF8FAFD),
          surfaceContainer: const Color(0xFFEDF1F8),
          surfaceContainerHigh: const Color(0xFFE7ECF5),
          surfaceContainerHighest: const Color(0xFFE0E6F1),
          onSurface: const Color(0xFF111A33),
          onSurfaceVariant: const Color(0xFF525D78),
          outline: const Color(0xFF8790A8),
          outlineVariant: const Color(0xFFDCE2EE),
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
    // which is the sun yellow kept for stars and fills.
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
/// so panels switch to the deep container navy to stay a slab, not a glow.
({Color fill, Color ink}) brandSlab(ColorScheme scheme) =>
    scheme.brightness == Brightness.dark
    ? (fill: scheme.primaryContainer, ink: scheme.onPrimaryContainer)
    : (fill: scheme.primary, ink: scheme.onPrimary);

/// The brand slab as a diagonal sweep from the logo navy to a brighter royal
/// blue, for headers and the cart bar. Dark mode keeps it subtle so the light
/// ink stays readable.
LinearGradient brandGradient(ColorScheme scheme) {
  final dark = scheme.brightness == Brightness.dark;
  final fill = brandSlab(scheme).fill;
  return LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [
      fill,
      Color.lerp(
        fill,
        dark ? scheme.primary : const Color(0xFF3B6EF0),
        dark ? 0.2 : 0.45,
      )!,
    ],
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
