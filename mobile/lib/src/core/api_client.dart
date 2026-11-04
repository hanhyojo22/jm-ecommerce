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

  Future<dynamic> _raw(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = switch (method) {
        'GET' => await _dio.get(path, queryParameters: query),
        'POST' => await _dio.post(path, data: data),
        'PATCH' => await _dio.patch(path, data: data),
        'DELETE' => await _dio.delete(path),
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
    String? sort,
    int page = 1,
    int perPage = 20,
  }) async {
    final body = mapOf(
      await _raw(
        'GET',
        '/products',
        query: {
          if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
          if (category != null && category.isNotEmpty) 'category': category,
          if (sort != null && sort.isNotEmpty) 'sort': sort,
          'page': page,
          'per_page': perPage,
        },
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
  }

  Future<List<Product>> suggestedProducts(String query) async {
    final body = await _raw(
      'GET',
      '/products/suggestions',
      query: {'q': query},
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
  }

  Future<List<Category>> categories() async {
    final body = await _data('GET', '/categories');

    return listOf(body).map(Category.fromJson).toList(growable: false);
  }

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
  }) async => ChatMessage.fromJson(
    mapOf(
      await _data(
        'POST',
        '/conversations/$conversationId/messages',
        data: {'body': body, 'client_message_id': clientMessageId},
      ),
    ),
  );

  Future<ChatMessage> sendAttachment({
    required String conversationId,
    required PickedAttachment attachment,
    required String clientMessageId,
    String body = '',
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
}
