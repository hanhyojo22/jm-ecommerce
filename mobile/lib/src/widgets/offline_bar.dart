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

    final scheme = Theme.of(context).colorScheme;
    // Dark neutral rather than red: being offline is a state, not a fault.
    final background = online ? const Color(0xFF1B7F4B) : scheme.inverseSurface;
    final foreground = online ? Colors.white : scheme.onInverseSurface;

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
        if (showBar)
          Semantics(
            liveRegion: true,
            child: Material(
              color: background,
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
                        size: 18,
                        color: foreground,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          online ? 'Back online' : "No internet connection. We'll reload when you're back.",
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: foreground,
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
      ],
    );
  }
}
