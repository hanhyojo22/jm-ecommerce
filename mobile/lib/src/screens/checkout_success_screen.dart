import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../theme.dart';

const _celebrationDuration = Duration(milliseconds: 1800);

/// The created order and the checkout choices needed by its acknowledgement.
class CheckoutSuccessDetails {
  const CheckoutSuccessDetails({
    required this.placed,
    required this.branchName,
    required this.paymentMethod,
    required this.fulfillmentMethod,
    required this.needsPrescription,
  });

  final PlacedOrder placed;
  final String? branchName;
  final String paymentMethod;
  final String fulfillmentMethod;
  final bool needsPrescription;
}

class CheckoutSuccessScreen extends StatefulWidget {
  const CheckoutSuccessScreen({super.key, required this.details});

  final CheckoutSuccessDetails details;

  @override
  State<CheckoutSuccessScreen> createState() => _CheckoutSuccessScreenState();
}

class _CheckoutSuccessScreenState extends State<CheckoutSuccessScreen>
    with SingleTickerProviderStateMixin {
  late final _animation = AnimationController(
    vsync: this,
    duration: _celebrationDuration,
    animationBehavior: AnimationBehavior.preserve,
  );
  bool _started = false;
  bool _openingPayment = false;
  String? _paymentFailure;

  CheckoutSuccessDetails get _details => widget.details;
  OrderDetail get _order => _details.placed.order;
  bool get _paymentPending =>
      _details.paymentMethod != 'cod' && _order.payment?.status != 'paid';

  Uri? get _paymentUri {
    if (!_paymentPending ||
        _paymentFailure != null ||
        _details.placed.paymentError != null) {
      return null;
    }
    final uri = Uri.tryParse(_details.placed.paymentUrl ?? '');
    return uri != null &&
            ['https', 'http'].contains(uri.scheme) &&
            uri.host.isNotEmpty
        ? uri
        : null;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      if (MediaQuery.disableAnimationsOf(context)) {
        _animation.duration = const Duration(milliseconds: 150);
      }
      _animation.forward();
    } else if (MediaQuery.disableAnimationsOf(context) &&
        _animation.isAnimating) {
      _animation.value = 1;
    }
  }

  @override
  void dispose() {
    _animation.dispose();
    super.dispose();
  }

  void _viewOrder() => context.pushReplacement('/orders/${_order.id}');

  Future<void> _continueToPayment() async {
    final uri = _paymentUri;
    if (uri == null || _openingPayment) {
      return;
    }
    setState(() => _openingPayment = true);
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!mounted) {
        return;
      }
      if (opened) {
        _viewOrder();
      } else {
        setState(
          () => _paymentFailure =
              'Could not open payment. Open your order to continue payment.',
        );
      }
    } on Exception {
      if (mounted) {
        setState(
          () => _paymentFailure =
              'Could not open payment. Open your order to continue payment.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _openingPayment = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final short = MediaQuery.sizeOf(context).height < 500;
    final textSize = MediaQuery.textScalerOf(context).scale(14);
    final branch = _details.branchName;
    final paymentLabel = _order.payment?.label.isNotEmpty == true
        ? _order.payment!.label
        : switch (_details.paymentMethod) {
            'cod' =>
              _details.fulfillmentMethod == 'pickup'
                  ? 'Pay at the counter'
                  : 'Cash on delivery',
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'card' => 'Card',
            _ => 'Online payment',
          };
    final paymentError = _paymentFailure ?? _details.placed.paymentError;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _viewOrder();
        }
      },
      child: Scaffold(
        backgroundColor: scheme.surfaceContainerLowest,
        appBar: AppBar(
          backgroundColor: scheme.surfaceContainerLowest,
          centerTitle: true,
          title: Text(
            'Checkout',
            style: theme.textTheme.titleMedium?.copyWith(
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          leading: IconButton(
            tooltip: 'Continue shopping',
            onPressed: () => context.go('/'),
            icon: const Icon(Icons.close_rounded),
          ),
        ),
        bottomNavigationBar: Align(
          heightFactor: 1,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 640),
            child: DecoratedBox(
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: scheme.outlineVariant)),
              ),
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(22, 14, 22, 16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      FilledButton(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size.fromHeight(54),
                          shape: const StadiumBorder(),
                        ),
                        onPressed: _openingPayment
                            ? null
                            : _paymentUri != null
                            ? _continueToPayment
                            : _viewOrder,
                        child: _openingPayment
                            ? SizedBox.square(
                                dimension: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: scheme.primary,
                                  semanticsLabel: 'Opening payment',
                                ),
                              )
                            : Text(
                                _paymentUri != null
                                    ? 'Continue to payment'
                                    : 'View order',
                                textAlign: TextAlign.center,
                              ),
                      ),
                      const SizedBox(height: 4),
                      TextButton(
                        style: TextButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                        ),
                        onPressed: () => context.go('/'),
                        child: const Text(
                          'Continue shopping',
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 640),
            child: LayoutBuilder(
              builder: (context, constraints) => SingleChildScrollView(
                child: ConstrainedBox(
                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(22, 16, 22, 24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ExcludeSemantics(
                          child: RepaintBoundary(
                            child: SizedBox(
                              height: short
                                  ? 96
                                  : textSize > 21
                                  ? 130
                                  : textSize > 18
                                  ? 160
                                  : 222,
                              child: Center(
                                child: AspectRatio(
                                  aspectRatio: 255 / 222,
                                  child: _CheckoutIllustration(
                                    animation: _animation,
                                    reduceMotion: reduceMotion,
                                    scheme: scheme,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Semantics(
                          header: true,
                          liveRegion: true,
                          child: Text(
                            'Order placed',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.headlineMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(height: 10),
                        Text(
                          _paymentPending
                              ? 'Your order is saved. Complete your payment to continue.'
                              : 'Your order is with ${branch ?? 'the pharmacy'}. You can follow its progress in your orders.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Order #${_order.shortNumber.isNotEmpty ? _order.shortNumber : _order.number}',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 14),
                        if (_details.needsPrescription &&
                            _order.prescriptionStatus != 'approved')
                          _notice(
                            'Your prescription is waiting for the pharmacy’s review.',
                          ),
                        if (paymentError != null) ...[
                          const SizedBox(height: 12),
                          _notice(paymentError, error: true),
                        ] else if (_paymentPending && _paymentUri == null) ...[
                          const SizedBox(height: 12),
                          _notice('Open your order to complete payment.'),
                        ],
                        const SizedBox(height: 24),
                        Divider(height: 1, color: scheme.outlineVariant),
                        const SizedBox(height: 16),
                        _summaryRow(
                          'Payment',
                          '$paymentLabel${_paymentPending ? ' · pending' : ''}',
                        ),
                        _summaryRow(
                          'Fulfilment',
                          '${_details.fulfillmentMethod == 'pickup' ? 'Pickup' : 'Delivery'}${branch == null ? '' : ' · $branch'}',
                        ),
                        _summaryRow(
                          'Order total',
                          money(_order.total),
                          total: true,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _notice(String message, {bool error = false}) {
    final theme = Theme.of(context);
    return Semantics(
      liveRegion: error,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: error
              ? theme.colorScheme.errorContainer
              : theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(appSurfaceRadius),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              error ? Icons.error_outline_rounded : Icons.schedule_rounded,
              size: 20,
              color: error
                  ? theme.colorScheme.onErrorContainer
                  : theme.colorScheme.primary,
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                message,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: error
                      ? theme.colorScheme.onErrorContainer
                      : theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value, {bool total = false}) {
    final theme = Theme.of(context);
    final textSize = MediaQuery.textScalerOf(context).scale(14);
    final large =
        textSize > 21 ||
        (MediaQuery.sizeOf(context).width < 360 && textSize > 18);
    final labelWidget = Text(
      label,
      style: theme.textTheme.bodyMedium?.copyWith(
        color: total
            ? theme.colorScheme.onSurface
            : theme.colorScheme.onSurfaceVariant,
        fontWeight: total ? FontWeight.w700 : FontWeight.w400,
      ),
    );
    final valueWidget = Text(
      value,
      textAlign: large ? TextAlign.start : TextAlign.end,
      style: (total ? theme.textTheme.titleMedium : theme.textTheme.bodyMedium)
          ?.copyWith(fontWeight: total ? FontWeight.w800 : FontWeight.w600),
    );
    return Padding(
      padding: EdgeInsets.only(bottom: 12, top: total ? 4 : 0),
      child: large
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [labelWidget, const SizedBox(height: 4), valueWidget],
            )
          : Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 2, child: labelWidget),
                const SizedBox(width: 16),
                Expanded(flex: 3, child: valueWidget),
              ],
            ),
    );
  }
}

double _phase(double elapsed, double start, double end) =>
    ((elapsed - start) / (end - start)).clamp(0.0, 1.0);

/// The approved local image, with a check and confetti painted above it.
class _CheckoutIllustration extends StatelessWidget {
  const _CheckoutIllustration({
    required this.animation,
    required this.reduceMotion,
    required this.scheme,
  });

  final Animation<double> animation;
  final bool reduceMotion;
  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final scale = constraints.maxWidth / 255;
      return IgnorePointer(
        child: ClipRect(
          child: AnimatedBuilder(
            animation: animation,
            child: Image.asset(
              'assets/branding/checkout-success-bag.png',
              fit: BoxFit.contain,
              cacheWidth: 768,
              excludeFromSemantics: true,
            ),
            builder: (context, image) {
              final elapsed =
                  animation.value * _celebrationDuration.inMilliseconds;
              final arrival = reduceMotion
                  ? 1.0
                  : Curves.easeOutQuart.transform(_phase(elapsed, 0, 280));
              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned(
                    left: 0,
                    top: -19 * scale,
                    width: 256 * scale,
                    height: 256 * scale,
                    child: Opacity(
                      opacity: reduceMotion
                          ? .7 + .3 * animation.value
                          : .6 + .4 * _phase(elapsed, 0, 180),
                      child: Transform.translate(
                        offset: Offset(0, 10 * (1 - arrival) * scale),
                        child: Transform.rotate(
                          angle: -2 * math.pi / 180 * (1 - arrival),
                          alignment: const Alignment(0, .7),
                          child: Transform.scale(
                            scale: .95 + .05 * arrival,
                            child: image,
                          ),
                        ),
                      ),
                    ),
                  ),
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _SuccessOverlayPainter(
                        elapsed: reduceMotion
                            ? _celebrationDuration.inMilliseconds.toDouble()
                            : elapsed,
                        confetti: !reduceMotion,
                        scheme: scheme,
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      );
    },
  );
}

class _SuccessOverlayPainter extends CustomPainter {
  const _SuccessOverlayPainter({
    required this.elapsed,
    required this.confetti,
    required this.scheme,
  });

  final double elapsed;
  final bool confetti;
  final ColorScheme scheme;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / 255, size.height / 222);
    const center = Offset(213, 178);
    final badge = Curves.easeOutQuart.transform(_phase(elapsed, 140, 380));
    final opacity = _phase(elapsed, 120, 300);
    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.scale(.78 + .22 * badge);
    canvas.translate(-center.dx, -center.dy);
    canvas.drawCircle(
      center,
      29,
      Paint()..color = scheme.surfaceContainerLowest.withValues(alpha: opacity),
    );
    canvas.drawCircle(
      center,
      25,
      Paint()..color = scheme.primary.withValues(alpha: opacity),
    );
    final check = Path()
      ..moveTo(204, 178)
      ..lineTo(210, 184)
      ..lineTo(223, 171);
    final metric = check.computeMetrics().first;
    final checkProgress = Curves.easeOutQuart.transform(
      _phase(elapsed, 220, 620),
    );
    canvas.drawPath(
      metric.extractPath(0, metric.length * checkProgress),
      Paint()
        ..color = scheme.onPrimary.withValues(alpha: opacity)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
    canvas.restore();
    if (confetti && elapsed > 520 && elapsed < 1800) {
      _paintConfetti(canvas);
    }
    canvas.restore();
  }

  void _paintConfetti(Canvas canvas) {
    final fade = 1 - _phase(elapsed, 1480, 1800);
    final colours = [
      scheme.primary,
      scheme.secondary,
      Color.lerp(scheme.primaryContainer, const Color(0xFF89C6EE), .85)!,
    ];
    for (var index = 0; index < 36; index++) {
      final seconds = math.max(0.0, (elapsed - 520 - index % 6 * 16) / 1000);
      if (seconds <= 0) {
        continue;
      }
      final angle = (202 + (index * 47) % 137) * math.pi / 180;
      final speed = 180 + (index * 29) % 135;
      final x = 128 + math.cos(angle) * speed * seconds;
      final y =
          120 + math.sin(angle) * speed * seconds + 280 * seconds * seconds;
      final opacity = (seconds * 24).clamp(0.0, 1.0) * fade;
      final spin =
          (index.isOdd ? 1 : -1) * (220 + index * 19) * seconds * math.pi / 180;
      final flip =
          .35 + .65 * math.cos(seconds * 7 + 520 + index % 6 * 16).abs();
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(spin);
      canvas.scale(flip, 1);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(
            0,
            0,
            (5 + index % 3).toDouble(),
            index % 4 == 0 ? 6 : 10,
          ),
          const Radius.circular(1),
        ),
        Paint()
          ..color = colours[index % colours.length].withValues(alpha: opacity),
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_SuccessOverlayPainter oldDelegate) =>
      oldDelegate.elapsed != elapsed ||
      oldDelegate.confetti != confetti ||
      oldDelegate.scheme != scheme;
}
