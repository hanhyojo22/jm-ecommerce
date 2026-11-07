import 'package:dio/dio.dart';

import '../models/models.dart';
import 'api_exception.dart';
import 'attachment_picker.dart';

typedef TokenProvider = Future<String?> Function();

class ApiClient {
  ApiClient({required String baseUrl, required this.tokenProvider}) {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 25),
        headers: const {'Accept': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          try {
            final token = await tokenProvider();

            if (token != null && token.isNotEmpty) {
              options.headers['Authorization'] = 'Bearer $token';
            }
          } catch (_) {
            // The request will surface a 401 and the gate will ask for sign-in.
          }

          handler.next(options);
        },
      ),
    );
  }

  late final Dio _dio;
  final TokenProvider tokenProvider;
  CancelToken? _productSearchCancelToken;
  CancelToken? _productSuggestionsCancelToken;

  Future<dynamic> _raw(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? query,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = switch (method) {
        'GET' => await _dio.get(
          path,
          queryParameters: query,
          cancelToken: cancelToken,
        ),
        'POST' => await _dio.post(path, data: data, cancelToken: cancelToken),
        'PATCH' => await _dio.patch(path, data: data, cancelToken: cancelToken),
        'DELETE' => await _dio.delete(
          path,
          data: data,
          cancelToken: cancelToken,
        ),
        _ => throw ArgumentError('Unsupported method $method'),
      };

      return response.data;
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }
  }

  Future<dynamic> _data(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? query,
  }) async {
    final body = await _raw(method, path, data: data, query: query);

    return body is Map ? body['data'] : body;
  }

  Future<Account> me() async =>
      Account.fromJson(mapOf(await _data('GET', '/me')));

  Future<ProductPage> products({
    String? query,
    String? category,
    String? seller,
    String? sort,
    int page = 1,
    int perPage = 20,
  }) async {
    final trimmedQuery = query?.trim() ?? '';
    CancelToken? cancelToken;

    if (trimmedQuery.isNotEmpty) {
      _productSearchCancelToken?.cancel('Replaced by a newer search.');
      cancelToken = CancelToken();
      _productSearchCancelToken = cancelToken;
    } else if (page == 1 && seller == null) {
      _productSearchCancelToken?.cancel('Search was cleared.');
      _productSearchCancelToken = null;
    }

    try {
      final body = mapOf(
        await _raw(
          'GET',
          '/products',
          query: {
            if (trimmedQuery.isNotEmpty) 'q': trimmedQuery,
            if (category != null && category.isNotEmpty) 'category': category,
            if (seller != null && seller.isNotEmpty) 'seller': seller,
            if (sort != null && sort.isNotEmpty) 'sort': sort,
            'page': page,
            'per_page': perPage,
          },
          cancelToken: cancelToken,
        ),
      );
      final meta = mapOf(body['meta']);

      return ProductPage(
        products: listOf(body['data'])
            .map(Product.fromJson)
            .toList(growable: false),
        page: integer(meta['current_page'], page),
        lastPage: integer(meta['last_page'], page),
        total: integer(meta['total']),
      );
    } finally {
      if (identical(_productSearchCancelToken, cancelToken)) {
        _productSearchCancelToken = null;
      }
    }
  }

  Future<List<Product>> suggestedProducts(String query) async {
    _productSuggestionsCancelToken?.cancel('Replaced by newer suggestions.');
    final cancelToken = CancelToken();
    _productSuggestionsCancelToken = cancelToken;

    try {
      final body = await _raw(
        'GET',
        '/products/suggestions',
        query: {'q': query},
        cancelToken: cancelToken,
      );
      final items = body is Map ? body['items'] : null;

      return listOf(items)
          .map((json) {
            return Product(
              id: str(json['product_id']),
              name: str(json['name']),
              slug: str(json['slug']),
              price: str(json['price']),
              effectivePrice: str(json['price']),
              inStock: !boolean(json['sold_out']),
              hasVariants: false,
              requiresVariantSelection: false,
              freeShipping: false,
              stockQuantity: boolean(json['sold_out']) ? 0 : 1,
              soldQuantity: 0,
              ratingAverage: 0,
              ratingCount: 0,
              thumbnailUrl: json['image_url'] as String?,
              store: StoreInfo(
                id: '',
                name: str(json['store_name']),
                slug: str(json['store_slug']),
              ),
            );
          })
          .toList(growable: false);
    } finally {
      if (identical(_productSuggestionsCancelToken, cancelToken)) {
        _productSuggestionsCancelToken = null;
      }
    }
  }

  void cancelProductSuggestions() {
    _productSuggestionsCancelToken?.cancel('Suggestions are no longer shown.');
    _productSuggestionsCancelToken = null;
  }

  Future<List<Category>> categories() async {
    final body = await _data('GET', '/categories');

    return listOf(body).map(Category.fromJson).toList(growable: false);
  }

  Future<ReviewPage> productReviews({
    required String store,
    required String product,
    int page = 1,
  }) async {
    final body = mapOf(
      await _raw(
        'GET',
        '/stores/${Uri.encodeComponent(store)}/products/${Uri.encodeComponent(product)}/reviews',
        query: {'page': page, 'per_page': 10},
      ),
    );
    final meta = mapOf(body['meta']);
    return ReviewPage(
      reviews: listOf(body['data'])
          .map(ProductReview.fromJson)
          .toList(growable: false),
      page: integer(meta['current_page'], page),
      lastPage: integer(meta['last_page'], page),
    );
  }

  Future<StoreInfo> store(String slug) async =>
      StoreInfo.fromJson(mapOf(await _data('GET', '/stores/$slug')));

  Future<Product> product({
    required String store,
    required String slug,
  }) async => Product.fromJson(
    mapOf(await _data('GET', '/stores/$store/products/$slug')),
  );

  Future<Cart> cart() async =>
      Cart.fromJson(mapOf(await _data('GET', '/cart')));

  Future<Cart> addToCart({
    required String productId,
    int quantity = 1,
    String? variantId,
  }) async => Cart.fromJson(
    mapOf(
      await _data(
        'POST',
        '/cart/items',
        data: {
          'product_id': productId,
          'quantity': quantity,
          'variant_id': ?variantId,
        },
      ),
    ),
  );

  Future<Cart> updateCartItem(String itemId, int quantity) async =>
      Cart.fromJson(
        mapOf(
          await _data(
            'PATCH',
            '/cart/items/$itemId',
            data: {'quantity': quantity},
          ),
        ),
      );

  Future<Cart> removeCartItem(String itemId) async =>
      Cart.fromJson(mapOf(await _data('DELETE', '/cart/items/$itemId')));

  Future<({int selectedCount, String subtotal})> updateCartSelection(
    List<String> itemIds,
  ) async {
    final data = mapOf(
      await _data('PATCH', '/cart/selection', data: {'selected': itemIds}),
    );

    return (
      selectedCount: integer(data['selected_count']),
      subtotal: str(data['subtotal'], '0.00'),
    );
  }

  Future<CheckoutQuote> checkoutQuote() async =>
      CheckoutQuote.fromJson(mapOf(await _data('GET', '/checkout')));

  Future<PlacedOrder> placeOrder({
    required Address address,
    required String paymentMethod,
    required String clientRequestId,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    final data = await _data(
      'POST',
      '/checkout',
      data: {
        ...address.toJson(),
        if (note != null && note.trim().isNotEmpty)
          'delivery_note': note.trim(),
        'payment_method': paymentMethod,
        'client_request_id': clientRequestId,
        'success_url': ?successUrl,
        'cancel_url': ?cancelUrl,
      },
    );

    return PlacedOrder.fromJson(mapOf(data));
  }

  Future<OrdersResult> orders({String tab = 'all', String? search}) async {
    final body = await _raw(
      'GET',
      '/orders',
      query: {
        'tab': tab,
        'per_page': 50,
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );

    return OrdersResult.fromEnvelope(mapOf(body));
  }

  Future<OrderDetail> order(String id) async =>
      OrderDetail.fromJson(mapOf(await _data('GET', '/orders/$id')));

  Future<String?> payOrder(
    String id, {
    String? successUrl,
    String? cancelUrl,
  }) async {
    final data = mapOf(
      await _data(
        'POST',
        '/orders/$id/pay',
        data: {'success_url': ?successUrl, 'cancel_url': ?cancelUrl},
      ),
    );

    return data['payment_url'] as String?;
  }

  Future<SellerOrder> confirmReceived(String sellerOrderId) async =>
      SellerOrder.fromJson(
        mapOf(
          await _data('POST', '/orders/seller-orders/$sellerOrderId/received'),
        ),
      );

  Future<List<Address>> addresses() async {
    final body = await _data('GET', '/delivery-addresses');

    return listOf(body).map(Address.fromJson).toList(growable: false);
  }

  Future<Address> createAddress(Address address) async => Address.fromJson(
    mapOf(await _data('POST', '/delivery-addresses', data: address.toJson())),
  );

  Future<Address> updateAddress(Address address) async => Address.fromJson(
    mapOf(
      await _data(
        'PATCH',
        '/delivery-addresses/${address.id}',
        data: address.toJson(),
      ),
    ),
  );

  Future<void> deleteAddress(String id) async {
    await _raw('DELETE', '/delivery-addresses/$id');
  }

  Future<List<Conversation>> conversations({String? search}) async {
    final body = await _data(
      'GET',
      '/conversations',
      query: {
        'per_page': 50,
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
      },
    );

    return listOf(body).map(Conversation.fromJson).toList(growable: false);
  }

  Future<Conversation> conversation(String id) async =>
      Conversation.fromJson(mapOf(await _data('GET', '/conversations/$id')));

  Future<Conversation> startConversation({
    String? productId,
    String? sellerOrderId,
  }) async {
    final data = await _data(
      'POST',
      '/conversations/start',
      data: {'product_id': ?productId, 'seller_order_id': ?sellerOrderId},
    );

    return Conversation.fromJson(mapOf(data));
  }

  Future<List<ChatMessage>> messages(String conversationId) async {
    final body = await _data('GET', '/conversations/$conversationId/messages');

    return listOf(body).map(ChatMessage.fromJson).toList(growable: false);
  }

  Future<ChatMessage> sendMessage({
    required String conversationId,
    required String body,
    required String clientMessageId,
    String? productId,
    String? sellerOrderId,
  }) async => ChatMessage.fromJson(
    mapOf(
      await _data(
        'POST',
        '/conversations/$conversationId/messages',
        data: {
          'body': body,
          'client_message_id': clientMessageId,
          'product_id': ?productId,
          'seller_order_id': ?sellerOrderId,
        },
      ),
    ),
  );

  Future<ChatMessage> sendAttachment({
    required String conversationId,
    required PickedAttachment attachment,
    required String clientMessageId,
    String body = '',
    String? productId,
    String? sellerOrderId,
  }) async => ChatMessage.fromJson(
    mapOf(
      await _data(
        'POST',
        '/conversations/$conversationId/attachments',
        data: FormData.fromMap({
          'attachment': MultipartFile.fromBytes(
            attachment.bytes,
            filename: attachment.name,
            contentType: DioMediaType.parse(attachment.mimeType),
          ),
          'client_message_id': clientMessageId,
          if (body.trim().isNotEmpty) 'body': body.trim(),
          'product_id': ?productId,
          'seller_order_id': ?sellerOrderId,
        }),
      ),
    ),
  );

  Future<void> markRead(String conversationId) async {
    await _raw('POST', '/conversations/$conversationId/read');
  }

  Future<void> typing(String conversationId) async {
    await _raw('POST', '/conversations/$conversationId/typing');
  }

  Future<void> submitReview({
    required String orderItemId,
    required int rating,
    String? title,
    String? body,
  }) async {
    await _raw(
      'POST',
      '/order-items/$orderItemId/review',
      data: {
        'rating': rating,
        if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
        if (body != null && body.trim().isNotEmpty) 'body': body.trim(),
      },
    );
  }

  Future<String> broadcastAuth({
    required String socketId,
    required String channelName,
  }) async {
    final body = await _raw(
      'POST',
      '/broadcasting/auth',
      data: {'socket_id': socketId, 'channel_name': channelName},
    );

    return str(body is Map ? body['auth'] : null);
  }

  Future<void> registerDeviceToken(String token) async {
    await _raw(
      'POST',
      '/device-tokens',
      data: {'token': token, 'platform': 'android'},
    );
  }

  Future<void> unregisterDeviceToken(String token) async {
    await _raw('DELETE', '/device-tokens', data: {'token': token});
  }
}
