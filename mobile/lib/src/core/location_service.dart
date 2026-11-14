import 'package:geolocator/geolocator.dart';

import '../models/store_branch.dart';

enum LocationIssue { serviceDisabled, denied, deniedForever, unavailable }

class LocationFailure implements Exception {
  const LocationFailure(this.issue);

  final LocationIssue issue;
}

class LocationService {
  const LocationService();

  /// Asks for permission when needed; throws [LocationFailure] instead of
  /// letting platform errors escape.
  Future<Position> currentPosition() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw const LocationFailure(LocationIssue.serviceDisabled);
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      switch (permission) {
        case LocationPermission.deniedForever:
          throw const LocationFailure(LocationIssue.deniedForever);
        case LocationPermission.denied:
        case LocationPermission.unableToDetermine:
          throw const LocationFailure(LocationIssue.denied);
        case LocationPermission.whileInUse:
        case LocationPermission.always:
          break;
      }

      // A cached fix is close enough to pick a branch and skips the GPS wait.
      return await Geolocator.getLastKnownPosition() ??
          await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.medium,
              timeLimit: Duration(seconds: 15),
            ),
          );
    } on LocationFailure {
      rethrow;
    } on LocationServiceDisabledException {
      throw const LocationFailure(LocationIssue.serviceDisabled);
    } on PermissionDeniedException {
      throw const LocationFailure(LocationIssue.denied);
    } catch (_) {
      throw const LocationFailure(LocationIssue.unavailable);
    }
  }

  /// Branches sorted closest first.
  static List<BranchDistance> sortByDistance(
    List<StoreBranch> branches,
    double latitude,
    double longitude,
  ) {
    return [
      for (final branch in branches)
        BranchDistance(
          branch,
          Geolocator.distanceBetween(
                latitude,
                longitude,
                branch.latitude,
                branch.longitude,
              ) /
              1000,
        ),
    ]..sort((a, b) => a.distanceKm!.compareTo(b.distanceKm!));
  }
}
