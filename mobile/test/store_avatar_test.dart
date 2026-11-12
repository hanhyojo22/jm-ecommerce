import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/widgets/common.dart';

void main() {
  testWidgets('supplied store photo needs no lookup', (tester) async {
    final api = _StoreApi();
    await tester.pumpWidget(
      _app(
        api,
        const StoreAvatar(
          name: 'Trail and Tide',
          logoUrl: 'https://example.com/logo.png',
          storeSlug: 'trail',
        ),
      ),
    );
    expect(
      tester
          .widget<CachedNetworkImage>(find.byType(CachedNetworkImage))
          .imageUrl,
      'https://example.com/logo.png',
    );
    expect(api.reads, 0);
  });

  testWidgets('compact store identities share lookup and display its photo', (
    tester,
  ) async {
    final api = _StoreApi();
    await tester.pumpWidget(
      _app(
        api,
        const Row(
          children: [
            StoreAvatar(name: 'Trail and Tide', storeSlug: 'trail'),
            StoreAvatar(name: 'Trail and Tide', storeSlug: 'trail'),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(api.reads, 1);
    expect(find.byType(CachedNetworkImage), findsNWidgets(2));
    for (final photo in tester.widgetList<CachedNetworkImage>(
      find.byType(CachedNetworkImage),
    )) {
      expect(photo.imageUrl, 'https://example.com/trail.png');
    }
  });

  testWidgets('missing or failed store lookup retains the initials fallback', (
    tester,
  ) async {
    final api = _StoreApi()..fail = true;
    await tester.pumpWidget(
      _app(api, const StoreAvatar(name: 'Trail Tide', storeSlug: 'trail')),
    );
    await tester.pumpAndSettle();
    expect(find.text('TT'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Widget _app(ApiClient api, Widget child) => ProviderScope(
  overrides: [apiClientProvider.overrideWithValue(api)],
  child: MaterialApp(home: Scaffold(body: child)),
);

class _StoreApi extends ApiClient {
  _StoreApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  int reads = 0;
  bool fail = false;

  @override
  Future<StoreInfo> store(String slug) async {
    reads++;
    if (fail) throw Exception('Offline');
    return StoreInfo(
      id: 'store',
      name: 'Trail and Tide',
      slug: slug,
      logoUrl: 'https://example.com/trail.png',
    );
  }
}
