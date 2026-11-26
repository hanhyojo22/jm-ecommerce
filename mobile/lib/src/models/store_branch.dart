import 'models.dart';

/// A branch that has set a map pin; [id] is its slug and [branchId] the ULID
/// the catalog, cart and checkout APIs take as `branch`.
class StoreBranch {
  const StoreBranch({
    required this.id,
    this.branchId = '',
    required this.name,
    required this.address,
    required this.latitude,
    required this.longitude,
    this.description = '',
    this.addressLine = '',
    this.barangay = '',
    this.city = '',
    this.province = '',
    this.postalCode = '',
    this.phone = '',
    this.openingTime,
    this.closingTime,
    this.maxDeliveryRadiusKm,
    this.deliveryFee,
    this.freeDeliveryMinimum,
    this.isOpen = true,
    this.open24Hours = false,
    this.nextOpeningAt,
  });

  factory StoreBranch.fromJson(Map<String, dynamic> json) {
    final city = str(json['city']);
    final province = str(json['province']);
    return StoreBranch(
      id: str(json['slug']),
      branchId: str(json['id']),
      name: str(json['name']),
      address: [city, province].where((part) => part.isNotEmpty).join(', '),
      latitude: decimal(json['latitude']),
      longitude: decimal(json['longitude']),
      description: str(json['description']),
      addressLine: str(json['address_line']),
      barangay: str(json['barangay']),
      city: city,
      province: province,
      postalCode: str(json['postal_code']),
      phone: str(json['phone']),
      openingTime: json['opening_time'] as String?,
      closingTime: json['closing_time'] as String?,
      maxDeliveryRadiusKm: json['delivery_radius_km'] is num
          ? (json['delivery_radius_km'] as num).toDouble()
          : null,
      deliveryFee: json['delivery_fee'] as String?,
      freeDeliveryMinimum: json['free_delivery_minimum'] as String?,
      isOpen: json['is_open'] != false,
      open24Hours: json['open_24_hours'] == true,
      nextOpeningAt: DateTime.tryParse(str(json['next_opening_at']))?.toLocal(),
    );
  }

  final String id;
  final String branchId;
  final String name;
  final String address;
  final double latitude;
  final double longitude;
  final String description;
  final String addressLine;
  final String barangay;
  final String city;
  final String province;
  final String postalCode;
  final String phone;
  final String? openingTime;
  final String? closingTime;

  /// Null when the seller has not set a delivery radius.
  final double? maxDeliveryRadiusKm;

  /// Flat company delivery fee as a decimal string; null when not sent.
  final String? deliveryFee;

  /// Orders at or above this subtotal deliver free; null when there is no such offer.
  final String? freeDeliveryMinimum;

  /// As of when the branch list loaded; checkout asks the server again.
  final bool isOpen;
  final bool open24Hours;
  final DateTime? nextOpeningAt;

  /// Strips the leading brand prefix when followed by a branch location name.
  String get shortName {
    const brand = 'Everyday Pharmacy ';
    return name.startsWith(brand) && name.length > brand.length
        ? name.substring(brand.length)
        : name;
  }

  /// Most detailed street-and-city address available from the API payload.
  String get fullAddress {
    final parts = [
      addressLine,
      barangay,
      city,
      province,
      postalCode,
    ].map((part) => part.trim()).where((part) => part.isNotEmpty).toList();
    return parts.isEmpty ? address : parts.join(', ');
  }

  String get formattedCoordinates {
    final latDir = latitude >= 0 ? 'N' : 'S';
    final lngDir = longitude >= 0 ? 'E' : 'W';
    return '${latitude.abs().toStringAsFixed(4)}° $latDir, '
        '${longitude.abs().toStringAsFixed(4)}° $lngDir';
  }

  String? get formattedClosingTime => _formatClock(closingTime);

  String? get formattedHoursRange {
    if (open24Hours) {
      return 'Open 24 hours';
    }
    final open = _formatClock(openingTime);
    final close = _formatClock(closingTime);
    if (open == null || close == null) {
      return null;
    }
    return '$open – $close';
  }

  static String? _formatClock(String? raw) {
    if (raw == null) {
      return null;
    }
    final parts = raw.trim().split(':');
    if (parts.length < 2) {
      return null;
    }
    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null ||
        minute == null ||
        hour < 0 ||
        hour > 23 ||
        minute < 0 ||
        minute > 59) {
      return null;
    }
    final period = hour >= 12 ? 'PM' : 'AM';
    final h12 = hour % 12 == 0 ? 12 : hour % 12;
    return '$h12:${minute.toString().padLeft(2, '0')} $period';
  }
}

/// A branch with its distance from the user; [distanceKm] is null when the
/// user's location is unknown.
class BranchDistance {
  const BranchDistance(this.branch, this.distanceKm);

  final StoreBranch branch;
  final double? distanceKm;

  int? get estimatedDriveMinutes =>
      distanceKm == null ? null : (distanceKm! / 22 * 60).round().clamp(2, 180);

  int? get estimatedWalkMinutes => distanceKm == null
      ? null
      : (distanceKm! / 4.8 * 60).round().clamp(3, 300);

  bool get isWithinDeliveryRadius {
    final radius = branch.maxDeliveryRadiusKm;
    return distanceKm != null && radius != null && distanceKm! <= radius;
  }

  /// Only true when both the distance and the seller's radius are known.
  bool get isOutOfDeliveryRange {
    final radius = branch.maxDeliveryRadiusKm;
    return distanceKm != null && radius != null && distanceKm! > radius;
  }
}
