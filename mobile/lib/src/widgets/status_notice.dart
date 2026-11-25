import 'dart:async';

import 'package:flutter/material.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';

enum NoticeKind { success, error, info }

/// Uses the scaffold's floating message area so navigation and checkout stay usable.
class AppNotice {
  AppNotice.of(BuildContext context, {this.bottomInset = 0})
    : _messenger = ScaffoldMessenger.of(context),
      _messageHeight =
          ((MediaQuery.sizeOf(context).height -
                      MediaQuery.viewInsetsOf(context).bottom -
                      bottomInset) *
                  0.25)
              .clamp(48.0, 160.0),
      _reduceMotion = MediaQuery.maybeOf(context)?.disableAnimations ?? false;

  final ScaffoldMessengerState _messenger;
  final double _messageHeight;
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
      if (!_messenger.mounted) return;
      _messenger.removeCurrentSnackBar();
      if (kind == NoticeKind.success) {
        _messenger.showSnackBar(
          SnackBar(
            content: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: _messageHeight),
              child: SingleChildScrollView(child: Text(message)),
            ),
            duration: const Duration(seconds: 3),
            persist: false,
          ),
        );
        return;
      }
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
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final (label, icon, foreground, tint) = switch (kind) {
      NoticeKind.success => (
        'Success',
        PhosphorIconsRegular.checkCircle,
        dark ? const Color(0xFF86EFAC) : const Color(0xFF16733D),
        dark ? const Color(0xFF153B2C) : const Color(0xFFE8F5EE),
      ),
      NoticeKind.error => (
        'Error',
        PhosphorIconsRegular.warningCircle,
        scheme.error,
        scheme.errorContainer,
      ),
      NoticeKind.info => (
        'Information',
        PhosphorIconsRegular.info,
        scheme.primary,
        scheme.primaryContainer,
      ),
    };
    final media = MediaQuery.of(context);
    final textHeight =
        ((media.size.height -
                    media.viewInsets.bottom -
                    media.padding.vertical -
                    bottomInset) *
                0.25)
            .clamp(48.0, 160.0);

    return Material(
      color: scheme.surfaceContainerLowest,
      surfaceTintColor: Colors.transparent,
      elevation: 4,
      shadowColor: Colors.black.withValues(alpha: dark ? 0.3 : 0.14),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 4, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: ExcludeSemantics(
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: tint,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, size: 24, color: foreground),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10),
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
                          style: theme.textTheme.bodyLarge?.copyWith(
                            color: scheme.onSurface,
                            fontWeight: FontWeight.w600,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ),
                    if (onAction != null)
                      TextButton(
                        style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(48, 48),
                        ),
                        onPressed: onAction,
                        child: Text(actionLabel),
                      ),
                  ],
                ),
              ),
            ),
            IconButton(
              tooltip: 'Dismiss message',
              onPressed: onDismiss,
              constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
              icon: Icon(
                PhosphorIconsRegular.x,
                size: 20,
                color: scheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
