Map<String, dynamic> mapOf(dynamic value) => value is Map
    ? value.map((key, entry) => MapEntry(key.toString(), entry))
    : const <String, dynamic>{};

List<Map<String, dynamic>> listOf(dynamic value) => value is List
    ? value.whereType<Map>().map(mapOf).toList(growable: false)
    : const <Map<String, dynamic>>[];

String str(dynamic value, [String fallback = '']) =>
    value is String ? value : (value == null ? fallback : '$value');

int integer(dynamic value, [int fallback = 0]) => value is int
    ? value
    : value is num
    ? value.toInt()
    : int.tryParse('${value ?? ''}') ?? fallback;

double decimal(dynamic value, [double fallback = 0]) => value is num
    ? value.toDouble()
    : double.tryParse('${value ?? ''}') ?? fallback;

bool boolean(dynamic value, [bool fallback = false]) => value is bool
    ? value
    : value == 1 ||
          value == '1' ||
          value == 'true' ||
          (value == null && fallback);

class Account {
  const Account({
    required this.id,
    required this.name,
    required this.email,
    required this.accountType,
    required this.emailVerified,
    required this.isPlatformAdministrator,
  });

  final String id;
  final String name;
  final String email;
  final String accountType;
  final bool emailVerified;
  final bool isPlatformAdministrator;

  factory Account.fromJson(Map<String, dynamic> json) => Account(
    id: str(json['id']),
    name: str(json['name']),
    email: str(json['email']),
    accountType: str(json['account_type'], 'buyer'),
    emailVerified: boolean(json['email_verified']),
    isPlatformAdministrator: boolean(json['is_platform_administrator']),
  );
}

class Category {
  const Category({
    required this.id,
    required this.name,
    required this.slug,
    required this.productCount,
  });

  final String id;
  final String name;
  final String slug;
  final int productCount;

  factory Category.fromJson(Map<String, dynamic> json) => Category(
    id: str(json['id']),
    name: str(json['name']),
    slug: str(json['slug']),
    productCount: integer(json['product_count']),
  );
}

class StoreInfo {
  const StoreInfo({
    required this.id,
    required this.name,
    required this.slug,
    this.logoUrl,
    this.bannerUrl,
    this.city,
    this.description,
    this.productsCount,
    this.acceptsOnlinePayments = false,
  });

  final String id;
  final String name;
  final String slug;
  final String? logoUrl;
  final String? bannerUrl;
  final String? city;
  final String? description;
  final int? productsCount;
  final bool acceptsOnlinePayments;

  factory StoreInfo.fromJson(Map<String, dynamic> json) => StoreInfo(
    id: str(json['id']),
    name: str(json['name']),
    slug: str(json['slug']),
    logoUrl: json['logo_url'] as String?,
    bannerUrl: json['banner_url'] as String?,
    city: json['city'] as String?,
    description: json['description'] as String?,
    productsCount: json['products_count'] == null
        ? null
        : integer(json['products_count']),
    acceptsOnlinePayments: boolean(json['accepts_online_payments']),
  );
}

class ProductImage {
  const ProductImage({required this.url, this.color, this.position = 0});

  final String url;
  final String? color;
  final int position;

  factory ProductImage.fromJson(Map<String, dynamic> json) => ProductImage(
    url: str(json['url']),
    color: json['color'] as String?,
    position: integer(json['position']),
  );
}

class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.label,
    this.color,
    this.colorHex,
    this.size,
    this.price,
    this.sku,
    required this.stockQuantity,
    required this.inStock,
  });

  final String id;
  final String label;
  final String? color;
  final String? colorHex;
  final String? size;
  final String? price;
  final String? sku;
  final int stockQuantity;
  final bool inStock;

  factory ProductVariant.fromJson(Map<String, dynamic> json) => ProductVariant(
    id: str(json['id']),
    label: str(json['label']),
    color: json['color'] as String?,
    colorHex: json['color_hex'] as String?,
    size: json['size'] as String?,
    price: json['price'] as String?,
    sku: json['sku'] as String?,
    stockQuantity: integer(json['stock_quantity']),
    inStock: boolean(json['in_stock']),
  );
}

