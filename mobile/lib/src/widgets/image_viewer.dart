import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

/// Full-screen photo viewer: pinch or double-tap to zoom, drag to pan while
/// zoomed, back or the close button to leave. Built on Flutter's own
/// [InteractiveViewer], so it needs no extra package.
class ImageViewer extends StatefulWidget {
  const ImageViewer({
    super.key,
    required this.url,
    this.cacheKey,
    this.title,
    this.onSave,
  });

  final String url;
  final String? cacheKey;
  final String? title;

  /// Shows a save action when given; the caller owns the saving and its feedback.
  final Future<void> Function()? onSave;

  static const maxScale = 5.0;
  static const doubleTapScale = 2.5;

  /// Opens the viewer with a quick fade, the way a photo "lifts" off the chat.
  static Future<void> open(
    BuildContext context, {
    required String url,
    String? cacheKey,
    String? title,
    Future<void> Function()? onSave,
  }) {
    return Navigator.of(context).push(
      PageRouteBuilder<void>(
        opaque: false,
        barrierColor: Colors.black,
        transitionDuration: const Duration(milliseconds: 180),
        reverseTransitionDuration: const Duration(milliseconds: 150),
        pageBuilder: (context, animation, secondaryAnimation) => ImageViewer(
          url: url,
          cacheKey: cacheKey,
          title: title,
          onSave: onSave,
        ),
        transitionsBuilder: (context, animation, secondaryAnimation, child) =>
            FadeTransition(opacity: animation, child: child),
      ),
    );
  }

  @override
  State<ImageViewer> createState() => _ImageViewerState();
}

class _ImageViewerState extends State<ImageViewer>
    with SingleTickerProviderStateMixin {
  final _transform = TransformationController();
  late final AnimationController _zoom = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 220),
  );
  Animation<Matrix4>? _zoomAnimation;
  TapDownDetails? _doubleTap;

  @override
  void initState() {
    super.initState();
    _zoom.addListener(() {
      if (_zoomAnimation != null) {
        _transform.value = _zoomAnimation!.value;
      }
    });
  }

  @override
  void dispose() {
    _zoom.dispose();
    _transform.dispose();
    super.dispose();
  }

  bool get _zoomedIn => _transform.value.getMaxScaleOnAxis() > 1.01;

  /// Double-tap zooms into the tapped point, or back out when already zoomed.
  void _toggleZoom() {
    final Matrix4 target;
    if (_zoomedIn) {
      target = Matrix4.identity();
    } else {
      final point = _doubleTap?.localPosition ?? Offset.zero;
      const scale = ImageViewer.doubleTapScale;
      target = Matrix4.identity()
        ..translateByDouble(
          -point.dx * (scale - 1),
          -point.dy * (scale - 1),
          0,
          1,
        )
        ..scaleByDouble(scale, scale, 1, 1);
    }
    _zoomAnimation = Matrix4Tween(
      begin: _transform.value,
      end: target,
    ).animate(CurvedAnimation(parent: _zoom, curve: Curves.easeOutCubic));
    if (MediaQuery.disableAnimationsOf(context)) {
      _transform.value = target;
    } else {
      _zoom.forward(from: 0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final onSave = widget.onSave;

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          Positioned.fill(
            child: GestureDetector(
              key: const ValueKey('image-viewer-gestures'),
              onDoubleTapDown: (details) => _doubleTap = details,
              onDoubleTap: _toggleZoom,
              child: InteractiveViewer(
                key: const ValueKey('image-viewer-zoom'),
                transformationController: _transform,
                minScale: 1,
                maxScale: ImageViewer.maxScale,
                child: Center(
                  child: Semantics(
                    image: true,
                    label: widget.title ?? 'Photo',
                    child: CachedNetworkImage(
                      imageUrl: widget.url,
                      cacheKey: widget.cacheKey,
                      fit: BoxFit.contain,
                      fadeInDuration: const Duration(milliseconds: 120),
                      placeholder: (context, url) => const SizedBox.square(
                        dimension: 32,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white70,
                        ),
                      ),
                      errorWidget: (context, url, error) => const Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.broken_image_outlined,
                            color: Colors.white70,
                            size: 40,
                          ),
                          SizedBox(height: 8),
                          Text(
                            'Could not load this photo.',
                            style: TextStyle(color: Colors.white70),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          // Controls sit on a soft scrim so they stay readable over bright photos.
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: DecoratedBox(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0x99000000), Color(0x00000000)],
                ),
              ),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(4, 4, 4, 16),
                  child: Row(
                    children: [
                      IconButton(
                        onPressed: () => Navigator.of(context).maybePop(),
                        tooltip: 'Close',
                        color: Colors.white,
                        icon: const Icon(Icons.close_rounded),
                      ),
                      const Spacer(),
                      if (onSave != null)
                        IconButton(
                          onPressed: onSave,
                          tooltip: 'Save image',
                          color: Colors.white,
                          icon: const Icon(Icons.download_rounded),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
