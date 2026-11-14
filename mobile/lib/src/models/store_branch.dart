import 'models.dart';

/// A store that has set a map pin; [id] is the store slug.
class StoreBranch {
  const StoreBranch({
    required this.id,
    required this.name,
    required this.address,
    required this.latitude,
    required this.longitude,
    this.maxDeliveryRadiusKm,
  });

  factory StoreBranch.fromJson(Map<String, dynamic> json) => StoreBranch(
    id: str(json['slug']),
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
  );

  final String id;
  final String name;
  final String address;
  final double latitude;
  final double longitude;

  /// Null when the seller has not set a delivery radius.
  final double? maxDeliveryRadiusKm;
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
