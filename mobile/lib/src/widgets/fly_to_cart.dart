import 'dart:ui' show lerpDouble;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/cart_providers.dart';
import 'common.dart';

/// App-bar cart icon with a live badge. It watches the cart on its own so a
/// count change repaints this icon instead of the whole page around it, and
/// [CartBadgeIconState.pulse] gives the landing bounce for [flyToCart].
class CartBadgeIcon extends ConsumerStatefulWidget {
  const CartBadgeIcon({super.key});

  @override
  ConsumerState<CartBadgeIcon> createState() => CartBadgeIconState();
}

class CartBadgeIconState extends ConsumerState<CartBadgeIcon>
    with SingleTickerProviderStateMixin {
  late final _bounce = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
  );

  // Squash on impact, overshoot, settle: a short elastic "catch".
  late final _scale = TweenSequence<double>([
    TweenSequenceItem(tween: Tween(begin: 1, end: 0.82), weight: 18),
    TweenSequenceItem(tween: Tween(begin: 0.82, end: 1.22), weight: 32),
    TweenSequenceItem(
      tween: Tween(
        begin: 1.22,
        end: 1.0,
      ).chain(CurveTween(curve: Curves.easeOutBack)),
      weight: 50,
    ),
  ]).animate(_bounce);

  void pulse() {
    if (!mounted || MediaQuery.disableAnimationsOf(context)) {
      return;
    }
    _bounce.forward(from: 0);
  }

  @override
  void dispose() {
    _bounce.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final count = ref.watch(cartCountProvider);

    return Semantics(
      liveRegion: true,
      label: '$count items in cart',
      child: ScaleTransition(
        scale: _scale,
        child: Badge(
          isLabelVisible: count > 0,
          label: ExcludeSemantics(child: Text(count > 99 ? '99+' : '$count')),
          child: const Icon(Icons.shopping_cart_outlined),
        ),
      ),
    );
  }
}

/// Flies a thumbnail of the product from [source] along an arc into [target],
/// then bounces the cart. Skipped (bounce only) when the system asks for
/// reduced motion or either end is off screen.
void flyToCart({
  required BuildContext context,
  required GlobalKey source,
  required GlobalKey<CartBadgeIconState> target,
  String? imageUrl,
}) {
  final overlay = Overlay.maybeOf(context, rootOverlay: true);
  final from = _centerOf(source);
  final to = _centerOf(target);

  if (overlay == null ||
      from == null ||
      to == null ||
      MediaQuery.disableAnimationsOf(context)) {
    target.currentState?.pulse();
    return;
  }

  late final OverlayEntry entry;
  entry = OverlayEntry(
    builder: (_) => _Flight(
      from: from,
      to: to,
      imageUrl: imageUrl,
      onLanded: () {
        entry.remove();
        target.currentState?.pulse();
      },
    ),
  );
  overlay.insert(entry);
}

Offset? _centerOf(GlobalKey key) {
  final box = key.currentContext?.findRenderObject();
  if (box is! RenderBox || !box.attached || !box.hasSize) {
    return null;
  }
  return box.localToGlobal(box.size.center(Offset.zero));
}

class _Flight extends StatefulWidget {
  const _Flight({
    required this.from,
    required this.to,
    required this.imageUrl,
    required this.onLanded,
  });

  final Offset from;
  final Offset to;
  final String? imageUrl;
  final VoidCallback onLanded;

  @override
  State<_Flight> createState() => _FlightState();
}

class _FlightState extends State<_Flight> with SingleTickerProviderStateMixin {
  static const _startSize = 72.0;
  static const _endSize = 18.0;

  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 680),
  )..forward().whenComplete(widget.onLanded);

  // Launch quickly, then ease into the cart.
  late final _travel = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeInOutCubic,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final from = widget.from;
    final to = widget.to;
    // Control point well above both ends gives the toss its arc.
    final control = Offset(
      lerpDouble(from.dx, to.dx, 0.35)!,
      (from.dy < to.dy ? from.dy : to.dy) - 140,
    );
    final scheme = Theme.of(context).colorScheme;

    return IgnorePointer(
      child: AnimatedBuilder(
        // A Stack of its own: Positioned must sit directly inside one.
        animation: _controller,
        builder: (context, child) {
          final t = _travel.value;
          final inverse = 1 - t;
          final point =
              from * (inverse * inverse) +
              control * (2 * inverse * t) +
              to * (t * t);
          // Pop up on launch, then shrink towards the icon.
          final pop = Curves.easeOut.transform(
            (_controller.value / 0.18).clamp(0, 1),
          );
          final size =
              lerpDouble(_startSize * 0.6, _startSize, pop)! * (1 - t) +
              _endSize * t;
          final opacity = t > 0.88 ? (1 - t) / 0.12 : 1.0;

          return Stack(
            children: [
              Positioned(
                left: point.dx - size / 2,
                top: point.dy - size / 2,
                width: size,
                height: size,
                child: Opacity(
                  opacity: opacity.clamp(0, 1),
                  child: Transform.rotate(angle: -0.5 * t, child: child),
                ),
              ),
            ],
          );
        },
        child: DecoratedBox(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: scheme.surfaceContainerLowest,
            border: Border.all(color: scheme.secondary, width: 2),
            boxShadow: const [
              BoxShadow(
                color: Color(0x33000000),
                blurRadius: 12,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: ClipOval(
            child: widget.imageUrl == null
                ? Icon(Icons.shopping_bag_outlined, color: scheme.primary)
                : NetworkThumb(
                    url: widget.imageUrl,
                    radius: 0,
                    cacheWidth: 200,
                  ),
          ),
        ),
      ),
    );
  }
}
