import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/screens/cart_screen.dart';
import 'package:jmmart/src/theme.dart';

void main() {
  testWidgets('selection waits for confirmation before enabling checkout', (
    tester,
  ) async {
    final api = _CartApi()
      ..selectionPending = Completer<({int selectedCount, String subtotal})>();
    await _pump(tester, api);
    await tester.tap(find.byType(Checkbox).first);
    await tester.pump();
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Checkout (1)'),
          )
          .onPressed,
      isNull,
    );
    api.selectionPending!.complete((selectedCount: 0, subtotal: '0.00'));
    await tester.pumpAndSettle();
    expect(find.text('0 items selected'), findsOneWidget);
    expect(tester.widget<Checkbox>(find.byType(Checkbox).first).value, isFalse);
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, 'Checkout'))
          .onPressed,
      isNull,
    );
  });

  testWidgets('failed removal leaves the item and allows retry', (
    tester,
  ) async {
    final api = _CartApi()..removePending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.text('Edit'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Remove'));
    await tester.pump();
    expect(
      tester
          .widget<TextButton>(find.widgetWithText(TextButton, 'Remove'))
          .onPressed,
      isNull,
    );
    api.removePending!.completeError(
      const ApiException(code: 'network', message: 'Please retry removal.'),
    );
    await tester.pumpAndSettle();
    expect(find.text('Cart (1)'), findsOneWidget);
    expect(find.text('Please retry removal.'), findsOneWidget);
    expect(
      tester
          .widget<TextButton>(find.widgetWithText(TextButton, 'Remove'))
          .onPressed,
      isNotNull,
    );
  });

  test('quantity response after provider disposal completes safely', () async {
    final api = _CartApi()..pending = Completer<Cart>();
    final container = ProviderContainer(
      overrides: [apiClientProvider.overrideWithValue(api)],
    );
    container.listen(cartProvider, (_, _) {});
    await container.read(cartProvider.future);
    final operation = container
        .read(cartProvider.notifier)
        .updateQuantity('line', 2);
    container.dispose();
    api.pending!.complete(await api.cart());
    await expectLater(operation, completes);
  });

  test(
    'clear selection uses response without a redundant cart fetch',
    () async {
      final api = _CartApi();
      final container = ProviderContainer(
        overrides: [apiClientProvider.overrideWithValue(api)],
      );
      addTearDown(container.dispose);
      container.listen(cartProvider, (_, _) {});
      await container.read(cartProvider.future);
      api.failRead = true;
      await container.read(cartProvider.notifier).clearSelection();
      expect(container.read(cartProvider).requireValue.selectedCount, 0);
      expect(
        container.read(cartProvider).requireValue.lines.single.selected,
        isFalse,
      );
    },
  );

  testWidgets('pending quantity blocks duplicate writes and checkout', (
    tester,
  ) async {
    final api = _CartApi()..pending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.byTooltip('Increase quantity'));
    await tester.pump();
    expect(
      tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.add_rounded),
          )
          .onPressed,
      isNull,
    );
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Checkout (1)'),
          )
          .onPressed,
      isNull,
    );
    expect(
      tester.widget<Checkbox>(find.byType(Checkbox).first).onChanged,
      isNull,
    );
    expect(find.byType(LinearProgressIndicator), findsOneWidget);
    api.pending!.complete(await api.cart());
    await tester.pumpAndSettle();
    expect(api.updates, 1);
    expect(find.text('2'), findsOneWidget);
  });

  testWidgets('failed refresh preserves cart and shows recovery feedback', (
    tester,
  ) async {
    final api = _CartApi();
    await _pump(tester, api);
    api.failRead = true;
    await tester
        .widget<RefreshIndicator>(find.byType(RefreshIndicator))
        .onRefresh();
    await tester.pumpAndSettle();
    expect(find.text('Cannot refresh cart. Try again.'), findsOneWidget);
    expect(find.text('Cart (1)'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('failed quantity preserves totals and allows retry', (
    tester,
  ) async {
    final api = _CartApi()..pending = Completer<Cart>();
    await _pump(tester, api);
    await tester.tap(find.byTooltip('Increase quantity'));
    await tester.pump();
    api.pending!.completeError(
      const ApiException(code: 'stock', message: 'Only one item remains.'),
    );
    await tester.pumpAndSettle();
    expect(find.text('Only one item remains.'), findsOneWidget);
    expect(find.text('1'), findsOneWidget);
    expect(
      tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.add_rounded),
          )
          .onPressed,
      isNotNull,
    );
  });

  testWidgets(
    'edit reveals removal and deleting the item shows the empty cart',
    (tester) async {
      final api = _CartApi();
      var browsed = false;
      await _pump(tester, api, onBrowseProducts: () => browsed = true);
      expect(find.text('Remove'), findsNothing);
      await tester.tap(find.text('Edit'));
      await tester.pumpAndSettle();
      expect(api.removals, 0);
      expect(find.text('Done'), findsOneWidget);
      await tester.tap(find.text('Remove'));
      await tester.pumpAndSettle();
      expect(api.removals, 1);
      expect(find.text('Your cart is empty'), findsOneWidget);
      await tester.tap(find.text('Browse products'));
      expect(browsed, isTrue);
    },
  );

  testWidgets('quantity controls retain the server response and minimum', (
    tester,
  ) async {
    final api = _CartApi();
    await _pump(tester, api);
    expect(
      tester
          .widget<IconButton>(
            find.widgetWithIcon(IconButton, Icons.remove_rounded),
          )
          .onPressed,
      isNull,
    );
    await tester.tap(find.byTooltip('Increase quantity'));
    await tester.pumpAndSettle();
    expect(api.quantity, 2);
    expect(find.text('2'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'unavailable items expose remove without enabling quantity changes',
    (tester) async {
      await _pump(tester, _CartApi()..available = false);
      expect(find.text('Remove'), findsOneWidget);
      expect(
        tester
            .widget<IconButton>(
              find.widgetWithIcon(IconButton, Icons.add_rounded),
            )
            .onPressed,
        isNull,
      );
      expect(
        tester
            .widget<FilledButton>(find.widgetWithText(FilledButton, 'Checkout'))
            .onPressed,
        isNull,
      );
    },
  );

  for (final width in [320.0, 390.0, 768.0]) {
    for (final scale in [1.0, 2.0]) {
      for (final dark in [false, true]) {
        testWidgets('cart fits $width at ${scale}x in dark=$dark', (
          tester,
        ) async {
          await _pump(
            tester,
            _CartApi()..quantity = 100,
            width: width,
            scale: scale,
            dark: dark,
          );
          expect(tester.takeException(), isNull);
          await tester.tap(find.text('Edit'));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        });
      }
    }
  }
}

Future<void> _pump(
  WidgetTester tester,
  _CartApi api, {
  double width = 390,
  double scale = 1,
  bool dark = false,
  VoidCallback? onBrowseProducts,
}) async {
  tester.view.physicalSize = Size(width, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: MaterialApp(
        theme: buildAppTheme(
          brightness: dark ? Brightness.dark : Brightness.light,
        ),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(context)
              .copyWith(textScaler: TextScaler.linear(scale)),
          child: child!,
        ),
        home: CartScreen(onBrowseProducts: onBrowseProducts),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _CartApi extends ApiClient {
  _CartApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);
  bool available = true;
  int quantity = 1;
  int removals = 0;
  int updates = 0;
  bool failRead = false;
  Completer<Cart>? pending;
  Completer<Cart>? removePending;
  Completer<({int selectedCount, String subtotal})>? selectionPending;

  @override
  Future<({int selectedCount, String subtotal})> updateCartSelection(
    List<String> ids,
  ) async {
    return selectionPending?.future ??
        (selectedCount: ids.length, subtotal: ids.isEmpty ? '0.00' : '250.00');
  }

  @override
  Future<Cart> cart() async => failRead
      ? throw const ApiException(
          code: 'network',
          message: 'Cannot refresh cart. Try again.',
        )
      : removals > 0
      ? Cart.empty
      : Cart(
          lines: [
            CartLine(
              id: 'line',
              productId: 'product',
              quantity: quantity,
              selected: available,
              available: available,
              unitPrice: '250.00',
              lineTotal: '${quantity * 250}.00',
              productName: 'Everyday linen shirt with a long product name',
              productSlug: 'shirt',
              storeName: 'Independent store',
              storeSlug: 'store',
              hasVariants: true,
              variantLabel: 'Navy blue / Large',
            ),
          ],
          selectedCount: available ? 1 : 0,
          subtotal: '${quantity * 250}.00',
        );

  @override
  Future<Cart> updateCartItem(String id, int quantity) async {
    updates++;
    this.quantity = quantity;
    return pending?.future ?? cart();
  }

  @override
  Future<Cart> removeCartItem(String id) async {
    if (removePending != null) return removePending!.future;
    removals++;
    return cart();
  }
}
