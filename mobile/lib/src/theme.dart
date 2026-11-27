import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

/// Everyday Pharmacy logo colours: Everyday Cobalt & Sun Gold.
const _brand = Color(0xFF0F3D9E);
const _accent = Color(0xFFFFC82C);

/// Semantic brand tokens beyond Material 3's standard ColorScheme slots.
@immutable
class AppBrandColors extends ThemeExtension<AppBrandColors> {
  const AppBrandColors({
    required this.sunGold,
    required this.onSunGold,
    required this.careTeal,
    required this.careTealSoft,
    required this.dealsBand,
    required this.dealsBandBorder,
    required this.openOnHeader,
    required this.closedOnHeader,
  });

  final Color sunGold;
  final Color onSunGold;
  final Color careTeal;
  final Color careTealSoft;
  final Color dealsBand;
  final Color dealsBandBorder;
  final Color openOnHeader;
  final Color closedOnHeader;

  static const light = AppBrandColors(
    sunGold: Color(0xFFFFC82C),
    onSunGold: Color(0xFF0D1F4F),
    careTeal: Color(0xFF0F766E),
    careTealSoft: Color(0xFFE6F6F4),
    dealsBand: Color(0xFFEBF1FF),
    dealsBandBorder: Color(0xFFDBE6FE),
    openOnHeader: Color(0xFF6EE7B7),
    closedOnHeader: Color(0xFFFCA5A5),
  );

  static const dark = AppBrandColors(
    sunGold: Color(0xFFFFC82C),
    onSunGold: Color(0xFF0D1F4F),
    careTeal: Color(0xFF5EEAD4),
    careTealSoft: Color(0xFF123635),
    dealsBand: Color(0xFF111B36),
    dealsBandBorder: Color(0xFF223159),
    openOnHeader: Color(0xFF6EE7B7),
    closedOnHeader: Color(0xFFFCA5A5),
  );

  static AppBrandColors of(BuildContext context) {
    final theme = Theme.of(context);
    return theme.extension<AppBrandColors>() ??
        (theme.brightness == Brightness.dark ? dark : light);
  }

  /// Soft pastel disc wash behind category artwork so every category has a
  /// subtle identity while staying calm.
  Color categoryTint(String slug, Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final lower = slug.toLowerCase();
    if (lower.contains('prescription') || lower.contains('rx')) {
      return isDark ? const Color(0xFF1B254A) : const Color(0xFFEEF2FF);
    }
    if (lower.contains('pain') || lower.contains('fever')) {
      return isDark ? const Color(0xFF331C28) : const Color(0xFFFFF1F2);
    }
    if (lower.contains('cough') || lower.contains('cold')) {
      return isDark ? const Color(0xFF152B3F) : const Color(0xFFE0F2FE);
    }
    if (lower.contains('vitamin') || lower.contains('supplement')) {
      return isDark ? const Color(0xFF322814) : const Color(0xFFFEF3C7);
    }
    if (lower.contains('first-aid') ||
        lower.contains('first_aid') ||
        lower.contains('wound')) {
      return isDark ? const Color(0xFF14302D) : const Color(0xFFE6F6F4);
    }
    return isDark ? const Color(0xFF1B2A52) : const Color(0xFFE8EFFD);
  }

  @override
  AppBrandColors copyWith({
    Color? sunGold,
    Color? onSunGold,
    Color? careTeal,
    Color? careTealSoft,
    Color? dealsBand,
    Color? dealsBandBorder,
    Color? openOnHeader,
    Color? closedOnHeader,
  }) => AppBrandColors(
    sunGold: sunGold ?? this.sunGold,
    onSunGold: onSunGold ?? this.onSunGold,
    careTeal: careTeal ?? this.careTeal,
    careTealSoft: careTealSoft ?? this.careTealSoft,
    dealsBand: dealsBand ?? this.dealsBand,
    dealsBandBorder: dealsBandBorder ?? this.dealsBandBorder,
    openOnHeader: openOnHeader ?? this.openOnHeader,
    closedOnHeader: closedOnHeader ?? this.closedOnHeader,
  );