class ProductReview {
  const ProductReview({
    required this.rating,
    required this.author,
    required this.body,
    this.title = '',
    this.createdAt,
    this.verifiedPurchase = false,
  });

  final int rating;
  final String author;
  final String body;
  final String title;
  final String? createdAt;
  final bool verifiedPurchase;

  factory ProductReview.fromJson(Map<String, dynamic> json) => ProductReview(
    rating: integer(json['rating']),
    author: str(json['author']),
    body: str(json['body']),
    title: str(json['title']),
    createdAt: json['created_at'] as String?,
    verifiedPurchase: boolean(json['verified_purchase']),
  );
}

class ReviewPage {
  const ReviewPage({
    required this.reviews,
    required this.page,
    required this.lastPage,
  });

  final List<ProductReview> reviews;
  final int page;
  final int lastPage;
}

class ReviewSummary {
  const ReviewSummary({
    required this.count,
    required this.average,
    required this.distribution,
  });

  final int count;
  final double average;
  final Map<int, int> distribution;

  factory ReviewSummary.fromJson(Map<String, dynamic> json) => ReviewSummary(
    count: integer(json['count']),
    average: decimal(json['average']),
    distribution: mapOf(json['distribution'])
        .map((key, value) => MapEntry(int.tryParse(key) ?? 0, integer(value))),
  );
}

class Product {
  const Product({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    required this.effectivePrice,
    this.compareAtPrice,
    this.thumbnailUrl,
    this.brand,
    this.condition,
    this.description,
    this.sku,
    required this.inStock,
    required this.hasVariants,
    required this.requiresVariantSelection,
    required this.freeShipping,
    required this.stockQuantity,
    required this.soldQuantity,
    required this.ratingAverage,
    required this.ratingCount,
    this.store,
    this.category,
    this.images = const [],
    this.variants = const [],
    this.reviewSummary,
    this.related = const [],
    this.moreFromStore = const [],
  });

  final String id;
  final String name;
  final String slug;
  final String price;
  final String effectivePrice;
  final String? compareAtPrice;
  final String? thumbnailUrl;
  final String? brand;
  final String? condition;
  final String? description;
  final String? sku;
  final bool inStock;
  final bool hasVariants;
  final bool requiresVariantSelection;
  final bool freeShipping;
  final int stockQuantity;
  final int soldQuantity;
  final double ratingAverage;
  final int ratingCount;
  final StoreInfo? store;
  final Category? category;
  final List<ProductImage> images;
  final List<ProductVariant> variants;
  final ReviewSummary? reviewSummary;
  final List<Product> related;
  final List<Product> moreFromStore;

  factory Product.fromJson(Map<String, dynamic> json) {
    final rating = mapOf(json['rating']);
    final category = json['category'] == null
        ? null
        : Category.fromJson(mapOf(json['category']));

    return Product(
      id: str(json['id']),
      name: str(json['name']),
      slug: str(json['slug']),
      price: str(json['price']),
      effectivePrice: str(json['effective_price'], str(json['price'])),
      compareAtPrice: json['compare_at_price'] as String?,
      thumbnailUrl: json['thumbnail_url'] as String?,
      brand: json['brand'] as String?,
      condition: json['condition'] as String?,
      description: json['description'] as String?,
      sku: json['sku'] as String?,
      inStock: boolean(json['in_stock']),
      hasVariants: boolean(json['has_variants']),
      requiresVariantSelection: boolean(json['requires_variant_selection']),
      freeShipping: boolean(json['free_shipping']),
      stockQuantity: integer(json['stock_quantity']),
      soldQuantity: integer(json['sold_quantity']),
      ratingAverage: decimal(rating['average']),
      ratingCount: integer(rating['count']),
      store: json['store'] == null
          ? null
          : StoreInfo.fromJson(mapOf(json['store'])),
      category: category,
      images: listOf(json['images'])
          .map(ProductImage.fromJson)
          .toList(growable: false),
      variants: listOf(json['variants'])
          .map(ProductVariant.fromJson)
          .toList(growable: false),
      reviewSummary: json['review_summary'] == null
          ? null
          : ReviewSummary.fromJson(mapOf(json['review_summary'])),
      related: listOf(json['related_products'])
          .map(Product.fromJson)
          .toList(growable: false),
      moreFromStore: listOf(json['more_from_store'])
          .map(Product.fromJson)
          .toList(growable: false),
    );
  }
}

