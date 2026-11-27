import 'dart:async';

import 'package:flutter/material.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';

enum NoticeKind { success, error, info }

/// Minimal Ink surface decoration shared between floating pills and toasts.
BoxDecoration minimalInkDecoration(
  Brightness brightness, {
  double radius = 22,
}) {
  final dark = brightness == Brightness.dark;
  return BoxDecoration(
    color: dark ? const Color(0xFF16213B) : const Color(0xFF0D1F4F),
    borderRadius: BorderRadius.circular(radius),
    border: dark ? Border.all(color: const Color(0x3894A3B8)) : null,
    boxShadow: [
      BoxShadow(
        color: dark ? const Color(0x6B000000) : const Color(0x290D1F4F),
        blurRadius: dark ? 28 : 24,
        offset: Offset(0, dark ? 10 : 8),
      ),
    ],
  );
}

/// Uses the scaffold's floating message area so navigation and checkout stay usable.
class AppNotice {
  AppNotice.of(BuildContext context, {this.bottomInset = 0})
    : _messenger = ScaffoldMessenger.of(context),
      _reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;

  final ScaffoldMessengerState _messenger;
  final bool _reduceMotion;
  final double bottomInset;

  void show(
    String message, {
    NoticeKind kind = NoticeKind.error,
    FutureOr<void> Function()? onAction,
    String actionLabel = 'Retry',
  }) {
    // Complete any sheet closure or navigation before presenting the result.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_messenger.mounted) {
        return;
      }
      _messenger.removeCurrentSnackBar();
      _messenger.showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: Colors.transparent,
          elevation: 0,
          padding: EdgeInsets.zero,
          margin: EdgeInsets.fromLTRB(16, 12, 16, 12 + bottomInset),
          clipBehavior: Clip.none,
          dismissDirection: DismissDirection.horizontal,
          duration: const Duration(seconds: 3),
          persist: false,
          content: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: StatusNoticeCard(
                message: message,
                kind: kind,
                bottomInset: bottomInset,
                onDismiss: _messenger.hideCurrentSnackBar,
                actionLabel: actionLabel,
                onAction: onAction == null
                    ? null
                    : () async {
                        _messenger.removeCurrentSnackBar();
                        try {
                          await onAction();
                        } on ApiException catch (error) {
                          show(
                            error.friendly,
                            onAction: onAction,
                            actionLabel: actionLabel,
                          );
                        } catch (_) {
                          show(
                            'Could not complete this action. Try again.',
                            onAction: onAction,
                            actionLabel: actionLabel,
                          );
                        }
                      },
              ),
            ),
          ),
        ),
        snackBarAnimationStyle: _reduceMotion
            ? AnimationStyle.noAnimation
            : const AnimationStyle(
                duration: Duration(milliseconds: 180),
                reverseDuration: Duration(milliseconds: 120),
              ),
      );
    });
    WidgetsBinding.instance.ensureVisualUpdate();
  }
}

class StatusNoticeCard extends StatelessWidget {
  const StatusNoticeCard({
    super.key,
    required this.message,
    required this.kind,
    required this.onDismiss,
    this.bottomInset = 0,
    this.onAction,
    this.actionLabel = 'Retry',
  });

  final String message;
  final NoticeKind kind;
  final VoidCallback onDismiss;
  final double bottomInset;
  final VoidCallback? onAction;
  final String actionLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final media = MediaQuery.of(context);
    final textScale = media.textScaler.scale(14);
    final isUndo = onAction != null && actionLabel.toLowerCase() == 'undo';
    final (label, icon, iconColor, actionColor) = switch (kind) {
      NoticeKind.success => (
        'Success',
        PhosphorIconsRegular.checkCircle,
        const Color(0xFF5EEAD4),
        const Color(0xFFFFC82C),
      ),
      NoticeKind.info => (
        'Information',
        isUndo
            ? PhosphorIconsRegular.arrowCounterClockwise
            : PhosphorIconsRegular.info,
        const Color(0xFFFFC82C),
        const Color(0xFFFFC82C),
      ),
      NoticeKind.error => (
        'Error',
        PhosphorIconsRegular.warningCircle,
        const Color(0xFFF87171),
        const Color(0xFFFCA5A5),
      ),
    };

