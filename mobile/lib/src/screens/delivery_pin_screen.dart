import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/location_service.dart';
import '../models/store_branch.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';

/// Kilometres from the branch to the pin, measured the way the API checks it.
double pinDistanceKm(StoreBranch branch, LatLng pin) =>
    Geolocator.distanceBetween(
      branch.latitude,
      branch.longitude,
      pin.latitude,
      pin.longitude,
    ) /
    1000;

/// The picker's answer: a confirmed pin, or a switch to pickup when it is too far.
class PinChoice {
  const PinChoice.pin(LatLng this.pin) : pickup = false;
  const PinChoice.pickup() : pin = null, pickup = true;

  final LatLng? pin;
  final bool pickup;
}

/// Full-screen map with a fixed centre pin: the customer drags the map until
/// the pin sits on their door, and sees straight away whether the branch delivers there.
class DeliveryPinScreen extends ConsumerStatefulWidget {
  const DeliveryPinScreen({super.key, required this.initial, this.branch});

  final LatLng initial;
  final StoreBranch? branch;

  @override
  ConsumerState<DeliveryPinScreen> createState() => _DeliveryPinScreenState();
}

class _DeliveryPinScreenState extends ConsumerState<DeliveryPinScreen> {
  final _map = MapController();
  late LatLng _center = widget.initial;

  /// True while the map is moving; the pin lifts and the distance waits.
  bool _moving = false;
  Timer? _settle;

  @override
  void dispose() {
    _settle?.cancel();
    _map.dispose();
    super.dispose();
  }

  void _moved(MapCamera camera, bool hasGesture) {
    _settle?.cancel();
    setState(() {
      _center = camera.center;
      _moving = hasGesture;
    });
    if (hasGesture) {
      _settle = Timer(const Duration(milliseconds: 300), () {
        if (mounted) {
          setState(() => _moving = false);
        }
      });
    }
  }