class ProductPage {
  const ProductPage({
    required this.products,
    required this.page,
    required this.lastPage,
    required this.total,
  });

  final List<Product> products;
  final int page;
  final int lastPage;
  final int total;

  bool get hasMore => page < lastPage;
}

class CartLine {
  const CartLine({
    required this.id,
    required this.productId,
    this.variantId,
    required this.quantity,
    required this.selected,
    required this.available,
    required this.unitPrice,
    required this.lineTotal,
    this.imageUrl,
    required this.productName,
    required this.productSlug,
    required this.storeName,
    required this.storeSlug,
    required this.hasVariants,
    this.variantLabel,
  });

  final String id;
  final String productId;
  final String? variantId;
  final int quantity;
  final bool selected;
  final bool available;
  final String unitPrice;
  final String lineTotal;
  final String? imageUrl;
  final String productName;
  final String productSlug;
  final String storeName;
  final String storeSlug;
  final bool hasVariants;
  final String? variantLabel;

  factory CartLine.fromJson(Map<String, dynamic> json) {
    final product = mapOf(json['product']);
    final store = mapOf(product['store']);
    final variant = mapOf(json['variant']);

    return CartLine(
      id: str(json['id']),
      productId: str(json['product_id']),
      variantId: json['product_variant_id'] as String?,
      quantity: integer(json['quantity']),
      selected: boolean(json['selected']),
      available: boolean(json['available']),
      unitPrice: str(json['unit_price']),
      lineTotal: str(json['line_total']),
      imageUrl: json['image_url'] as String?,
      productName: str(product['name']),
      productSlug: str(product['slug']),
      storeName: str(store['name']),
      storeSlug: str(store['slug']),
      hasVariants: boolean(product['has_variants']),
      variantLabel: variant.isEmpty ? null : variant['label'] as String?,
    );
  }
}

class Cart {
  const Cart({
    this.id,
    required this.lines,
    required this.selectedCount,
    required this.subtotal,
  });

  final String? id;
  final List<CartLine> lines;
  final int selectedCount;
  final String subtotal;

  static const empty = Cart(lines: [], selectedCount: 0, subtotal: '0.00');

  factory Cart.fromJson(Map<String, dynamic> json) => Cart(
    id: json['id'] as String?,
    lines: listOf(json['items']).map(CartLine.fromJson).toList(growable: false),
    selectedCount: integer(json['selected_count']),
    subtotal: str(json['subtotal'], '0.00'),
  );
}

class Address {
  const Address({
    this.id,
    required this.recipientName,
    required this.phone,
    required this.addressLine,
    required this.city,
    required this.province,
    required this.postalCode,
  });

  final String? id;
  final String recipientName;
  final String phone;
  final String addressLine;
  final String city;
  final String province;
  final String postalCode;

  factory Address.fromJson(Map<String, dynamic> json) => Address(
    id: json['id'] as String?,
    recipientName: str(json['recipient_name']),
    phone: str(json['phone']),
    addressLine: str(json['address_line']),
    city: str(json['city']),
    province: str(json['province']),
    postalCode: str(json['postal_code']),
  );

