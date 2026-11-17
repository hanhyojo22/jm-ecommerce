import 'package:geolocator/geolocator.dart';

import '../models/store_branch.dart';

enum LocationIssue { serviceDisabled, denied, deniedForever, unavailable }

class LocationFailure implements Exception {
  const LocationFailure(this.issue);

  final LocationIssue issue;
}

class LocationService {
  const LocationService();

  /// Android's check only ever says denied or granted; a request is the only
  /// thing that reveals the dialog is blocked, so the last answer is kept here.
  // shortcut: kept in memory, so it resets on restart; askForLocation covers that case.
  static bool _blockedByAndroid = false;

  /// The current permission, without asking; failures read as not granted.
  Future<LocationPermission> permission() async {
    try {
      final permission = await Geolocator.checkPermission();
      if (permission != LocationPermission.denied) {
        _blockedByAndroid = false;
        return permission;
      }
      return _blockedByAndroid ? LocationPermission.deniedForever : permission;
    } catch (_) {
      return LocationPermission.unableToDetermine;
    }
  }

  /// Shows Android's location dialog (Precise or Approximate), unless Android
  /// has blocked it, which comes back as [LocationPermission.deniedForever].
  Future<LocationPermission> requestPermission() async {
    try {
      final permission = await Geolocator.requestPermission();
      _blockedByAndroid = permission == LocationPermission.deniedForever;
      return permission;
    } catch (_) {
      return LocationPermission.denied;
    }
  }

  /// The app's page in Android Settings, where Permissions → Location lives.
  Future<void> openAppSettings() async {
    try {
      await Geolocator.openAppSettings();
    } catch (_) {
      // Nothing to recover: the customer stays where they were.
    }
  }

  /// Never asks for permission itself: the app explains first, then calls
  /// [requestPermission]. Throws [LocationFailure] instead of letting platform
  /// errors escape.
  Future<Position> currentPosition() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw const LocationFailure(LocationIssue.serviceDisabled);
      }

      switch (await permission()) {
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
