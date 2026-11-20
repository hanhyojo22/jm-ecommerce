import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/attachment_picker.dart';
import '../core/discount_id_reader.dart';
import '../models/models.dart';
import 'auth_providers.dart';

final checkoutQuoteProvider = FutureProvider.autoDispose<CheckoutQuote>(
  (ref) => ref.watch(apiClientProvider).checkoutQuote(),
);

/// Picks the prescription photo or PDF; tests swap in a fake.
final attachmentPickerProvider = Provider<AttachmentPicker>(
  (ref) => const FilePickerAttachmentPicker(),
);

/// Reads the SC/PWD ID photo on the phone to fill in its number and name; tests swap in a fake.
final discountIdReaderProvider = Provider<DiscountIdReader>(
  (ref) => const MlKitDiscountIdReader(),
);