  Map<String, dynamic> toJson() => {
    'recipient_name': recipientName,
    'phone': phone,
    'address_line': addressLine,
    'city': city,
    'province': province,
    'postal_code': postalCode,
  };

  Address copyWith({
    String? id,
    String? recipientName,
    String? phone,
    String? addressLine,
    String? city,
    String? province,
    String? postalCode,
  }) => Address(
    id: id ?? this.id,
    recipientName: recipientName ?? this.recipientName,
    phone: phone ?? this.phone,
    addressLine: addressLine ?? this.addressLine,
    city: city ?? this.city,
    province: province ?? this.province,
    postalCode: postalCode ?? this.postalCode,
  );
}

class PaymentOption {
  const PaymentOption({required this.available, this.fee, this.reason});

  final bool available;
  final String? fee;
  final String? reason;

  factory PaymentOption.fromJson(Map<String, dynamic> json) => PaymentOption(
    available: boolean(json['available']),
    fee: json['fee'] as String?,
    reason: json['reason'] as String?,
  );
}

class StoreTotal {
  const StoreTotal({required this.name, required this.total});

  final String name;
  final String total;

  factory StoreTotal.fromJson(Map<String, dynamic> json) =>
      StoreTotal(name: str(json['name']), total: str(json['total']));
}

class CheckoutQuote {
  const CheckoutQuote({
    required this.lines,
    required this.subtotal,
    required this.storeTotals,
    required this.addresses,
    this.preferredAddress,
    required this.paymentOptions,
  });

  final List<CartLine> lines;
  final String subtotal;
  final List<StoreTotal> storeTotals;
  final List<Address> addresses;
  final Address? preferredAddress;
  final Map<String, PaymentOption> paymentOptions;

  factory CheckoutQuote.fromJson(Map<String, dynamic> json) => CheckoutQuote(
    lines: listOf(json['items']).map(CartLine.fromJson).toList(growable: false),
    subtotal: str(json['subtotal'], '0.00'),
    storeTotals: listOf(json['store_totals'])
        .map(StoreTotal.fromJson)
        .toList(growable: false),
    addresses: listOf(json['addresses'])
        .map(Address.fromJson)
        .toList(growable: false),
    preferredAddress: json['preferred_address'] == null
        ? null
        : Address.fromJson(mapOf(json['preferred_address'])),
    paymentOptions: mapOf(
      json['payment_methods'],
    ).map((key, value) => MapEntry(key, PaymentOption.fromJson(mapOf(value)))),
  );
}

class DeliveryInfo {
  const DeliveryInfo({
    required this.recipientName,
    required this.phone,
    required this.addressLine,
    required this.city,
    required this.province,
    required this.postalCode,
    this.note,
  });

  final String recipientName;
  final String phone;
  final String addressLine;
  final String city;
  final String province;
  final String postalCode;
  final String? note;

  factory DeliveryInfo.fromJson(Map<String, dynamic> json) => DeliveryInfo(
    recipientName: str(json['recipient_name']),
    phone: str(json['phone']),
    addressLine: str(json['address_line']),
    city: str(json['city']),
    province: str(json['province']),
    postalCode: str(json['postal_code']),
    note: json['note'] as String?,
  );
}

class PaymentInfo {
  const PaymentInfo({
    required this.method,
    required this.label,
    required this.status,
    required this.amount,
    required this.feeAmount,
  });

  final String method;
  final String label;
  final String status;
  final String amount;
  final String feeAmount;

  factory PaymentInfo.fromJson(Map<String, dynamic> json) => PaymentInfo(
    method: str(json['method']),
    label: str(json['label']),
    status: str(json['status']),
    amount: str(json['amount']),
    feeAmount: str(json['fee_amount']),
  );
}

class OrderLine {
  const OrderLine({
    required this.id,
    this.productId,
    required this.productName,
    this.sku,
    this.variantLabel,
    required this.unitPrice,
    required this.quantity,
    required this.lineTotal,
    this.imageUrl,
    this.reviewed = false,
  });

