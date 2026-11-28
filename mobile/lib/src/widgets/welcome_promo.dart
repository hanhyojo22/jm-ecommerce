import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme.dart';

/// Android persists the claim before opening, including if the app is killed
/// while the promotion is visible. Signing in or out never resets it.
Future<bool> showWelcomePromo(
  BuildContext context, {
  required bool signedIn,
}) async {
  const channel = MethodChannel('jmmart/welcome_promo');
  if (!(ModalRoute.of(context)?.isCurrent ?? false)) {
    return false;
  }
  try {
    final shouldShow = await channel.invokeMethod<bool>('claimWelcomePromo');
    if (shouldShow != true ||
        !context.mounted ||
        !(ModalRoute.of(context)?.isCurrent ?? false)) {
      return false;
    }
  } on PlatformException catch (error) {
    debugPrint('[Welcome promo] Could not save display state: $error');
    return false;
  } on MissingPluginException {
    return false;
  }
  if (!context.mounted) {
    return false;
  }
  return await showDialog<bool>(
        context: context,
        routeSettings: const RouteSettings(name: 'welcome-promo'),
        builder: (_) => WelcomePromoDialog(signedIn: signedIn),
      ) ??
      false;
}

class WelcomePromoDialog extends StatelessWidget {
  const WelcomePromoDialog({super.key, required this.signedIn});

  final bool signedIn;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final brand = AppBrandColors.of(context);
    final slab = brandSlab(theme.colorScheme);

    return Dialog(
      constraints: const BoxConstraints(maxWidth: 400),
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      backgroundColor: slab.fill,
      surfaceTintColor: Colors.transparent,
      elevation: 24,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appModalRadius),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ColoredBox(
            color: const Color(0xFFFFF8E8),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 8, 10),
              child: Row(
                children: [
                  Expanded(
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Image.asset(
                        'assets/branding/everyday-company-logo.png',
                        width: 164,
                        height: 48,
                        fit: BoxFit.contain,
                        semanticLabel: 'Everyday Pharmacy',
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close promotion',
                    onPressed: () => Navigator.of(context).pop(false),
                    style: IconButton.styleFrom(
                      foregroundColor: brand.onSunGold,
                      backgroundColor: brand.onSunGold.withValues(alpha: .06),
                      minimumSize: const Size(48, 48),
                    ),
                    icon: const Icon(Icons.close_rounded, size: 22),
                  ),
                ],
              ),
            ),
          ),
          Flexible(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AspectRatio(
                    aspectRatio: 1.85,
                    child: Image.asset(
                      'assets/home/family-everyday-care.webp',
                      fit: BoxFit.cover,
                      alignment: const Alignment(0, -.4),
                      excludeFromSemantics: true,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(24, 24, 24, 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'APP WELCOME PROMO',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: brand.sunGold,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.5,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Semantics(
                          header: true,
                          child: Text.rich(
                            TextSpan(
                              children: [
                                const TextSpan(text: 'Everyday care.\n'),
                                TextSpan(
                                  text: 'Free shipping.',
                                  style: TextStyle(color: brand.sunGold),
                                ),
                              ],
                            ),
                            style: theme.textTheme.headlineMedium?.copyWith(
                              color: slab.ink,
                              fontSize: 30,
                              fontWeight: FontWeight.w800,
                              height: 1.15,
                              letterSpacing: -.8,
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'Medicines and everyday essentials for your family, '
                          'delivered to your door.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: slab.ink.withValues(alpha: .85),
                            height: 1.6,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 12, 24, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                FilledButton.icon(
                  onPressed: () => Navigator.of(context).pop(true),
                  style: FilledButton.styleFrom(
                    backgroundColor: brand.sunGold,
                    foregroundColor: brand.onSunGold,
                    minimumSize: const Size.fromHeight(54),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 12,
                    ),
                    textStyle: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  icon: const Icon(Icons.arrow_forward_rounded, size: 20),
                  iconAlignment: IconAlignment.end,
                  label: Text(
                    signedIn ? 'Shop now' : 'Register & shop',
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  style: TextButton.styleFrom(
                    foregroundColor: slab.ink,
                    minimumSize: const Size.fromHeight(48),
                  ),
                  child: const Text('Continue browsing'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
