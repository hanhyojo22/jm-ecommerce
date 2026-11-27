import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show visibleForTesting;
import 'package:intl/intl.dart';

import '../models/medication.dart';
import '../models/models.dart';
import '../models/store_branch.dart';
import 'api_exception.dart';
import 'attachment_picker.dart';

typedef TokenProvider = Future<String?> Function();

class ApiClient {
  ApiClient({
    required String baseUrl,
    required this.tokenProvider,
    @visibleForTesting HttpClientAdapter? httpClientAdapter,
  }) {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 25),
        headers: const {'Accept': 'application/json'},
      ),
    );
    if (httpClientAdapter != null) {
      _dio.httpClientAdapter = httpClientAdapter;
    }

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
        'GET' => await _getWithRetry(path, query, cancelToken),
        'POST' => await _dio.post(path, data: data, cancelToken: cancelToken),
        'PATCH' => await _dio.patch(path, data: data, cancelToken: cancelToken),
        'PUT' => await _dio.put(path, data: data, cancelToken: cancelToken),
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

  /// Mobile data networks silently drop idle pooled connections, so the first
  /// read after the app sits idle can fail even though the server is up; one
  /// retry on a fresh connection fixes that. Only reads retry: a repeated
  /// POST could place an order twice.
  Future<Response<dynamic>> _getWithRetry(
    String path,
    Map<String, dynamic>? query,
    CancelToken? cancelToken,
  ) async {
    try {
      return await _dio.get(
        path,
        queryParameters: query,
        cancelToken: cancelToken,
      );
    } on DioException catch (error) {
      final neverReachedServer =
          error.response == null &&
          error.type != DioExceptionType.cancel &&
          error.type != DioExceptionType.badCertificate;
      if (!neverReachedServer) {
        rethrow;
      }

      return _dio.get(path, queryParameters: query, cancelToken: cancelToken);
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

  /// Opting out deletes every plan, dose log, family profile and caregiver link.
  Future<void> setMedicationConsent(bool enabled) async =>
      _raw(enabled ? 'PUT' : 'DELETE', '/me/medication-consent');

  Future<List<MedicationPlan>> medicationPlans() async =>
      listOf(await _data('GET', '/medication-plans'))
          .map(MedicationPlan.fromJson)
          .toList();

  Future<List<MedicationSuggestion>> medicationSuggestions() async =>
      listOf(await _data('GET', '/medication-plans/suggestions'))
          .map(MedicationSuggestion.fromJson)
          .toList();

  Future<MedicationPlan> startMedicationPlan(Map<String, dynamic> body) async =>
      MedicationPlan.fromJson(
        mapOf(await _data('POST', '/medication-plans', data: body)),
      );

  Future<MedicationPlan> updateMedicationPlan(
    String planId,
    Map<String, dynamic> changes,
  ) async => MedicationPlan.fromJson(
    mapOf(await _data('PATCH', '/medication-plans/$planId', data: changes)),
  );

  Future<void> deleteMedicationPlan(String planId) async =>
      _raw('DELETE', '/medication-plans/$planId');

  /// Each log carries a client_uuid, so a resent log is never counted twice.
  /// [warning] is 'too_soon' or 'daily_max' for an as-needed medicine.
  Future<({MedicationPlan plan, String? warning})> logDoses(
    String planId,
    List<Map<String, dynamic>> logs,
  ) async {
    final body = mapOf(
      await _raw(
        'POST',
        '/medication-plans/$planId/doses',
        data: {'logs': logs},
      ),
    );
    return (
      plan: MedicationPlan.fromJson(mapOf(body['plan'])),
      warning: body['warning'] as String?,
    );
  }

  Future<MedicationPlan> countMedication(
    String planId,
    double unitsLeft,
  ) async => MedicationPlan.fromJson(
    mapOf(
      await _data(
        'POST',
        '/medication-plans/$planId/count',
        data: {'units_left': unitsLeft},
      ),
    ),
  );

  Future<List<DoseHistory>> medicationHistory({
    String? profileId,
    int days = 30,
  }) async => listOf(
    await _data(
      'GET',
      '/medication-history',
      query: {'days': days, 'care_profile_id': ?profileId},
    ),
  ).map(DoseHistory.fromJson).toList();

  Future<List<CareProfile>> careProfiles() async =>
      listOf(await _data('GET', '/care-profiles'))
          .map(CareProfile.fromJson)
          .toList();

  Future<CareProfile> saveCareProfile({
    String? id,
    required String name,
    String? relation,
  }) async => CareProfile.fromJson(
    mapOf(
      await _data(
        id == null ? 'POST' : 'PATCH',
        id == null ? '/care-profiles' : '/care-profiles/$id',
        data: {'name': name, 'relation': relation},
      ),
    ),
  );

  Future<void> deleteCareProfile(String id) async =>
      _raw('DELETE', '/care-profiles/$id');

  Future<Caregivers> caregivers() async =>
      Caregivers.fromJson(mapOf(await _data('GET', '/caregivers')));

  Future<void> inviteCaregiver({
    required String email,
    bool shareMedicineNames = false,
  }) async => _raw(
    'POST',
    '/caregivers',
    data: {'email': email, 'share_medicine_names': shareMedicineNames},
  );

  Future<void> acceptCaregiver(String id) async =>
      _raw('POST', '/caregivers/$id/accept');

  Future<void> removeCaregiver(String id) async =>
      _raw('DELETE', '/caregivers/$id');

  Future<Account> updateCartReminders(bool enabled) async => Account.fromJson(
    mapOf(await _data('PATCH', '/me', data: {'cart_reminders': enabled})),
  );

  /// Saves the profile. [birthDate] is sent as a plain YYYY-MM-DD date.
  Future<Account> updateProfile({
    required String name,
    required DateTime birthDate,
    required String phone,
    String? sex,
  }) async => Account.fromJson(
    mapOf(
      await _data(
        'PATCH',
        '/me',
        data: {
          'name': name,
          'birth_date': DateFormat('yyyy-MM-dd').format(birthDate),
          'phone': phone,
          'sex': sex,
        },
      ),
    ),
  );

  Future<void> deleteAccount() async {
    await _raw('DELETE', '/me');
  }

  Future<ProductPage> products({
    String? query,
    String? category,
    String? seller,
    String? sort,
    bool onSale = false,
    bool noPrescription = false,
    int page = 1,
    int perPage = 20,
    String? branch,
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
            if (onSale) 'on_sale': 1,
            if (noPrescription) 'no_prescription': 1,
            'branch': ?branch,
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
        searchIngredient: mapOf(body['search'])['ingredient'] as String?,
      );
    } finally {
      if (identical(_productSearchCancelToken, cancelToken)) {
        _productSearchCancelToken = null;
      }
    }
  }

  /// Names, categories and products for what the shopper has typed so far. Each part is
  /// optional, so an older server that only sends `items` still works.
  Future<SearchSuggestions> searchSuggestions(
    String query, {
    String? branch,
  }) async {
    _productSuggestionsCancelToken?.cancel('Replaced by newer suggestions.');
    final cancelToken = CancelToken();
    _productSuggestionsCancelToken = cancelToken;

    try {
      final body = mapOf(
        await _raw(
          'GET',
          '/products/suggestions',
          query: {'q': query, 'branch': ?branch},
          cancelToken: cancelToken,
        ),
      );

      return SearchSuggestions(
        names: listOf(body['names'])
            .map(
              (json) =>
                  (name: str(json['name']), count: integer(json['count'])),
            )
            .where((name) => name.name.isNotEmpty)
            .toList(growable: false),
        categories: listOf(body['categories'])
            .map(
              (json) => (
                slug: str(json['slug']),
                name: str(json['name']),
                count: integer(json['count']),
              ),
            )
            .toList(growable: false),
        products: listOf(body['items'])
            .map(
              (json) => (
                product: Product(
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
                  genericName: json['generic_name'] as String?,
                  dosageForm: json['dosage_form'] as String?,
                  strength: json['strength'] as String?,
                  requiresPrescription: boolean(json['requires_prescription']),
                  store: StoreInfo(
                    id: '',
                    name: str(json['store_name']),
                    slug: str(json['store_slug']),
                  ),
                ),
                atBranch: json['at_branch'] as bool?,
              ),
            )
            .toList(growable: false),
        total: body['total'] is num ? integer(body['total']) : null,
      );
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

  Future<DiscussionPage> productDiscussions({
    required String store,
    required String product,
    int page = 1,
  }) async {
    final body = mapOf(
      await _raw(
        'GET',
        '/stores/${Uri.encodeComponent(store)}/products/${Uri.encodeComponent(product)}/discussions',
        query: {'page': page},
      ),
    );
    final meta = mapOf(body['meta']);
    return DiscussionPage(
      discussions: listOf(body['data'])
          .map(ProductDiscussion.fromJson)
          .toList(growable: false),
      page: integer(meta['current_page'], page),
      lastPage: integer(meta['last_page'], page),
    );
  }

  Future<ProductDiscussion> postProductDiscussion({
    required String store,
    required String product,
    required String body,
    String? parentId,
  }) async => ProductDiscussion.fromJson(
    mapOf(
      await _data(
        'POST',
        '/stores/${Uri.encodeComponent(store)}/products/${Uri.encodeComponent(product)}/discussions',
        data: {'body': body, 'parent_id': ?parentId},
      ),
    ),
  );

  Future<String?> eligibleReviewOrderItem(String productId) async {
    final data = mapOf(
      await _data('GET', '/products/$productId/review-eligibility'),
    );
    return data['order_item_id'] as String?;
  }

  Future<StoreInfo> store(String slug) async =>
      StoreInfo.fromJson(mapOf(await _data('GET', '/stores/$slug')));

  Future<Product> product({
    required String store,
    required String slug,
    String? branch,
  }) async => Product.fromJson(
    mapOf(
      await _data(
        'GET',
        '/stores/$store/products/$slug',
        query: {'branch': ?branch},
      ),
    ),
  );

  Future<Cart> cart() async =>
      Cart.fromJson(mapOf(await _data('GET', '/cart')));

  /// [buyNow] also makes this line the only selected item, in the same request.
  Future<Cart> addToCart({
    required String productId,
    int quantity = 1,
    String? variantId,
    bool buyNow = false,
    String? branch,
  }) async => Cart.fromJson(
    mapOf(
      await _data(
        'POST',
        '/cart/items',
        data: {
          'product_id': productId,
          'quantity': quantity,
          'variant_id': ?variantId,
          'branch': ?branch,
          if (buyNow) 'buy_now': true,
        },
      ),
    ),
  );

  /// Puts a past order's medicines back in the cart; [skipped] names what the branch lacks.
  Future<({Cart cart, List<String> skipped})> reorder(
    String orderId, {
    String? branch,
  }) async {
    final body = mapOf(
      await _raw('POST', '/cart/reorder/$orderId', data: {'branch': ?branch}),
    );
    return (
      cart: Cart.fromJson(mapOf(body['data'])),
      skipped: listOf(body['skipped']).map((name) => '$name').toList(),
    );
  }

  /// Moves the cart to [branchId]; lines that branch cannot fill come back unavailable.
  Future<Cart> setCartBranch(String branchId) async => Cart.fromJson(
    mapOf(await _data('PATCH', '/cart/branch', data: {'branch': branchId})),
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

  /// Every prescription page and the [discountDocument] photo go up with the
  /// order as multipart form data; a delivery needs the
  /// [deliveryLatitude]/[deliveryLongitude] pin.
  Future<PlacedOrder> placeOrder({
    required Address address,
    required String paymentMethod,
    required String clientRequestId,
    String fulfillmentMethod = 'delivery',
    double? deliveryLatitude,
    double? deliveryLongitude,
    List<PickedAttachment> prescriptions = const [],
    ({String type, String idNumber, String name})? discountCard,
    String? discountCardId,
    bool saveDiscountCard = false,
    PickedAttachment? discountDocument,
    String? note,
    String? successUrl,
    String? cancelUrl,
  }) async {
    final fields = <String, dynamic>{
      ...address.toJson(),
      'discount_card_id': ?discountCardId,
      if (discountCard != null)
        'discount_card': {
          'type': discountCard.type,
          'id_number': discountCard.idNumber,
          'name': discountCard.name,
        },
      // 1, not true: with a prescription this goes as multipart, where true becomes "true" and fails Laravel's boolean rule.
      if (saveDiscountCard) 'save_discount_card': 1,
      if (note != null && note.trim().isNotEmpty) 'delivery_note': note.trim(),
      'fulfillment_method': fulfillmentMethod,
      'delivery_latitude': ?deliveryLatitude,
      'delivery_longitude': ?deliveryLongitude,
      'payment_method': paymentMethod,
      'client_request_id': clientRequestId,
      'success_url': ?successUrl,
      'cancel_url': ?cancelUrl,
    };
    final data = await _data(
      'POST',
      '/checkout',
      data: prescriptions.isEmpty && discountDocument == null
          ? fields
          : FormData.fromMap({
              ...fields,
              for (final (index, page) in prescriptions.indexed)
                'prescriptions[$index]': MultipartFile.fromBytes(
                  page.bytes,
                  filename: page.name,
                  contentType: DioMediaType.parse(page.mimeType),
                ),
              if (discountDocument != null)
                'discount_document': MultipartFile.fromBytes(
                  discountDocument.bytes,
                  filename: discountDocument.name,
                  contentType: DioMediaType.parse(discountDocument.mimeType),
                ),
            }),
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

  Future<void> openOrderIssue(
    String sellerOrderId,
    String type,
    String details,
  ) async {
    await _data(
      'POST',
      '/orders/seller-orders/$sellerOrderId/issues',
      data: {'type': type, 'details': details},
    );
  }

  Future<Map<String, dynamic>> returnRequest(String id) async =>
      mapOf(await _data('GET', '/returns/$id'));

  Future<Map<String, dynamic>> openReturnRequest(
    String sellerOrderId, {
    required String reason,
    required String remedy,
    required String details,
    required Map<String, int> quantities,
    List<PickedAttachment> photos = const [],
  }) async {
    final items = quantities.entries.toList();
    final form = FormData()
      ..fields.addAll([
        MapEntry('reason', reason),
        MapEntry('requested_remedy', remedy),
        MapEntry('details', details),
        for (final (index, item) in items.indexed) ...[
          MapEntry('items[$index][order_item_id]', item.key),
          MapEntry('items[$index][quantity]', '${item.value}'),
        ],
      ])
      ..files.addAll([
        for (final photo in photos)
          MapEntry(
            'photos[]',
            MultipartFile.fromBytes(
              photo.bytes,
              filename: photo.name,
              contentType: DioMediaType.parse(photo.mimeType),
            ),
          ),
      ]);
    return mapOf(
      await _data(
        'POST',
        '/orders/seller-orders/$sellerOrderId/returns',
        data: form,
      ),
    );
  }

  Future<Map<String, dynamic>> updateReturnRequest(
    String id,
    String action, {
    String? note,
    PickedAttachment? proof,
  }) async {
    final fields = <String, dynamic>{'action': action, 'note': ?note};
    return mapOf(
      await _data(
        proof == null ? 'PATCH' : 'POST',
        '/returns/$id',
        data: proof == null
            ? fields
            : FormData.fromMap({
                ...fields,
                '_method': 'PATCH',
                'proof': MultipartFile.fromBytes(
                  proof.bytes,
                  filename: proof.name,
                  contentType: DioMediaType.parse(proof.mimeType),
                ),
              }),
      ),
    );
  }

  /// Sends clearer prescription pages after a pharmacist asked for them.
  Future<OrderDetail> resubmitPrescription(
    String orderId,
    List<PickedAttachment> prescriptions,
  ) async => OrderDetail.fromJson(
    mapOf(
      await _data(
        'POST',
        '/orders/$orderId/prescription',
        data: FormData.fromMap({
          for (final (index, page) in prescriptions.indexed)
            'prescriptions[$index]': MultipartFile.fromBytes(
              page.bytes,
              filename: page.name,
              contentType: DioMediaType.parse(page.mimeType),
            ),
        }),
      ),
    ),
  );

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

  /// ULIDs of the open branches that have [productId] on hand.
  Future<Set<String>> branchesWithStock(String productId) async => {
    for (final branch in listOf(
      await _data('GET', '/products/$productId/availability'),
    ))
      str(branch['id']),
  };

  /// The signed-in buyer's recent medicines that [branch] has on hand.
  Future<List<Product>> boughtBefore(String branch) async => listOf(
    await _data('GET', '/products/bought-before', query: {'branch': branch}),
  ).map(Product.fromJson).toList(growable: false);

  /// The branch's best sellers it has on hand, without prescription medicines.
  Future<List<Product>> bestSellers(String branch) async => listOf(
    await _data('GET', '/products/best-sellers', query: {'branch': branch}),
  ).map(Product.fromJson).toList(growable: false);

  /// The signed-in buyer's wishlist; with a [branch], stock is that branch's.
  Future<List<Product>> savedProducts({String? branch}) async => listOf(
    await _data(
      'GET',
      '/saved-products',
      query: {if (branch != null) 'branch': branch},
    ),
  ).map(Product.fromJson).toList(growable: false);

  Future<void> saveProduct(String productId) =>
      _raw('POST', '/saved-products/$productId');

  Future<void> unsaveProduct(String productId) =>
      _raw('DELETE', '/saved-products/$productId');

  Future<List<StoreBranch>> storeLocations() async =>
      listOf(await _data('GET', '/store-locations'))
          .map(StoreBranch.fromJson)
          .toList(growable: false);

  Future<List<Address>> addresses() async {
    final body = await _data('GET', '/delivery-addresses');

    return listOf(body).map(Address.fromJson).toList(growable: false);
  }

  /// Photon (OpenStreetMap data) is called from the phone, not through our
  /// server: it refuses Render's shared servers, and it is built for apps
  /// searching as the customer types.
  final Dio _photon = Dio(
    BaseOptions(
      baseUrl: 'https://photon.komoot.io',
      connectTimeout: const Duration(seconds: 8),
      receiveTimeout: const Duration(seconds: 8),
      // Photon answers Dart's default "Dart/x (dart:io)" agent with 403.
      headers: const {'User-Agent': 'EverydayPharmacy/1.0 (Android app)'},
    ),
  );

  Future<List<Map<String, dynamic>>> _photonFeatures(
    String path,
    Map<String, dynamic> query,
  ) async {
    try {
      final response = await _photon.get(
        path,
        queryParameters: {...query, 'lang': 'en'},
      );
      return listOf(mapOf(response.data)['features']);
    } on DioException {
      throw const ApiException(
        code: 'address_lookup_failed',
        message: 'We could not look up your address. Type it in instead.',
      );
    }
  }

  /// The nearest street to a spot, with its barangay, city and postal code.
  Future<Map<String, dynamic>> lookupAddress(
    double latitude,
    double longitude,
  ) async {
    final features = await _photonFeatures('/reverse', {
      'lat': latitude,
      'lon': longitude,
      'layer': 'street',
      'limit': 1,
    });
    if (features.isEmpty) {
      throw const ApiException(
        code: 'address_not_found',
        message: 'No street address was found here. Type it in instead.',
      );
    }
    return photonAddressFields(features.first);
  }

  /// Streets, barangays and buildings in the Philippines matching what the
  /// customer typed (3+ characters), each with a `label` to show.
  Future<List<Map<String, dynamic>>> searchAddresses(String query) async {
    final features = await _photonFeatures('/api', {
      'q': query,
      'limit': 8,
      'bbox': '116.9,4.5,126.7,21.2',
    });
    final labels = <String>{};
    return [
      for (final fields in features.map(photonAddressFields))
        if (fields['label'] != '' && labels.add(fields['label'])) fields,
    ];
  }

  /// Every province with its cities and municipalities, for the address
  /// dropdowns.
  Future<Map<String, List<String>>> provinces() async => {
    for (final province in listOf(await _data('GET', '/locations')))
      '${province['name']}': [
        for (final city in province['cities'] as List? ?? const []) '$city',
      ],
  };

  /// The barangays of one city, matched however its name is written.
  Future<List<String>> barangays(String province, String city) async {
    final names = await _data(
      'GET',
      '/locations/barangays',
      query: {'province': province, 'city': city},
    );

    return [for (final name in names as List? ?? const []) '$name'];
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

  Future<List<DiscountCard>> discountCards() async {
    final body = await _data('GET', '/discount-cards');

    return listOf(body).map(DiscountCard.fromJson).toList(growable: false);
  }

  Future<void> deleteDiscountCard(String id) async {
    await _raw('DELETE', '/discount-cards/$id');
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

  /// [branch] sends a product question to the branch the customer is shopping from.
  Future<Conversation> startConversation({
    String? productId,
    String? sellerOrderId,
    String? branch,
  }) async {
    final data = await _data(
      'POST',
      '/conversations/start',
      data: {
        'product_id': ?productId,
        'seller_order_id': ?sellerOrderId,
        'branch': ?branch,
      },
    );

    return Conversation.fromJson(mapOf(data));
  }

  Future<void> reportMessage({
    required String conversationId,
    required String messageId,
    required String reason,
  }) async {
    await _data(
      'POST',
      '/conversations/$conversationId/messages/$messageId/report',
      data: {'reason': reason},
    );
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
    String? replyToId,
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
          'reply_to_id': ?replyToId,
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

  Future<ProductReview> publishProductReview({
    required String orderItemId,
    required int rating,
    required String body,
  }) async => ProductReview.fromJson(
    mapOf(
      await _data(
        'POST',
        '/order-items/$orderItemId/review',
        data: {'rating': rating, if (body.isNotEmpty) 'body': body},
      ),
    ),
  );

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
      data: {'token': token, 'platform': 'android', 'rich_notifications': true},
    );
  }

  Future<void> unregisterDeviceToken(String token) async {
    await _raw('DELETE', '/device-tokens', data: {'token': token});
  }
}

/// A Photon place as the address form's fields: building and street on the
/// address line, the district or locality as the barangay. OpenStreetMap gives the Philippines regions, not
/// provinces, so province stays empty for the customer to fill in.
Map<String, dynamic> photonAddressFields(Map<String, dynamic> feature) {
  final place = mapOf(feature['properties']);
  String? text(String key) {
    final value = place[key];
    return value is String && value.trim().isNotEmpty ? value.trim() : null;
  }

  final kind = text('osm_key') ?? '';
  final streetName = kind == 'highway' ? text('name') : text('street');
  final street = streetName == null
      ? null
      : [text('housenumber'), streetName].whereType<String>().join(' ');
  final building = kind == 'highway' || kind == 'place' ? null : text('name');
  final barangay =
      text('district') ??
      text('locality') ??
      (kind == 'place' ? text('name') : null) ??
      '';
  final line = <String>[];
  for (final part in [building, street]) {
    if (part != null && !line.contains(part)) {
      line.add(part);
    }
  }
  final addressLine = line.join(', ');
  final city = text('city') ?? text('county') ?? '';
  final postcode = text('postcode') ?? '';
  final geometry = mapOf(feature['geometry']);
  final coordinates = geometry['coordinates'];
  double? latitude;
  double? longitude;
  if (geometry['type'] == 'Point' &&
      coordinates is List &&
      coordinates.length >= 2 &&
      coordinates[0] is num &&
      coordinates[1] is num) {
    final lat = (coordinates[1] as num).toDouble();
    final lng = (coordinates[0] as num).toDouble();
    if (lat.isFinite && lng.isFinite && lat.abs() <= 90 && lng.abs() <= 180) {
      latitude = lat;
      longitude = lng;
    }
  }

  return {
    'label': [
      addressLine,
      barangay,
      city,
    ].where((part) => part.isNotEmpty).toSet().join(', '),
    'address_line': addressLine,
    'barangay': barangay,
    'city': city,
    'province': '',
    'postal_code': RegExp(r'^\d{4}$').hasMatch(postcode) ? postcode : '',
    if (latitude != null && longitude != null) ...{
      'latitude': latitude,
      'longitude': longitude,
    },
  };
}
