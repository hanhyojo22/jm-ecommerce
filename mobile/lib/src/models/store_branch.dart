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
    this.maxDeliveryRadiusKm,
    this.deliveryFee,
    this.freeDeliveryMinimum,
    this.isOpen = true,
    this.open24Hours = false,
    this.nextOpeningAt,
    this.prepMinutes,
  });

  factory StoreBranch.fromJson(Map<String, dynamic> json) => StoreBranch(
    id: str(json['slug']),
    branchId: str(json['id']),
    name: str(json['name']),
    address: [
      str(json['city']),
      str(json['province']),
    ].where((part) => part.isNotEmpty).join(', '),
    latitude: decimal(json['latitude']),
    longitude: decimal(json['longitude']),
    maxDeliveryRadiusKm: json['delivery_radius_km'] is num
        ? (json['delivery_radius_km'] as num).toDouble()
        : null,
    deliveryFee: json['delivery_fee'] as String?,
    freeDeliveryMinimum: json['free_delivery_minimum'] as String?,
    isOpen: json['is_open'] != false,
    open24Hours: json['open_24_hours'] == true,
    nextOpeningAt: DateTime.tryParse(str(json['next_opening_at']))?.toLocal(),
    prepMinutes: json['prep_minutes'] is num
        ? (json['prep_minutes'] as num).toInt()
        : null,
  );

  final String id;
  final String branchId;
  final String name;
  final String address;
  final double latitude;
  final double longitude;

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

  /// Packing time before the rider leaves; null when head office has not set it.
  final int? prepMinutes;
}

/// A branch with its distance from the user; [distanceKm] is null when the
/// user's location is unknown.
class BranchDistance {
  const BranchDistance(this.branch, this.distanceKm);

  final StoreBranch branch;
  final double? distanceKm;

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
