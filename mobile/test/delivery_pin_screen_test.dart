import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/store_branch.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/screens/delivery_pin_screen.dart';
import 'package:jmmart/src/theme.dart';
import 'package:latlong2/latlong.dart';

const _branch = StoreBranch(
  id: 'makati',
  branchId: 'B-MAKATI',
  name: 'Makati branch',
  address: 'Makati',
  latitude: 14.5547,
  longitude: 121.0244,
  maxDeliveryRadiusKm: 5,
);
const _place = <String, dynamic>{
  'label': 'Ayala Center, Makati',
  'latitude': 14.551,
  'longitude': 121.025,
};

void main() {
  testWidgets('typing an address moves the map only after a result is chosen', (
    tester,
  ) async {
    final api = _SearchApi();
    final answer = await _pump(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'Ay');
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.searched, isEmpty);
    await _type(tester, 'Ayala');
    expect(api.searched, ['Ayala']);
    expect(answer.value, isNull);
    await tester.tap(find.text(_place['label'] as String));
    await tester.pumpAndSettle();
    expect(find.text(_place['label'] as String), findsOneWidget);
    expect(answer.value, isNull);
    await tester.tap(find.text('Confirm pin'));
    await tester.pumpAndSettle();
    expect(answer.value?.pin?.latitude, _place['latitude']);
    expect(answer.value?.pin?.longitude, _place['longitude']);
    expect(answer.value?.pickup, isFalse);
  });

  testWidgets('a far search result still cannot be confirmed for delivery', (
    tester,
  ) async {
    final api = _SearchApi()
      ..results = [
        {..._place, 'latitude': 14.8},
      ];
    final answer = await _pump(tester, api);
    await _openSearch(tester);
    await _type(tester, 'Ayala');
    await tester.tap(find.text(_place['label'] as String));
    await tester.pumpAndSettle();
    expect(find.text('Too far for delivery'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Move the pin closer'),
          )
          .onPressed,
      isNull,
    );
    await tester.tap(find.text('Choose pickup instead'));
    await tester.pumpAndSettle();
    expect(answer.value?.pickup, isTrue);
  });

  testWidgets('older responses cannot replace the latest address results', (
    tester,
  ) async {
    final old = Completer<List<Map<String, dynamic>>>();
    final latest = Completer<List<Map<String, dynamic>>>();
    final api = _SearchApi()
      ..handler = (query) => query == 'Old street' ? old.future : latest.future;
    await _pump(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'Old street');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.enterText(find.byType(TextField), 'New street');
    await tester.pump(const Duration(milliseconds: 400));
    latest.complete([
      {..._place, 'label': 'New result'},
    ]);
    await tester.pumpAndSettle();
    old.complete([
      {..._place, 'label': 'Old result'},
    ]);
    await tester.pumpAndSettle();
    expect(find.text('New result'), findsOneWidget);
    expect(find.text('Old result'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('clearing a pending search keeps late results hidden', (
    tester,
  ) async {
    final pending = Completer<List<Map<String, dynamic>>>();
    final api = _SearchApi()..handler = (_) => pending.future;
    await _pump(tester, api);
    await _openSearch(tester);
    await tester.enterText(find.byType(TextField), 'Ayala');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.tap(find.byTooltip('Clear search'));
    await tester.pumpAndSettle();
    pending.complete([_place]);
    await tester.pumpAndSettle();
    expect(find.text('Find your delivery address'), findsOneWidget);
    expect(find.text(_place['label'] as String), findsNothing);
  });

  testWidgets(
    'search failure can retry and empty results explain what to try',
    (tester) async {
      final api = _SearchApi()
        ..handler = (_) async => throw const ApiException(
          code: 'address_lookup_failed',
          message: 'Unavailable',
        );
      await _pump(tester, api);
      await _openSearch(tester);
      await _type(tester, 'Unknown street');
      expect(find.text('Address search is unavailable'), findsOneWidget);
      api.handler = (_) async => [];
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('No matching addresses'), findsOneWidget);
      expect(api.searched.length, 2);
    },
  );

  testWidgets(
    'closing search leaves the original pin and handles a late failure',
    (tester) async {
      final pending = Completer<List<Map<String, dynamic>>>();
      final api = _SearchApi()..handler = (_) => pending.future;
      final answer = await _pump(tester, api);
      await _openSearch(tester);
      await tester.enterText(find.byType(TextField), 'Ayala');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.tap(find.byTooltip('Back to map'));
      await tester.pumpAndSettle();
      pending.completeError(
        const ApiException(
          code: 'address_lookup_failed',
          message: 'Unavailable',
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm pin'));
      await tester.pumpAndSettle();
      expect(answer.value?.pin?.latitude, _branch.latitude);
      expect(answer.value?.pin?.longitude, _branch.longitude);
      expect(tester.takeException(), isNull);
    },
  );

  for (final (width, height, scale) in [
    (320.0, 844.0, 2.0),
    (844.0, 390.0, 1.3),
    (768.0, 1024.0, 1.3),
  ]) {
    testWidgets('address search fits $width x $height at $scale text', (
      tester,
    ) async {
      await _pump(
        tester,
        _SearchApi(),
        width: width,
        height: height,
        scale: scale,
      );
      await _openSearch(tester);
      await _type(tester, 'Ayala');
      tester.view.viewInsets = const FakeViewPadding(bottom: 200);
      await tester.pumpAndSettle();
      expect(find.text(_place['label'] as String), findsOneWidget);
      expect(tester.takeException(), isNull);
      tester.view.resetViewInsets();
      await tester.pumpAndSettle();
    });
  }
}

Future<void> _openSearch(WidgetTester tester) async {
  await tester.tap(find.bySemanticsLabel('Search delivery address'));
  await tester.pumpAndSettle();
}

Future<void> _type(WidgetTester tester, String query) async {
  await tester.enterText(find.byType(TextField), query);
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pumpAndSettle();
}

Future<ValueNotifier<PinChoice?>> _pump(
  WidgetTester tester,
  _SearchApi api, {
  double width = 390,
  double height = 844,
  double scale = 1,
}) async {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.view.resetViewInsets);
  final answer = ValueNotifier<PinChoice?>(null);
  addTearDown(answer.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        theme: buildAppTheme(),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async =>
                  answer.value = await Navigator.of(context).push<PinChoice>(
                    MaterialPageRoute(
                      builder: (_) => DeliveryPinScreen(
                        initial: LatLng(_branch.latitude, _branch.longitude),
                        branch: _branch,
                      ),
                    ),
                  ),
              child: const Text('Open pin'),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open pin'));
  await tester.pumpAndSettle();
  return answer;
}

class _SearchApi extends ApiClient {
  _SearchApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  final searched = <String>[];
  List<Map<String, dynamic>> results = [_place];
  Future<List<Map<String, dynamic>>> Function(String)? handler;

  @override
  Future<List<Map<String, dynamic>>> searchAddresses(String query) async {
    searched.add(query);
    return handler == null ? results : await handler!(query);
  }
}
