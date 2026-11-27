import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

const splashBackground = Color(0xFFCCE5FA);
const _splashHorizon = Color(0xFFF9D8BF);
const _splashGradient = LinearGradient(
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
  colors: [splashBackground, Color(0xFFFDF7E9), _splashHorizon],
  stops: [0, .62, 1],
);
const _emblemAsset = AssetImage('assets/branding/everyday-emblem.webp');
const _logoAsset = AssetImage('assets/branding/everyday-company-logo.png');

class StartupSplash extends StatefulWidget {
  const StartupSplash({
    super.key,
    required this.onComplete,
    this.isLoading = false,
  });

  final VoidCallback onComplete;
  final bool isLoading;

  @override
  State<StartupSplash> createState() => _StartupSplashState();
}

class _StartupSplashState extends State<StartupSplash>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 1600),
      )..addStatusListener((status) {
        if (status == AnimationStatus.completed) _finish();
      });
  bool _started = false;
  bool _completed = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.value = 1;
    }
    if (_started) return;
    _started = true;
    Future.wait([
      precacheImage(_emblemAsset, context),
      precacheImage(_logoAsset, context),
    ]).then((_) {
      if (mounted && !_completed) _controller.forward();
    });
  }

  void _finish() {
    if (_completed) return;
    _completed = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) widget.onComplete();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reducedMotion = MediaQuery.disableAnimationsOf(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: _splashHorizon,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: splashBackground,
        body: DecoratedBox(
          decoration: const BoxDecoration(gradient: _splashGradient),
          child: Semantics(
            label: 'Everyday Pharmacy. Starting app.',
            child: ExcludeSemantics(
              child: SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final width = math.min(
                      math.min(constraints.maxWidth * .72, 280.0),
                      math.max(0, constraints.maxHeight - 64) * .62,
                    );
                    return Stack(
                      fit: StackFit.expand,
                      children: [
                        Positioned.fill(
                          bottom: 64,
                          child: Align(
                            alignment: Alignment.center,
                            child: SizedBox(
                              width: width,
                              child: RepaintBoundary(
                                child: AnimatedBuilder(
                                  animation: _controller,
                                  builder: (context, child) {
                                    final time = _controller.value;
                                    final cloud = const Interval(
                                      0,
                                      .65,
                                      curve: Curves.easeOutCubic,
                                    ).transform(time);
                                    final sun = const Interval(
                                      .06,
                                      .78,
                                      curve: Curves.easeOutCubic,
                                    ).transform(time);
                                    final wordmark = const Interval(
                                      .28,
                                      .76,
                                      curve: Curves.easeOutCubic,
                                    ).transform(time);
                                    final emblemWidth = width;
                                    final emblemHeight = width * .92;
                                    return Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        SizedBox(
                                          width: emblemWidth,
                                          height: emblemHeight,
                                          child: Stack(
                                            clipBehavior: Clip.none,
                                            children: [
                                              Positioned(
                                                top: -emblemWidth * .015,
                                                left: -emblemWidth * .035,
                                                child: Opacity(
                                                  opacity: .35 + .65 * sun,
                                                  child: Transform.translate(
                                                    offset: reducedMotion
                                                        ? Offset.zero
                                                        : Offset(
                                                            6 * (1 - sun),
                                                            28 * (1 - sun),
                                                          ),
                                                    child: Transform.rotate(
                                                      angle: reducedMotion
                                                          ? 0
                                                          : -.16 * (1 - sun),
                                                      child: _EmblemSprite(
                                                        size: emblemWidth * .8,
                                                        alignment: Alignment
                                                            .centerLeft,
                                                      ),
                                                    ),
                                                  ),
                                                ),
                                              ),
                                              Positioned(
                                                top: emblemWidth * .16,
                                                left: emblemWidth * .045,
                                                child: Transform.translate(
                                                  offset: reducedMotion
                                                      ? Offset.zero
                                                      : Offset(
                                                          0,
                                                          16 * (1 - cloud),
                                                        ),
                                                  child: _EmblemSprite(
                                                    size: emblemWidth * .95,
                                                    alignment:
                                                        Alignment.centerRight,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        SizedBox(height: width * .07),
                                        Opacity(
                                          opacity: wordmark,
                                          child: Transform.translate(
                                            offset: Offset(
                                              0,
                                              reducedMotion
                                                  ? 0
                                                  : 8 * (1 - wordmark),
                                            ),
                                            child: _LogoWordmark(
                                              width: width * .88,
                                            ),
                                          ),
                                        ),
                                      ],
                                    );
                                  },
                                ),
                              ),
                            ),
                          ),
                        ),
                        if (widget.isLoading)
                          Positioned(
                            left: 24,
                            right: 24,
                            bottom: 28,
                            child: AnimatedBuilder(
                              animation: _controller,
                              builder: (context, child) => Opacity(
                                opacity: const Interval(
                                  .85,
                                  1,
                                ).transform(_controller.value),
                                child: child,
                              ),
                              child: const Text(
                                'Starting app…',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontFamily: 'Manrope',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                  color: Color(0xFF48546B),
                                ),
                              ),
                            ),
                          ),
                      ],
                    );
                  },
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Crop to the original lettering, without the miniature emblem and tagline.
class _LogoWordmark extends StatelessWidget {
  const _LogoWordmark({required this.width});

  final double width;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    height: width * 58 / 168,
    child: ClipRect(
      child: Stack(
        children: [
          Positioned(
            left: -width * 126 / 168,
            top: -width * 13 / 168,
            width: width * 341 / 168,
            height: width * 100 / 168,
            child: Image(
              image: _logoAsset,
              fit: BoxFit.fill,
              filterQuality: FilterQuality.high,
            ),
          ),
        ],
      ),
    ),
  );
}

class _EmblemSprite extends StatelessWidget {
  const _EmblemSprite({required this.size, required this.alignment});

  final double size;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: size,
    child: ClipRect(
      child: OverflowBox(
        alignment: alignment,
        minWidth: size * 2,
        maxWidth: size * 2,
        minHeight: size,
        maxHeight: size,
        child: Image(
          image: _emblemAsset,
          width: size * 2,
          height: size,
          fit: BoxFit.fill,
          filterQuality: FilterQuality.medium,
        ),
      ),
    ),
  );
}