  @override
  AppBrandColors lerp(covariant ThemeExtension<AppBrandColors>? other, double t) {
    if (other is! AppBrandColors) {
      return this;
    }
    return AppBrandColors(
      sunGold: Color.lerp(sunGold, other.sunGold, t)!,
      onSunGold: Color.lerp(onSunGold, other.onSunGold, t)!,
      careTeal: Color.lerp(careTeal, other.careTeal, t)!,
      careTealSoft: Color.lerp(careTealSoft, other.careTealSoft, t)!,
      dealsBand: Color.lerp(dealsBand, other.dealsBand, t)!,
      dealsBandBorder: Color.lerp(dealsBandBorder, other.dealsBandBorder, t)!,
      openOnHeader: Color.lerp(openOnHeader, other.openOnHeader, t)!,
      closedOnHeader: Color.lerp(closedOnHeader, other.closedOnHeader, t)!,
    );
  }
}

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
  // Light: warm daylight neutral ground (#F5F6F9) with crisp elevated white
  // cards, Everyday Cobalt primary, and Sun Gold accents.
  final scheme = dark
      ? seeded.copyWith(
          primary: const Color(0xFF7AA2FF),
          onPrimary: const Color(0xFF0B132B),
          primaryContainer: const Color(0xFF112254),
          onPrimaryContainer: const Color(0xFFF8FAFC),
          secondary: _accent,
          onSecondary: const Color(0xFF0D1F4F),
          secondaryContainer: const Color(0xFF1B2A52),
          onSecondaryContainer: const Color(0xFFDCE7FF),
          tertiary: const Color(0xFF818CF8),
          onTertiary: const Color(0xFF141740),
          tertiaryContainer: const Color(0xFF1E2859),
          onTertiaryContainer: const Color(0xFFC7D2FE),
          error: const Color(0xFFF87171),
          onError: const Color(0xFF450A0A),
          surface: const Color(0xFF0B1020),
          surfaceContainerLowest: const Color(0xFF131B30),
          surfaceContainerLow: const Color(0xFF16203A),
          surfaceContainer: const Color(0xFF141E36),
          surfaceContainerHigh: const Color(0xFF111B36),
          surfaceContainerHighest: const Color(0xFF1B2A52),
          onSurface: const Color(0xFFF1F5F9),
          onSurfaceVariant: const Color(0xFF9AA9C7),
          outline: const Color(0xFF475577),
          outlineVariant: const Color(0xFF243052),
        )
      : seeded.copyWith(
          primary: _brand,
          onPrimary: Colors.white,
          primaryContainer: const Color(0xFFE8EFFD),
          onPrimaryContainer: _brand,
          secondary: _accent,
          onSecondary: const Color(0xFF0D1F4F),
          secondaryContainer: const Color(0xFFE8EFFD),
          onSecondaryContainer: _brand,
          // Clinical indigo for Rx tags and regulatory highlights.
          tertiary: const Color(0xFF3730A3),
          onTertiary: Colors.white,
          tertiaryContainer: const Color(0xFFEEF2FF),
          onTertiaryContainer: const Color(0xFF3730A3),
          error: const Color(0xFFDC2626),
          onError: Colors.white,
          surface: const Color(0xFFF5F6F9),
          surfaceContainerLowest: Colors.white,
          surfaceContainerLow: Colors.white,
          surfaceContainer: const Color(0xFFEDF1F8),
          surfaceContainerHigh: const Color(0xFFEBF1FF),
          surfaceContainerHighest: const Color(0xFFE4ECFA),
          onSurface: const Color(0xFF0D1F4F),
          onSurfaceVariant: const Color(0xFF52607A),
          outline: const Color(0xFF8391AB),
          outlineVariant: const Color(0xFFE4E8F2),
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
      dark ? AppBrandColors.dark : AppBrandColors.light,
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
      elevation: dark ? 0 : 1,
      shadowColor: const Color(0x1F0F1D40),
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
      backgroundColor: dark ? const Color(0xFF16213B) : const Color(0xFF0D1F4F),
      contentTextStyle: textTheme.bodyMedium?.copyWith(
        color: Colors.white,
        fontSize: 13.5,
        fontWeight: FontWeight.w600,
      ),
      actionTextColor: const Color(0xFFFFC82C),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
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
      indicatorColor: dark ? scheme.secondaryContainer : scheme.primaryContainer,
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

/// The logo's sun at a whisper: quick-action buttons and the selected tab.
const sunWash = Color(0xFFFFF4D1);
const sunWashEdge = Color(0xFFF3DB8C);

/// The Shop header's sky: flat cloud blue with navy ink, like the logo on a
/// clear morning. Dark mode turns it to deep navy with light ink.
({Color fill, Color ink}) skySlab(ColorScheme scheme) =>
    scheme.brightness == Brightness.dark
    ? (fill: scheme.primaryContainer, ink: scheme.onPrimaryContainer)
    : (fill: scheme.primaryContainer, ink: scheme.primary);

Color statusColour(BuildContext context, String status) {
  final scheme = Theme.of(context).colorScheme;
  final dark = scheme.brightness == Brightness.dark;

  return switch (status) {
    // Waiting: the logo's sun, deepened for text.
    'pending' => dark ? const Color(0xFFFFC94A) : const Color(0xFF8A5A00),
    // Being prepared: the royal blue between the logo navy and sky.
    'confirmed' ||
    'processing' => dark ? const Color(0xFF9DB4FF) : const Color(0xFF2346C8),
    // On the road: teal, distinct from preparing without leaving the cool family.
    'shipped' => dark ? const Color(0xFF5FD4E8) : const Color(0xFF0E7490),
    'delivered' ||
    'completed' => dark ? const Color(0xFF7ADFA0) : const Color(0xFF2F855A),
    'cancelled' || 'rejected' || 'denied' => scheme.error,
    _ => scheme.onSurfaceVariant,
  };
}