    final showDismiss = kind != NoticeKind.success || onAction != null;
    final hasActions = onAction != null || showDismiss;
    final isWrapped = message.length > 42 || textScale > 17;
    final isCompactPill = onAction == null && !isWrapped;
    final stackAction = onAction != null && textScale > 20;
    final textHeight =
        ((media.size.height -
                    media.viewInsets.bottom -
                    media.padding.vertical -
                    bottomInset) *
                0.25)
            .clamp(48.0, 160.0);

    final EdgeInsetsGeometry padding;
    if (!hasActions && !isWrapped) {
      padding = const EdgeInsets.symmetric(horizontal: 18, vertical: 11);
    } else if (isWrapped) {
      padding = const EdgeInsets.fromLTRB(14, 10, 6, 10);
    } else {
      padding = const EdgeInsets.fromLTRB(15, 2, 6, 2);
    }

    Widget? actionButton;
    if (onAction != null) {
      actionButton = TextButton(
        style: TextButton.styleFrom(
          foregroundColor: actionColor,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          minimumSize: const Size(48, 44),
          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          textStyle: theme.textTheme.labelLarge?.copyWith(
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
        onPressed: onAction,
        child: Text(actionLabel),
      );
    }

    return DecoratedBox(
      decoration: minimalInkDecoration(
        theme.brightness,
        radius: isCompactPill ? 22 : 14,
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: BorderRadius.circular(isCompactPill ? 22 : 14),
        clipBehavior: Clip.antiAlias,
        child: Padding(
          padding: padding,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: isWrapped
                ? CrossAxisAlignment.start
                : CrossAxisAlignment.center,
            children: [
              Padding(
                padding: EdgeInsets.only(top: isWrapped ? 2 : 0),
                child: ExcludeSemantics(
                  child: Icon(icon, size: 18, color: iconColor),
                ),
              ),
              const SizedBox(width: 10),
              Flexible(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ConstrainedBox(
                      constraints: BoxConstraints(maxHeight: textHeight),
                      child: SingleChildScrollView(
                        child: Text(
                          message,
                          semanticsLabel: '$label. $message',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: Colors.white,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w600,
                            letterSpacing: -0.05,
                            height: 1.38,
                          ),
                        ),
                      ),
                    ),
                    if (stackAction && actionButton != null) actionButton,
                  ],
                ),
              ),
              if (!stackAction && actionButton != null) ...[
                const SizedBox(width: 6),
                actionButton,
              ],
              if (showDismiss)
                IconButton(
                  tooltip: 'Dismiss message',
                  onPressed: onDismiss,
                  constraints: const BoxConstraints(
                    minWidth: 40,
                    minHeight: 44,
                  ),
                  padding: EdgeInsets.zero,
                  icon: Icon(
                    PhosphorIconsRegular.x,
                    size: 15,
                    color: Colors.white.withValues(alpha: 0.56),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Quiet branded refresh failure strip that replaces stock [MaterialBanner].
class RefreshNoticeStrip extends StatelessWidget {
  const RefreshNoticeStrip({
    super.key,
    required this.message,
    required this.actionLabel,
    required this.onAction,
  });

  final String message;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: dark ? const Color(0xFF15203B) : const Color(0xFFEFF3FA),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: dark ? const Color(0xFF243256) : const Color(0xFFDCE4F3),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 4, 6, 4),
          child: Row(
            children: [
              ExcludeSemantics(
                child: Icon(
                  PhosphorIconsRegular.warningCircle,
                  size: 16,
                  color: scheme.primary,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  message,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurface,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              TextButton(
                style: TextButton.styleFrom(
                  foregroundColor:
                      dark ? const Color(0xFFFFC82C) : scheme.primary,
                  minimumSize: const Size(48, 44),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  textStyle: theme.textTheme.labelLarge?.copyWith(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                onPressed: onAction,
                child: Text(actionLabel),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
