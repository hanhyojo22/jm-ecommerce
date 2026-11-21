import 'dart:math' as math;

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
  Widget build(BuildContext context) => Dialog(
    constraints: const BoxConstraints(maxWidth: 420),
    insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(appModalRadius),
    ),
    clipBehavior: Clip.antiAlias,
    child: AspectRatio(
      aspectRatio: 1122 / 1402,
      child: LayoutBuilder(
        builder: (context, constraints) => Stack(
          fit: StackFit.expand,
          children: [
            Image.asset(
              'assets/home/app-register-free-shipping-modal.webp',
              fit: BoxFit.contain,
              semanticLabel:
                  'Everyday Pharmacy app welcome promo. Free shipping. '
                  'Shop medicines and everyday essentials.',
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: math.max(64, constraints.maxHeight * .118),
              child: ColoredBox(
                color: const Color(0xFFFFF8E8),
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: FilledButton.icon(
                    onPressed: () => Navigator.of(context).pop(true),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFFFFD21A),
                      foregroundColor: const Color(0xFF071C94),
                    ),
                    icon: const Icon(Icons.arrow_forward, size: 20),
                    iconAlignment: IconAlignment.end,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(signedIn ? 'Shop now' : 'Register & shop'),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 4,
              right: 4,
              child: IconButton.filled(
                tooltip: 'Close promotion',
                onPressed: () => Navigator.of(context).pop(false),
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xFFFFF8E8),
                  foregroundColor: const Color(0xFF071C94),
                  minimumSize: const Size(48, 48),
                ),
                icon: const Icon(Icons.close),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
