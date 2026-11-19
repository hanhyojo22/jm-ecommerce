import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

const splashBackground = Color(0xFFFAFBFD);
const _splashBlue = Color(0xFF071C94);
const _emblemAsset = AssetImage('assets/branding/everyday-emblem.png');

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
        duration: const Duration(milliseconds: 1530),
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
    precacheImage(_emblemAsset, context).then((_) {
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
                          final cloud = const Interval(
                            .05,
                            .52,
                            curve: Curves.easeOutCubic,
                          ).transform(time);
                          final sun = const Interval(
                            .17,
                            .95,
                            curve: Curves.easeOutCubic,
                          ).transform(time);
                          final wordmark = const Interval(
                            .575,
                            1,
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
                                        opacity: sun,
                                        child: Transform.translate(
                                          offset: reducedMotion
                                              ? Offset.zero
                                              : Offset(
                                                  10 * (1 - sun),
                                                  38 * (1 - sun),
                                                ),
                                          child: Transform.rotate(
                                            angle: reducedMotion
                                                ? 0
                                                : -.244 * (1 - sun),
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
                                          offset: Offset(
                                            reducedMotion ? 0 : 9 * (1 - cloud),
                                            0,
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
                                child: FittedBox(
                                  fit: BoxFit.scaleDown,
                                  child: Text(
                                    'EVERYDAY\nPHARMACY',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontFamily: 'EverydayInter',
                                      fontVariations: const [
                                        FontVariation('wght', 850),
                                      ],
                                      fontSize: (constraints.maxWidth * .085)
                                          .clamp(28.0, 41.0),
                                      fontWeight: FontWeight.w800,
                                      height: 1.1,
                                      letterSpacing: -1.1,
                                      color: _splashBlue,
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
                );
              },
            ),
          ),
        ),
      ),
    );
  }
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
