import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../providers/connectivity_providers.dart';

/// A slim bar under every screen while the phone has no Wi-Fi or mobile data, then
/// "Back online" for a moment. Screens that failed load again on reconnect, and when the
/// app is reopened (a phone with no data load still reports a connection).
class OfflineBar extends ConsumerStatefulWidget {
  const OfflineBar({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<OfflineBar> createState() => _OfflineBarState();
}

class _OfflineBarState extends ConsumerState<OfflineBar>
    with WidgetsBindingObserver {
  bool _backOnline = false;
  Timer? _hideBackOnline;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _hideBackOnline?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Android can miss telling the app it is back online, so ask again on every return.
      ref.invalidate(isOnlineProvider);
      failedProviders.reloadFailed();
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(isOnlineProvider, (previous, next) {
      final wasOnline = previous?.value ?? true;
      final online = next.value ?? true;
      if (wasOnline || !online) {
        return;
      }
      failedProviders.reloadFailed();
      _hideBackOnline?.cancel();
      setState(() => _backOnline = true);
      _hideBackOnline = Timer(const Duration(seconds: 2), () {
        if (mounted) {
          setState(() => _backOnline = false);
        }
      });
    });

    final online = ref.watch(isOnlineProvider).value ?? true;
    final showBar = !online || _backOnline;

    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final background = online
        ? const Color(0xFF0F766E)
        : dark
        ? const Color(0xFF16213B)
        : const Color(0xFF0D1F4F);
    const foreground = Colors.white;
    final borderColor = online
        ? Colors.transparent
        : dark
        ? const Color(0x3894A3B8)
        : const Color(0x1AFFFFFF);
    final animDuration = MediaQuery.disableAnimationsOf(context)
        ? Duration.zero
        : const Duration(milliseconds: 180);

    // The tree keeps one shape whether or not the bar shows, so the app below never rebuilds.
    return Column(
      children: [
        // The bar takes the gesture-area inset, so the screen above must not pad for it too.
        Expanded(
          child: MediaQuery.removePadding(
            context: context,
            removeBottom: showBar,
            child: widget.child,
          ),
        ),
        AnimatedSize(
          duration: animDuration,
          curve: Curves.easeOutCubic,
          alignment: Alignment.topCenter,
          child: showBar
              ? Semantics(
                  liveRegion: true,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: background,
                      border: Border(top: BorderSide(color: borderColor)),
                    ),
                    child: Material(
                      type: MaterialType.transparency,
                      child: SafeArea(
                        top: false,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          child: Row(
                            children: [
                              Icon(
                                online
                                    ? PhosphorIconsRegular.wifiHigh
                                    : PhosphorIconsRegular.wifiSlash,
                                size: 16,
                                color: foreground,
                              ),
                              const SizedBox(width: 9),
                              Expanded(
                                child: Text(
                                  online
                                      ? 'Back online'
                                      : "No internet connection. We'll reload when you're back.",
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: foreground,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                )
              : const SizedBox.shrink(),
        ),
      ],
    );
  }
}
