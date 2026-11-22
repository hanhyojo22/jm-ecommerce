import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/providers/connectivity_providers.dart';
import 'package:jmmart/src/widgets/common.dart';
import 'package:jmmart/src/widgets/offline_bar.dart';

const _offlineError = ApiException(
  code: 'network.error',
  message: 'No connection.',
);

Future<StreamController<bool>> _pump(WidgetTester tester, Widget body) async {
  final connection = StreamController<bool>();
  addTearDown(connection.close);
  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      observers: [failedProviders],
      overrides: [isOnlineProvider.overrideWith((ref) => connection.stream)],
      child: MaterialApp(
        home: OfflineBar(child: Scaffold(body: body)),
      ),
    ),
  );
  await tester.pump();
  return connection;
}

void main() {
  testWidgets('offline shows a bar, and coming back reloads what failed', (
    tester,
  ) async {
    var attempts = 0;
    final flaky = FutureProvider.autoDispose<String>((ref) async {
      attempts++;
      if (attempts == 1) {
        throw _offlineError;
      }
      return 'Loaded';
    });
    final connection = await _pump(
      tester,
      Consumer(
        builder: (context, ref, _) => Text(
          ref
              .watch(flaky)
              .when(
                data: (value) => value,
                error: (_, _) => 'Failed',
                loading: () => 'Loading',
              ),
        ),
      ),
    );
    expect(find.text('Failed'), findsOneWidget);

    connection.add(false);
    await tester.pump();
    await tester.pump();
    expect(find.textContaining('No internet connection'), findsOneWidget);

    connection.add(true);
    await tester.pump();
    await tester.pump();
    await tester.pump();
    expect(find.text('Back online'), findsOneWidget);
    expect(find.text('Loaded'), findsOneWidget);

    await tester.pump(const Duration(seconds: 3));
    expect(find.text('Back online'), findsNothing);
    expect(find.textContaining('No internet connection'), findsNothing);
  });

  testWidgets('offline, a failed refresh keeps what already loaded', (
    tester,
  ) async {
    var attempts = 0;
    final orders = FutureProvider.autoDispose<String>((ref) async {
      attempts++;
      if (attempts > 1) {
        throw _offlineError;
      }
      return 'Order EP-1';
    });
    final connection = await _pump(
      tester,
      Consumer(
        builder: (context, ref, _) => AsyncView(
          value: ref.watch(orders),
          builder: (value) => Text(value),
        ),
      ),
    );
    expect(find.text('Order EP-1'), findsOneWidget);

    connection.add(false);
    await tester.pump();
    ProviderScope.containerOf(tester.element(find.byType(Scaffold)))
        .invalidate(orders);
    await tester.pump();
    await tester.pump();

    expect(find.text('Order EP-1'), findsOneWidget);
    expect(find.text('No connection.'), findsNothing);
  });
}
