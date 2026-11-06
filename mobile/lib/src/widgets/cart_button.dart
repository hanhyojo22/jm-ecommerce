import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../providers/cart_providers.dart';

/// Cart shortcut drawn on the brand-coloured home header.
class CartButton extends ConsumerWidget {
  const CartButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scheme = Theme.of(context).colorScheme;
    final count = ref.watch(cartCountProvider);
    final reduceMotion = MediaQuery.disableAnimationsOf(context);

    return Semantics(
      liveRegion: true,
      child: IconButton(
        tooltip: 'Cart, $count ${count == 1 ? 'item' : 'items'}',
        onPressed: () => context.push('/cart'),
        style: IconButton.styleFrom(
          minimumSize: const Size(48, 48),
          foregroundColor: scheme.onPrimary,
        ),
        icon: Stack(
          clipBehavior: Clip.none,
          children: [
            const Icon(Icons.shopping_cart_outlined, size: 26),
            if (count > 0)
              PositionedDirectional(
                top: -7,
                end: -11,
                child: ExcludeSemantics(
                  child: MediaQuery.withClampedTextScaling(
                    maxScaleFactor: 1.3,
                    child: AnimatedSwitcher(
                      duration: reduceMotion
                          ? Duration.zero
                          : const Duration(milliseconds: 180),
                      transitionBuilder: (child, animation) =>
                          ScaleTransition(scale: animation, child: child),
                      child: Container(
                        key: ValueKey(count),
                        constraints: const BoxConstraints(minWidth: 20),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 1,
                        ),
                        decoration: BoxDecoration(
                          color: scheme.error,
                          borderRadius: BorderRadius.circular(999),
                          // The ring separates the badge from the icon on the band.
                          border: Border.all(color: scheme.primary, width: 1.5),
                        ),
                        child: Text(
                          count > 99 ? '99+' : '$count',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: scheme.onError,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            height: 1.25,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
