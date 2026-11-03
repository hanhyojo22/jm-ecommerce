import 'package:intl/intl.dart';

final _peso = NumberFormat.currency(
  locale: 'en_PH',
  symbol: '₱',
  decimalDigits: 2,
);

String money(String? value) => _peso.format(double.tryParse(value ?? '') ?? 0);

String shortDate(String? iso) {
  final date = DateTime.tryParse(iso ?? '')?.toLocal();

  return date == null ? '' : DateFormat('MMM d, yyyy').format(date);
}

String dateTime(String? iso) {
  final date = DateTime.tryParse(iso ?? '')?.toLocal();

  return date == null ? '' : DateFormat('MMM d · h:mm a').format(date);
}

String timeAgo(String? iso) {
  final date = DateTime.tryParse(iso ?? '')?.toLocal();

  if (date == null) {
    return '';
  }

  final delta = DateTime.now().difference(date);

  if (delta.inMinutes < 1) {
    return 'just now';
  }
  if (delta.inHours < 1) {
    return '${delta.inMinutes}m ago';
  }
  if (delta.inDays < 1) {
    return '${delta.inHours}h ago';
  }
  if (delta.inDays < 7) {
    return '${delta.inDays}d ago';
  }

  return shortDate(iso);
}

String statusLabel(String status) => switch (status) {
  'pending' => 'Pending',
  'confirmed' => 'Confirmed',
  'processing' => 'Preparing',
  'shipped' => 'On the way',
  'delivered' => 'Delivered',
  'cancelled' => 'Cancelled',
  'open' => 'Open',
  'awaiting_seller' => 'Waiting for store',
  'offered' => 'Offer received',
  'rejected' => 'Rejected',
  'awaiting_return' => 'Return the item',
  'awaiting_receipt' => 'Return in transit',
  'awaiting_fulfillment' => 'Store is preparing',
  'awaiting_confirmation' => 'Confirm the outcome',
  'appealed' => 'With support',
  'completed' => 'Completed',
  'denied' => 'Denied',
  'closed' => 'Closed',
  _ => status.replaceAll('_', ' '),
};

String initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2);

  return parts.map((part) => part[0].toUpperCase()).join();
}
