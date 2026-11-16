import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../theme.dart';

/// A small map with a pin fixed in the middle: the customer drags the map
/// until the pin sits on their door, like a ride or food app.
class DeliveryPinMap extends StatelessWidget {
  const DeliveryPinMap({
    super.key,
    required this.initial,
    required this.onMoved,
    this.branch,
    this.height = 220,
  });

  final LatLng initial;
  final ValueChanged<LatLng> onMoved;

  /// Where the branch is, shown as a small store marker for orientation.
  final LatLng? branch;
  final double height;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ClipRRect(
      borderRadius: BorderRadius.circular(appSurfaceRadius),
      child: SizedBox(
        height: height,
        child: Stack(
          children: [
            FlutterMap(
              options: MapOptions(
                initialCenter: initial,
                initialZoom: 17,
                minZoom: 12,
                interactionOptions: const InteractionOptions(
                  flags: InteractiveFlag.drag | InteractiveFlag.pinchZoom,
                ),
                onPositionChanged: (camera, hasGesture) {
                  if (hasGesture) onMoved(camera.center);
                },
              ),
              children: [
                // shortcut: OpenStreetMap's public tiles allow light use only;
                // move to a paid tile host (MapTiler, Stadia) before heavy traffic.
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.jmmart.jmmart',
                ),
                if (branch != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: branch!,
                        width: 32,
                        height: 32,
                        child: Icon(
                          Icons.storefront_rounded,
                          color: scheme.secondary,
                          semanticLabel: 'Branch',
                        ),
                      ),
                    ],
                  ),
              ],
            ),
            // OpenStreetMap's licence asks for this credit on the map.
            Positioned(
              right: 0,
              bottom: 0,
              child: ColoredBox(
                color: Colors.white.withValues(alpha: 0.8),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  child: Text(
                    '© OpenStreetMap',
                    maxLines: 1,
                    style: TextStyle(fontSize: 10, color: Colors.black87),
                  ),
                ),
              ),
            ),
            IgnorePointer(
              child: Center(
                child: Padding(
                  // Lifts the pin so its tip, not its middle, marks the spot.
                  padding: const EdgeInsets.only(bottom: 40),
                  child: Icon(
                    Icons.location_on_rounded,
                    size: 44,
                    color: scheme.primary,
                    semanticLabel: 'Delivery pin',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
