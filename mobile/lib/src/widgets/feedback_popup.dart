import 'dart:async';

import 'package:flutter/material.dart';

import '../theme.dart';

enum FeedbackKind { success, error, info }

/// Captures the root navigator so feedback still works after a screen closes.
class AppFeedback {
  AppFeedback.of(
    BuildContext context, {
    GlobalKey<NavigatorState>? navigatorKey,
  }) : _navigator = Navigator.maybeOf(context, rootNavigator: true),
       _navigatorKey = navigatorKey;

  final NavigatorState? _navigator;
  final GlobalKey<NavigatorState>? _navigatorKey;
  static final _visible =
      <
        NavigatorState,
        ({DialogRoute<void> route, String message, FeedbackKind kind})
      >{};

  void show(String message, {FeedbackKind kind = FeedbackKind.error}) {
    // Navigation and form sheets finish first; feedback then appears above them.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final navigator = _navigatorKey?.currentState ?? _navigator;
      if (navigator == null || !navigator.mounted) return;
      final previous = _visible[navigator];
      if (previous != null && previous.route.isActive) {
        if (previous.message == message && previous.kind == kind) return;
        navigator.removeRoute(previous.route);
      }

      late final DialogRoute<void> route;
      void dismiss() {
        if (!navigator.mounted || !route.isActive) return;
        if (route.isCurrent) {
          navigator.pop();
        } else {
          navigator.removeRoute(route);
        }
      }

      route = DialogRoute<void>(
        context: navigator.context,
        settings: const RouteSettings(name: 'feedback'),
        barrierColor: Colors.black.withValues(alpha: 0.4),
        animationStyle: MediaQuery.disableAnimationsOf(navigator.context)
            ? AnimationStyle.noAnimation
            : null,
        builder: (_) =>
            FeedbackPopup(kind: kind, message: message, onDismiss: dismiss),
      );
      _visible[navigator] = (route: route, message: message, kind: kind);
      unawaited(
        navigator.push(route).whenComplete(() {
          if (_visible[navigator]?.route == route) {
            _visible.remove(navigator);
          }
        }),
      );
    });
    WidgetsBinding.instance.ensureVisualUpdate();
  }
}

class FeedbackPopup extends StatefulWidget {
  const FeedbackPopup({
    super.key,
    required this.kind,
    required this.message,
    required this.onDismiss,
  });

  final FeedbackKind kind;
  final String message;
  final VoidCallback onDismiss;

  @override
  State<FeedbackPopup> createState() => _FeedbackPopupState();
}

class _FeedbackPopupState extends State<FeedbackPopup> {
  Timer? _dismissTimer;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      if (widget.kind == FeedbackKind.success &&
          !MediaQuery.accessibleNavigationOf(context)) {
        _dismissTimer = Timer(const Duration(seconds: 2), widget.onDismiss);
      }
    }
  }

  @override
  void dispose() {
    _dismissTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final (title, icon, color, tint) = switch (widget.kind) {
      FeedbackKind.success => (
        'Success',
        Icons.check_rounded,
        dark ? const Color(0xFF86EFAC) : const Color(0xFF16733D),
        dark ? const Color(0xFF133D2B) : const Color(0xFFE7F6EC),
      ),
      FeedbackKind.error => (
        'Something went wrong',
        Icons.close_rounded,
        scheme.error,
        scheme.errorContainer,
      ),
      FeedbackKind.info => (
        'Please note',
        Icons.info_outline_rounded,
        scheme.primary,
        scheme.primaryContainer,
      ),
    };

    return Dialog(
      backgroundColor: scheme.surfaceContainerLowest,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appModalRadius),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: ExcludeSemantics(
                      child: Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          color: tint,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: color.withValues(alpha: 0.18),
                          ),
                        ),
                        child: Icon(icon, color: color, size: 44),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: scheme.onSurface,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Semantics(
                    liveRegion: true,
                    child: Text(
                      widget.message,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                        height: 1.5,
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: widget.onDismiss,
                    child: Text(
                      widget.kind == FeedbackKind.success
                          ? 'Continue'
                          : 'Got it',
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              top: 4,
              right: 4,
              child: IconButton(
                tooltip: 'Close message',
                onPressed: widget.onDismiss,
                icon: const Icon(Icons.close_rounded, size: 20),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
