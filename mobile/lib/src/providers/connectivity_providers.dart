import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart';

/// Whether the phone has Wi-Fi or mobile data. A phone with no data load still reports
/// online, so screens also go by the error a request returns.
final isOnlineProvider = StreamProvider<bool>((ref) async* {
  bool hasNetwork(List<ConnectivityResult> results) =>
      results.any((result) => result != ConnectivityResult.none);

  final connectivity = Connectivity();
  try {
    yield hasNetwork(await connectivity.checkConnectivity());
    yield* connectivity.onConnectivityChanged.map(hasNetwork);
  } catch (_) {
    // No plugin (widget tests) or a platform failure: requests still report their own errors.
    yield true;
  }
});

/// Remembers which providers last ended in an error, so they can load again once the
/// phone is back online or the app is reopened.
final class FailedProviderTracker extends ProviderObserver {
  final _failed = <ProviderBase<Object?>, ProviderContainer>{};

  @override
  void providerDidFail(
    ProviderObserverContext context,
    Object error,
    StackTrace stackTrace,
  ) {
    _failed[context.provider] = context.container;
  }

  @override
  void didUpdateProvider(
    ProviderObserverContext context,
    Object? previousValue,
    Object? newValue,
  ) {
    if (newValue is! AsyncError) {
      _failed.remove(context.provider);
    }
  }

  @override
  void didDisposeProvider(ProviderObserverContext context) {
    _failed.remove(context.provider);
  }

  /// Loads every failed provider again.
  void reloadFailed() {
    final failed = Map.of(_failed);
    _failed.clear();
    failed.forEach((provider, container) => container.invalidate(provider));
  }
}

final failedProviders = FailedProviderTracker();
