import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:jmmart/src/core/location_service.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/cart_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/providers/chat_providers.dart';
import 'package:jmmart/src/providers/push_providers.dart';
import 'package:jmmart/src/providers/store_locator_providers.dart';
import 'package:jmmart/src/theme.dart';
import 'package:jmmart/src/widgets/shell.dart';
import 'package:jmmart/src/widgets/welcome_promo.dart';

void main() {
  const channel = MethodChannel('jmmart/welcome_promo');
  final binding = TestWidgetsFlutterBinding.ensureInitialized();
  var shown = false;

  setUp(() {
    shown = false;
    binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, (
      call,
    ) async {
      expect(call.method, 'claimWelcomePromo');
      if (shown) {
        return false;
      }
      shown = true;
      return true;
    });
  });

  tearDown(() {
    binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, null);
  });

  testWidgets(
    'startup waits for the promo before location and profile prompts',
    (tester) async {
      final location = _Location(status: LocationPermission.denied);
      await _pumpApp(
        tester,
        signedIn: true,
        location: location,
        profileComplete: false,
      );

      expect(find.byType(WelcomePromoDialog), findsOneWidget);
      expect(shown, isTrue);
      expect(location.checks, 0);
      expect(find.text('Complete your profile'), findsNothing);
      await tester.tap(find.byTooltip('Close promotion'));
      await tester.pumpAndSettle();
      expect(find.text('Find your nearest branch'), findsOneWidget);
      expect(find.text('Complete your profile'), findsNothing);
      await tester.tap(find.text('Not now'));
      await tester.pumpAndSettle();
      expect(find.text('Complete your profile'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('a guest can register and signing in never repeats the promo', (
    tester,
  ) async {
    await _pumpApp(tester, signedIn: false);
    expect(find.byType(WelcomePromoDialog), findsOneWidget);
    await tester.tap(find.text('Register & shop'));
    await tester.pumpAndSettle();
    expect(find.text('Create an account'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await _pumpApp(tester, signedIn: true);
    expect(find.byType(WelcomePromoDialog), findsNothing);
    expect(find.text('Create an account'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'signed-in shoppers can continue and restarting stays dismissed',
    (tester) async {
      await _pumpApp(tester, signedIn: true);
      expect(find.text('Shop now'), findsOneWidget);
      expect(find.text('Register & shop'), findsNothing);
      await tester.tap(find.text('Shop now'));
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePromoDialog), findsNothing);
      expect(find.text('Create an account'), findsNothing);

      await tester.pumpWidget(const SizedBox());
      await _pumpApp(tester, signedIn: false);
      expect(find.byType(WelcomePromoDialog), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  for (final dismissal in ['close', 'back', 'outside', 'continue browsing']) {
    testWidgets('$dismissal dismisses the promo permanently', (tester) async {
      await _pumpApp(tester, signedIn: false);
      expect(shown, isTrue);
      switch (dismissal) {
        case 'close':
          await tester.tap(find.byTooltip('Close promotion'));
        case 'back':
          await tester.binding.handlePopRoute();
        case 'outside':
          await tester.tapAt(const Offset(5, 5));
        case 'continue browsing':
          await tester.ensureVisible(find.text('Continue browsing'));
          await tester.tap(find.text('Continue browsing'));
      }
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePromoDialog), findsNothing);

      await tester.pumpWidget(const SizedBox());
      await _pumpApp(tester, signedIn: false);
      expect(find.byType(WelcomePromoDialog), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('a persistence failure leaves shopping usable', (tester) async {
    binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, (_) async {
      throw PlatformException(code: 'storage_unavailable');
    });
    final location = _Location();
    await _pumpApp(tester, signedIn: false, location: location);
    expect(find.byType(WelcomePromoDialog), findsNothing);
    expect(location.checks, 1);
    expect(tester.takeException(), isNull);
  });

  for (final size in [const Size(320, 568), const Size(640, 320)]) {
    testWidgets('the promo fits $size with large text and usable controls', (
      tester,
    ) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(2)),
            child: child!,
          ),
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => showWelcomePromo(context, signedIn: false),
                child: const Text('Open'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open'));
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePromoDialog), findsOneWidget);
      expect(find.byTooltip('Close promotion').hitTestable(), findsOneWidget);
      expect(
        tester.getSize(find.byType(FilledButton)).height,
        greaterThanOrEqualTo(48),
      );
      expect(find.byType(FilledButton).hitTestable(), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.tap(find.byTooltip('Close promotion'));
      await tester.pumpAndSettle();
      expect(find.byType(WelcomePromoDialog), findsNothing);
    });
  }
}

Future<void> _pumpApp(
  WidgetTester tester, {
  required bool signedIn,
  _Location? location,
  bool profileComplete = true,
}) async {
  final router = GoRouter(
    routes: [
      GoRoute(path: '/', builder: (_, _) => const AppShell()),
      GoRoute(
        path: '/sign-in',
        builder: (_, _) => const Scaffold(body: Text('Create an account')),
      ),
      GoRoute(
        path: '/profile',
        builder: (_, _) => const Scaffold(body: Text('Complete your profile')),
      ),
    ],
  );
  addTearDown(router.dispose);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        isSignedInProvider.overrideWithValue(signedIn),
        productsProvider.overrideWith(_EmptyProducts.new),
        categoriesProvider.overrideWith((ref) async => const []),
        dealsProvider.overrideWith((ref) async => const []),
        boughtBeforeProvider.overrideWith((ref) async => const []),
        bestSellersProvider.overrideWith((ref) async => const []),
        nearestStoreProvider.overrideWith(_NoBranches.new),
        cartCountProvider.overrideWithValue(0),
        conversationsProvider.overrideWith((ref) async => const []),
        pushRegistrationProvider.overrideWith((ref) async {}),
        meProvider.overrideWith(
          (ref) async => Account.fromJson({
            'id': 'buyer',
            'profile_complete': profileComplete,
          }),
        ),
        locationServiceProvider.overrideWithValue(location ?? _Location()),
      ],
      child: MaterialApp.router(theme: buildAppTheme(), routerConfig: router),
    ),
  );
  await tester.pumpAndSettle();
}

class _EmptyProducts extends ProductsNotifier {
  @override
  Future<ProductFeed> build() async => const ProductFeed(
    filter: CatalogFilter(),
    products: [],
    page: 1,
    lastPage: 1,
    total: 0,
  );
}

class _NoBranches extends NearestStoreNotifier {
  @override
  Future<StoreLocatorState> build() async =>
      const StoreLocatorState(branches: []);
}

class _Location extends LocationService {
  _Location({this.status = LocationPermission.whileInUse});

  final LocationPermission status;
  int checks = 0;

  @override
  Future<LocationPermission> permission() async {
    checks++;
    return status;
  }
}