  final String id;
  final String? productId;
  final String productName;
  final String? sku;
  final String? variantLabel;
  final String unitPrice;
  final int quantity;
  final String lineTotal;
  final String? imageUrl;
  final bool reviewed;

  factory OrderLine.fromJson(Map<String, dynamic> json) => OrderLine(
    id: str(json['id']),
    productId: json['product_id'] as String?,
    productName: str(json['product_name']),
    sku: json['sku'] as String?,
    variantLabel: json['variant_label'] as String?,
    unitPrice: str(json['unit_price']),
    quantity: integer(json['quantity']),
    lineTotal: str(json['line_total']),
    imageUrl: json['image_url'] as String?,
    reviewed: boolean(json['reviewed']),
  );
}

class SellerOrder {
  const SellerOrder({
    required this.id,
    required this.status,
    required this.subtotal,
    required this.shippingAmount,
    required this.total,
    this.shippedAt,
    this.deliveredAt,
    this.codCollectedAt,
    required this.canConfirmReceipt,
    this.store,
    required this.lines,
    this.orderId,
    this.orderNumber,
    this.orderShortNumber,
    this.orderTotal,
    this.orderCreatedAt,
  });

  final String id;
  final String status;
  final String subtotal;
  final String shippingAmount;
  final String total;
  final String? shippedAt;
  final String? deliveredAt;
  final String? codCollectedAt;
  final bool canConfirmReceipt;
  final StoreInfo? store;
  final List<OrderLine> lines;
  final String? orderId;
  final String? orderNumber;
  final String? orderShortNumber;
  final String? orderTotal;
  final String? orderCreatedAt;

  factory SellerOrder.fromJson(Map<String, dynamic> json) {
    final order = mapOf(json['order']);

    return SellerOrder(
      id: str(json['id']),
      status: str(json['status']),
      subtotal: str(json['subtotal']),
      shippingAmount: str(json['shipping_amount']),
      total: str(json['total']),
      shippedAt: json['shipped_at'] as String?,
      deliveredAt: json['delivered_at'] as String?,
      codCollectedAt: json['cod_collected_at'] as String?,
      canConfirmReceipt: boolean(json['can_confirm_receipt']),
      store: json['store'] == null
          ? null
          : StoreInfo.fromJson(mapOf(json['store'])),
      lines: listOf(json['items'])
          .map(OrderLine.fromJson)
          .toList(growable: false),
      orderId: order['id'] as String?,
      orderNumber: order['number'] as String?,
      orderShortNumber: order['short_number'] as String?,
      orderTotal: order['total'] as String?,
      orderCreatedAt: order['created_at'] as String?,
    );
  }
}

class OrderDetail {
  const OrderDetail({
    required this.id,
    required this.number,
    required this.shortNumber,
    required this.status,
    required this.createdAt,
    required this.subtotal,
    required this.shippingAmount,
    required this.total,
    required this.delivery,
    this.payment,
    required this.sellerOrders,
    required this.canPay,
    required this.canConfirmReceipt,
  });

  final String id;
  final String number;
  final String shortNumber;
  final String status;
  final String createdAt;
  final String subtotal;
  final String shippingAmount;
  final String total;
  final DeliveryInfo delivery;
  final PaymentInfo? payment;
  final List<SellerOrder> sellerOrders;
  final bool canPay;
  final bool canConfirmReceipt;

  factory OrderDetail.fromJson(Map<String, dynamic> json) => OrderDetail(
    id: str(json['id']),
    number: str(json['number']),
    shortNumber: str(json['short_number']),
    status: str(json['status']),
    createdAt: str(json['created_at']),
    subtotal: str(json['subtotal']),
    shippingAmount: str(json['shipping_amount']),
    total: str(json['total']),
    delivery: DeliveryInfo.fromJson(mapOf(json['delivery'])),
    payment: json['payment'] == null
        ? null
        : PaymentInfo.fromJson(mapOf(json['payment'])),
    sellerOrders: listOf(json['seller_orders'])
        .map(SellerOrder.fromJson)
        .toList(growable: false),
    canPay: boolean(json['can_pay']),
    canConfirmReceipt: boolean(json['can_confirm_receipt']),
  );
}

