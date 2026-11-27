import 'dart:async';
import 'dart:ui' show lerpDouble;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../providers/cart_providers.dart';
import 'common.dart';
import 'status_notice.dart';

/// App-bar cart icon with a live badge. It watches the cart on its own so a
/// count change repaints this icon instead of the whole page around it, and
/// [CartBadgeIconState.pulse] gives the landing bounce for [flyToCart].
class CartBadgeIcon extends ConsumerStatefulWidget {
  const CartBadgeIcon({super.key, this.child});

  /// Custom cart icon (such as the shell's Cart tab); defaults to the app-bar cart icon.
  final Widget? child;

  @override
  ConsumerState<CartBadgeIcon> createState() => CartBadgeIconState();
}

/// Exposes a cart icon target so product cards inside the shell can fly items into it.
class CartFlightTarget extends InheritedWidget {
  const CartFlightTarget({
    super.key,
    required this.targetKey,
    this.onOpenCart,
    required super.child,
  });

  final GlobalKey<CartBadgeIconState> targetKey;
  final VoidCallback? onOpenCart;

  static GlobalKey<CartBadgeIconState>? maybeOf(BuildContext context) =>
      context.getInheritedWidgetOfExactType<CartFlightTarget>()?.targetKey;

  static VoidCallback? maybeOpenCartOf(BuildContext context) =>
      context.getInheritedWidgetOfExactType<CartFlightTarget>()?.onOpenCart;

  @override
  bool updateShouldNotify(CartFlightTarget oldWidget) =>
      targetKey != oldWidget.targetKey || onOpenCart != oldWidget.onOpenCart;
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
    if (widget.child != null) {
      return ScaleTransition(scale: _scale, child: widget.child);
    }
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
/// reduced motion or either end is off screen. Completes once it has landed.
Future<void> flyToCart({
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
    return Future.value();
  }

  final landed = Completer<void>();
  late final OverlayEntry entry;
  entry = OverlayEntry(
    builder: (_) => _Flight(
      from: from,
      to: to,
      imageUrl: imageUrl,
      onLanded: () {
        entry.remove();
        target.currentState?.pulse();
        landed.complete();
      },
    ),
  );
  overlay.insert(entry);
  return landed.future;
}

/// Non-blocking bottom-floating Minimal Ink pill confirming the cart add.
void showAddedToCartToast(
  BuildContext context, {
  VoidCallback? onViewCart,
  double bottomOffset = 80,
}) {
  final overlay = Overlay.maybeOf(context, rootOverlay: true);
  if (overlay == null) {
    return;
  }
  var removed = false;
  late final OverlayEntry entry;
  void removeEntry() {
    if (removed) {
      return;
    }
    removed = true;
    entry.remove();
  }

  entry = OverlayEntry(
    builder: (_) => _AddedToast(
      onDone: removeEntry,
      onViewCart: onViewCart,
      bottomOffset: bottomOffset,
    ),
  );
  overlay.insert(entry);
}

class _AddedToast extends StatefulWidget {
  const _AddedToast({
    required this.onDone,
    required this.bottomOffset,
    this.onViewCart,
  });

  final VoidCallback onDone;
  final VoidCallback? onViewCart;
  final double bottomOffset;

  @override
  State<_AddedToast> createState() => _AddedToastState();
}

class _AddedToastState extends State<_AddedToast>
    with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
  );

  @override
  void initState() {
    super.initState();
    _controller.addStatusListener((status) {
      if (status == AnimationStatus.completed) {
        widget.onDone();
      }
    });
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final media = MediaQuery.of(context);
    final reduceMotion = media.disableAnimations;
    final bottomPadding = media.padding.bottom;

    return Align(
      alignment: Alignment.bottomCenter,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          12,
          16,
          bottomPadding + widget.bottomOffset,
        ),
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, child) {
            final t = _controller.value;
            final opacity = t < 0.12
                ? t / 0.12
                : t > 0.8
                ? (1 - t) / 0.2
                : 1.0;
            final dy = reduceMotion
                ? 0.0
                : lerpDouble(
                    10,
                    0,
                    Curves.easeOutCubic.transform((t / 0.15).clamp(0, 1)),
                  )!;
            return Opacity(
              opacity: opacity.clamp(0, 1),
              child: Transform.translate(offset: Offset(0, dy), child: child),
            );
          },
          child: Semantics(
            liveRegion: true,
            child: DecoratedBox(
              decoration: minimalInkDecoration(theme.brightness, radius: 22),
              child: Material(
                type: MaterialType.transparency,
                borderRadius: BorderRadius.circular(22),
                clipBehavior: Clip.antiAlias,
                child: Padding(
                  padding: widget.onViewCart == null
                      ? const EdgeInsets.symmetric(horizontal: 18, vertical: 11)
                      : const EdgeInsets.fromLTRB(16, 0, 10, 0),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const ExcludeSemantics(
                        child: Icon(
                          PhosphorIconsRegular.checkCircle,
                          color: Color(0xFF5EEAD4),
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 9),
                      Flexible(
                        child: Text(
                          'Added to cart',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: Colors.white,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w600,
                            letterSpacing: -0.05,
                          ),
                        ),
                      ),
                      if (widget.onViewCart != null) ...[
                        const SizedBox(width: 6),
                        TextButton(
                          style: TextButton.styleFrom(
                            foregroundColor: const Color(0xFFFFC82C),
                            minimumSize: const Size(48, 44),
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                            textStyle: theme.textTheme.labelLarge?.copyWith(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          onPressed: () {
                            widget.onDone();
                            widget.onViewCart!();
                          },
                          child: const Text('View cart'),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
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
    duration: const Duration(milliseconds: 1000),
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