  Future<void> _locate() async {
    try {
      final here = await ref.read(locationServiceProvider).currentPosition();
      if (!mounted) {
        return;
      }
      final point = LatLng(here.latitude, here.longitude);
      _map.move(point, 17);
      setState(() => _center = point);
    } on LocationFailure {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Turn on location to find you, or drag the map.'),
          ),
        );
      }
    }
  }

  /// OpenStreetMap has no street-level photos, so this hands the pin to Google Street View.
  Future<void> _viewStreet() async {
    final opened = await launchUrl(
      Uri.parse(
        'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${_center.latitude},${_center.longitude}',
      ),
      mode: LaunchMode.externalApplication,
    );
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open Street View.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final branch = widget.branch;
    final radius = branch?.maxDeliveryRadiusKm;
    final km = branch == null ? null : pinDistanceKm(branch, _center);
    final tooFar = km != null && radius != null && km > radius;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final floating = [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.18),
        blurRadius: 8,
        offset: const Offset(0, 2),
      ),
    ];

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: FlutterMap(
              mapController: _map,
              options: MapOptions(
                initialCenter: widget.initial,
                initialZoom: 17,
                minZoom: 11,
                interactionOptions: const InteractionOptions(
                  flags:
                      InteractiveFlag.drag |
                      InteractiveFlag.pinchZoom |
                      InteractiveFlag.doubleTapZoom,
                ),
                onPositionChanged: _moved,
              ),
              children: [
                // shortcut: OpenStreetMap's public tiles allow light use only;
                // move to a paid tile host (MapTiler, Stadia) before heavy traffic.
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.jmmart.jmmart',
                ),
                if (branch != null && radius != null)
                  CircleLayer(
                    circles: [
                      CircleMarker(
                        point: LatLng(branch.latitude, branch.longitude),
                        radius: radius * 1000,
                        useRadiusInMeter: true,
                        color: scheme.primary.withValues(alpha: 0.06),
                        borderColor: scheme.primary.withValues(alpha: 0.45),
                        borderStrokeWidth: 2,
                      ),
                    ],
                  ),
                if (branch != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: LatLng(branch.latitude, branch.longitude),
                        width: 190,
                        height: 36,
                        child: Center(child: _BranchChip(name: branch.name)),
                      ),
                    ],
                  ),
              ],
            ),
          ),
          // The pin marks the map's centre; it lifts off its shadow while the map moves.
          IgnorePointer(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  AnimatedSlide(
                    offset: _moving ? const Offset(0, -0.25) : Offset.zero,
                    duration: reduceMotion
                        ? Duration.zero
                        : const Duration(milliseconds: 160),
                    curve: Curves.easeOutCubic,
                    child: Icon(
                      Icons.location_on_rounded,
                      size: 48,
                      color: tooFar ? scheme.error : scheme.primary,
                      semanticLabel: 'Delivery pin',
                    ),
                  ),
                  AnimatedContainer(
                    duration: reduceMotion
                        ? Duration.zero
                        : const Duration(milliseconds: 160),
                    width: _moving ? 14 : 10,
                    height: _moving ? 6 : 4,
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(
                        alpha: _moving ? 0.2 : 0.35,
                      ),
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  // Balances the icon above so the pin's tip sits on the centre.
                  const SizedBox(height: 44),
                ],
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
              child: Row(
                children: [
                  DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: scheme.surfaceContainerLowest,
                      boxShadow: floating,
                    ),
                    child: IconButton(
                      tooltip: 'Close',
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Container(
                      height: 48,
                      alignment: Alignment.centerLeft,
                      padding: const EdgeInsets.symmetric(horizontal: 18),
                      decoration: BoxDecoration(
                        color: scheme.surfaceContainerLowest,
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: floating,
                      ),
                      child: Text(
                        'Set your delivery pin',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: 14, bottom: 10),
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: scheme.surfaceContainerLowest,
                      boxShadow: floating,
                    ),
                    child: IconButton(
                      tooltip: 'View street',
                      iconSize: 24,
                      color: scheme.primary,
                      style: IconButton.styleFrom(
                        fixedSize: const Size.square(52),
                      ),
                      onPressed: _moving ? null : _viewStreet,
                      icon: const Icon(Icons.streetview_rounded),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(right: 14, bottom: 10),
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: scheme.surfaceContainerLowest,
                      boxShadow: floating,
                    ),
                    child: IconButton(
                      tooltip: 'Use my location',
                      iconSize: 24,
                      color: scheme.primary,
                      style: IconButton.styleFrom(
                        fixedSize: const Size.square(52),
                      ),
                      onPressed: _locate,
                      icon: const Icon(Icons.my_location_rounded),
                    ),
                  ),
                ),
                // OpenStreetMap's licence asks for this credit on the map.
                ColoredBox(
                  color: Colors.white.withValues(alpha: 0.82),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    child: Text(
                      '© OpenStreetMap',
                      style: TextStyle(fontSize: 11, color: Colors.black87),
                    ),
                  ),
                ),
                _panel(theme, km: km, radius: radius, tooFar: tooFar),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _panel(
    ThemeData theme, {
    required double? km,
    required double? radius,
    required bool tooFar,
  }) {
    final scheme = theme.colorScheme;
    final branch = widget.branch;
    final ok = statusColour(context, 'completed');

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        borderRadius: const BorderRadius.vertical(
          top: Radius.circular(appModalRadius),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 24,
            offset: const Offset(0, -6),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                tooFar ? 'Too far for delivery' : 'Is the pin on your door?',
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                tooFar && branch != null
                    ? '${branch.name} delivers within ${radius!.toStringAsFixed(0)} km.'
                    : 'Drag the map. The rider goes exactly where the pin points.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
              if (branch != null && km != null) ...[
                const SizedBox(height: 14),
                _meter(
                  theme,
                  icon: _moving
                      ? Icons.near_me_outlined
                      : tooFar
                      ? Icons.error_rounded
                      : Icons.check_circle_rounded,
                  colour: _moving
                      ? scheme.onSurfaceVariant
                      : tooFar
                      ? scheme.error
                      : ok,
                  title: _moving
                      ? 'Measuring…'
                      : tooFar
                      ? 'This pin is ${km.toStringAsFixed(1)} km away'
                      : '${km.toStringAsFixed(1)} km from ${branch.name}',
                  detail: _moving
                      ? 'The distance updates when you let go'
                      : tooFar
                      ? 'Move the pin closer, or pick it up at the branch'
                      : radius == null
                      ? null
                      : 'Inside the ${radius.toStringAsFixed(0)} km delivery area',
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(54),
                  shape: const StadiumBorder(),
                ),
                onPressed: tooFar || _moving
                    ? null
                    : () => Navigator.of(context).pop(PinChoice.pin(_center)),
                child: Text(tooFar ? 'Move the pin closer' : 'Confirm pin'),
              ),
              if (tooFar) ...[
                const SizedBox(height: 4),
                TextButton.icon(
                  style: TextButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: () =>
                      Navigator.of(context).pop(const PinChoice.pickup()),
                  icon: const Icon(Icons.storefront_outlined),
                  label: const Text('Choose pickup instead'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _meter(
    ThemeData theme, {
    required IconData icon,
    required Color colour,
    required String title,
    String? detail,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colour.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      child: Row(
        children: [
          Icon(icon, color: colour),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: colour,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (detail != null)
                  Text(detail, style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The branch on the map: a small white label with a store badge.
class _BranchChip extends StatelessWidget {
  const _BranchChip({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(4, 4, 10, 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.16),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(
            radius: 13,
            backgroundColor: theme.colorScheme.secondary,
            child: const Icon(
              Icons.storefront_rounded,
              size: 15,
              color: Colors.white,
            ),
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.labelMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
