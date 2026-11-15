import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/attachment_picker.dart';
import '../models/models.dart';
import 'auth_providers.dart';

final checkoutQuoteProvider = FutureProvider.autoDispose<CheckoutQuote>(
  (ref) => ref.watch(apiClientProvider).checkoutQuote(),
);

/// Picks the prescription photo or PDF; tests swap in a fake.
final attachmentPickerProvider = Provider<AttachmentPicker>(
  (ref) => const FilePickerAttachmentPicker(),
);
