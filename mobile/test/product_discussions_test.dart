import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';
import 'package:jmmart/src/models/models.dart';
import 'package:jmmart/src/providers/auth_providers.dart';
import 'package:jmmart/src/providers/catalog_providers.dart';
import 'package:jmmart/src/screens/product_discussions.dart';

void main() {
  testWidgets('failed comment keeps its text and shows the error', (
    tester,
  ) async {
    final api = _DiscussionApi();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(api)],
        child: const MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ProductDiscussions(
                productKey: ProductKey(store: 'shop', slug: 'bag'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, 'Will it fit?');
    await tester.pump();
    await tester.tap(find.text('Post comment'));
    await tester.pump();
    api.post.completeError(
      const ApiException(
        code: 'validation.failed',
        message: 'Please revise your comment.',
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Will it fit?'), findsOneWidget);
    expect(find.text('Please revise your comment.'), findsOneWidget);
  });

  testWidgets(
    'comment appears while posting and reconciles after confirmation',
    (tester) async {
      final api = _DiscussionApi();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(api)],
          child: const MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: ProductDiscussions(
                  productKey: ProductKey(store: 'shop', slug: 'bag'),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField).first, 'Will it fit?');
      await tester.pump();
      await tester.tap(find.text('Post comment'));
      await tester.pump();

      expect(find.text('Will it fit?'), findsOneWidget);
      expect(find.text('Sending...'), findsOneWidget);

      api.completePost();
      await tester.pumpAndSettle();

      expect(find.text('Will it fit?'), findsOneWidget);
      expect(find.text('Sending...'), findsNothing);
      expect(find.text('Posted'), findsNothing);
    },
  );

  testWidgets('reply appears before server confirmation', (tester) async {
    final api = _DiscussionApi()..withRoot = true;
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(api)],
        child: const MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ProductDiscussions(
                productKey: ProductKey(store: 'shop', slug: 'bag'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Reply'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, 'Yes it does.');
    await tester.tap(find.text('Post reply'));
    await tester.pumpAndSettle();

    expect(api.parentId, 'root');
    expect(find.text('Yes it does.'), findsOneWidget);
    expect(find.text('Sending...'), findsOneWidget);
  });
}

class _DiscussionApi extends ApiClient {
  _DiscussionApi()
    : super(baseUrl: 'http://localhost', tokenProvider: () async => null);

  bool withRoot = false;
  bool saved = false;
  String? parentId;
  final post = Completer<ProductDiscussion>();

  @override
  Future<DiscussionPage> productDiscussions({
    required String store,
    required String product,
    int page = 1,
  }) async => DiscussionPage(
    discussions: saved
        ? const [
            ProductDiscussion(
              id: 'saved',
              rootId: 'saved',
              author: 'Ana',
              authorType: 'buyer',
              body: 'Will it fit?',
            ),
          ]
        : withRoot
        ? const [
            ProductDiscussion(
              id: 'root',
              rootId: 'root',
              author: 'Ana',
              authorType: 'buyer',
              body: 'Does it fit?',
            ),
          ]
        : const [],
    page: 1,
    lastPage: 1,
  );

  @override
  Future<ProductDiscussion> postProductDiscussion({
    required String store,
    required String product,
    required String body,
    String? parentId,
  }) {
    this.parentId = parentId;
    return post.future;
  }

  void completePost() {
    saved = true;
    post.complete(
      const ProductDiscussion(
        id: 'saved',
        rootId: 'saved',
        author: 'Ana',
        authorType: 'buyer',
        body: 'Will it fit?',
      ),
    );
  }
}