class OrdersResult {
  const OrdersResult({required this.orders, required this.tabCounts});

  final List<SellerOrder> orders;
  final Map<String, int> tabCounts;

  factory OrdersResult.fromEnvelope(Map<String, dynamic> body) => OrdersResult(
    orders: listOf(body['data'])
        .map(SellerOrder.fromJson)
        .toList(growable: false),
    tabCounts: mapOf(body['tab_counts'])
        .map((key, value) => MapEntry(key, integer(value))),
  );
}

class PlacedOrder {
  const PlacedOrder({required this.order, this.paymentUrl, this.paymentError});

  final OrderDetail order;
  final String? paymentUrl;
  final String? paymentError;

  factory PlacedOrder.fromJson(Map<String, dynamic> json) => PlacedOrder(
    order: OrderDetail.fromJson(json),
    paymentUrl: json['payment_url'] as String?,
    paymentError: json['payment_error'] as String?,
  );
}

class ChatAttachment {
  const ChatAttachment({
    required this.id,
    required this.name,
    required this.mimeType,
    this.url,
    required this.isImage,
  });

  final String id;
  final String name;
  final String mimeType;
  final String? url;
  final bool isImage;

  factory ChatAttachment.fromJson(Map<String, dynamic> json) => ChatAttachment(
    id: str(json['id']),
    name: str(json['name']),
    mimeType: str(json['mime_type']),
    url: json['url'] as String?,
    isImage: boolean(json['is_image']),
  );
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.senderId,
    this.senderName,
    required this.isMine,
    required this.type,
    required this.body,
    this.clientMessageId,
    required this.createdAt,
    this.attachments = const [],
  });

  final String id;
  final String senderId;
  final String? senderName;
  final bool isMine;
  final String type;
  final String body;
  final String? clientMessageId;
  final String createdAt;
  final List<ChatAttachment> attachments;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => ChatMessage(
    id: str(json['id']),
    senderId: str(json['sender_id']),
    senderName: json['sender_name'] as String?,
    isMine: boolean(json['is_mine']),
    type: str(json['type'], 'text'),
    body: str(json['body']),
    clientMessageId: json['client_message_id'] as String?,
    createdAt: str(json['created_at']),
    attachments: listOf(json['attachments'])
        .map(ChatAttachment.fromJson)
        .toList(growable: false),
  );
}

class Conversation {
  const Conversation({
    required this.id,
    this.latestMessageAt,
    required this.unreadCount,
    this.store,
    this.productName,
    this.productSlug,
    this.productImageUrl,
    this.sellerOrderId,
    this.orderNumber,
    this.latestMessage,
  });

  final String id;
  final String? latestMessageAt;
  final int unreadCount;
  final StoreInfo? store;
  final String? productName;
  final String? productSlug;
  final String? productImageUrl;
  final String? sellerOrderId;
  final String? orderNumber;
  final ChatMessage? latestMessage;

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final product = mapOf(json['product']);
    final sellerOrder = mapOf(json['seller_order']);

    return Conversation(
      id: str(json['id']),
      latestMessageAt: json['latest_message_at'] as String?,
      unreadCount: integer(json['unread_count']),
      store: json['store'] == null
          ? null
          : StoreInfo.fromJson(mapOf(json['store'])),
      productName: product['name'] as String?,
      productSlug: product['slug'] as String?,
      productImageUrl: product['thumbnail_url'] as String?,
      sellerOrderId: sellerOrder['id'] as String?,
      orderNumber: sellerOrder['short_number'] as String?,
      latestMessage: json['latest_message'] == null
          ? null
          : ChatMessage.fromJson(mapOf(json['latest_message'])),
    );
  }
}
