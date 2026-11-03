import 'package:intl/intl.dart';

final _peso = NumberFormat.currency(
  locale: 'en_PH',
  symbol: '₱',
  decimalDigits: 2,
);

String money(String? value) => _peso.format(double.tryParse(value ?? '') ?? 0);

/// Adds two decimal money strings without floating-point rounding.
String addMoney(String a, String b) {
  final cents = _minorUnits(a) + _minorUnits(b);
  final sign = cents < 0 ? '-' : '';
  final absolute = cents.abs();

  return '$sign${absolute ~/ 100}.${(absolute % 100).toString().padLeft(2, '0')}';
}

int _minorUnits(String value) {
  final cleaned = value.trim().replaceAll(',', '');

  if (cleaned.isEmpty) {
    return 0;
  }

  final negative = cleaned.startsWith('-');
  final unsigned = negative ? cleaned.substring(1) : cleaned;
  final parts = unsigned.split('.');
  final whole = int.tryParse(parts.first) ?? 0;
  final fraction = parts.length > 1
      ? parts[1].padRight(2, '0').substring(0, 2)
      : '00';
  final cents = whole * 100 + (int.tryParse(fraction) ?? 0);

  return negative ? -cents : cents;
}

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
