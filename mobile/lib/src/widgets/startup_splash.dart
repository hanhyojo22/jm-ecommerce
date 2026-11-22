import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

const splashBackground = Color(0xFFFAFBFD);
const _emblemAsset = AssetImage('assets/branding/everyday-emblem.webp');
const _logoAsset = AssetImage('assets/branding/everyday-company-logo.png');

class StartupSplash extends StatefulWidget {
  const StartupSplash({super.key, required this.onComplete});

  final VoidCallback onComplete;

  @override
  State<StartupSplash> createState() => _StartupSplashState();
}

class _StartupSplashState extends State<StartupSplash>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 2100),
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
        systemNavigationBarColor: splashBackground,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: splashBackground,
        body: Semantics(
          label: 'Everyday Pharmacy. Starting app.',
          child: ExcludeSemantics(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final width = math.min(
                  math.min(constraints.maxWidth * .74, 340.0),
                  constraints.maxHeight * .68,
                );
                return Center(
                  child: FractionalTranslation(
                    translation: const Offset(0, -.12),
                    child: SizedBox(
                      width: width,
                      child: AnimatedBuilder(
                        animation: _controller,
                        builder: (context, child) {
                          final time = _controller.value;
                          // A slow rise: about 1.9 s.
                          final cloud = const Interval(
                            .057,
                            .95,
                            curve: Curves.easeOutCubic,
                          ).transform(time);
                          // The sun climbs an arc from behind the cloud on the right to its spot top-left.
                          final sun = const Interval(
                            .08,
                            .8,
                            curve: Curves.easeInOutSine,
                          ).transform(time);
                          final sunArcRise = math.sin(sun * math.pi / 2);
                          final wordmark = const Interval(
                            .131,
                            .67,
                            curve: Curves.easeOutCubic,
                          ).transform(time);
                          final emblemWidth = width * .76;
                          final emblemHeight = emblemWidth / 1.12;
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
                                      top: 0,
                                      left: -emblemWidth * .03,
                                      child: Opacity(
                                        opacity: math.min(1, sun * 3),
                                        child: Transform.translate(
                                          offset: reducedMotion
                                              ? Offset.zero
                                              : Offset(
                                                  emblemWidth * .55 * (1 - sun),
                                                  emblemHeight *
                                                      .5 *
                                                      (1 - sunArcRise),
                                                ),
                                          child: Transform.rotate(
                                            angle: reducedMotion
                                                ? 0
                                                : 1.2 * (1 - sun),
                                            child: _EmblemSprite(
                                              size: emblemWidth * .88,
                                              alignment: Alignment.centerLeft,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                    Positioned(
                                      top: emblemHeight * .16,
                                      left: emblemWidth * .08,
                                      child: Opacity(
                                        opacity: cloud,
                                        child: Transform.translate(
                                          offset: reducedMotion
                                              ? Offset.zero
                                              : Offset(
                                                  0,
                                                  emblemHeight *
                                                      .95 *
                                                      (1 - cloud),
                                                ),
                                          child: _EmblemSprite(
                                            size: emblemWidth * 1.08,
                                            alignment: Alignment.centerRight,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 26),
                              Opacity(
                                opacity: wordmark,
                                child: FractionalTranslation(
                                  translation: const Offset(.1, 0),
                                  child: _LogoWordmark(width: width * .8),
                                ),
                              ),
                            ],
                          );
                        },
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

/// Show only the yellow and blue wordmark beside the animated emblem.
class _LogoWordmark extends StatelessWidget {
  const _LogoWordmark({required this.width});

  final double width;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    height: width * 100 / 216,
    child: ClipRect(
      child: OverflowBox(
        alignment: Alignment.centerRight,
        minWidth: width * 341 / 216,
        maxWidth: width * 341 / 216,
        minHeight: width * 100 / 216,
        maxHeight: width * 100 / 216,
        child: Image(
          image: _logoAsset,
          width: width * 341 / 216,
          height: width * 100 / 216,
          fit: BoxFit.fill,
          filterQuality: FilterQuality.medium,
        ),
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
