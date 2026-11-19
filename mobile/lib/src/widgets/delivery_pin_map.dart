import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../theme.dart';

/// A still map preview of the delivery pin for the checkout list. It does not
/// take drags (they would fight the page scroll); tapping it opens the picker.
class DeliveryPinMap extends StatelessWidget {
  const DeliveryPinMap({
    super.key,
    required this.pin,
    required this.onTap,
    this.branch,
    this.height = 124,
  });

  final LatLng pin;
  final VoidCallback onTap;

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
            IgnorePointer(
              child: FlutterMap(
                // A new pin needs a new map: initialCenter only applies once.
                key: ValueKey(pin),
                options: MapOptions(
                  initialCenter: pin,
                  initialZoom: 16,
                  interactionOptions: const InteractionOptions(
                    flags: InteractiveFlag.none,
                  ),
                ),
                children: [
                  TileLayer(
                    urlTemplate:
                        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.jmmart.jmmart',
                  ),
                  if (branch != null)
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: branch!,
                          width: 28,
                          height: 28,
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
            ),
            IgnorePointer(
              child: Center(
                child: Padding(
                  // Lifts the pin so its tip, not its middle, marks the spot.
                  padding: const EdgeInsets.only(bottom: 40),
                  child: Icon(
                    Icons.location_on_rounded,
                    size: 40,
                    color: scheme.primary,
                    semanticLabel: 'Delivery pin',
                  ),
                ),
              ),
            ),
            Positioned.fill(
              child: Material(
                type: MaterialType.transparency,
                child: InkWell(onTap: onTap, excludeFromSemantics: true),
              ),
            ),
            // OpenStreetMap's licence asks for this credit on the map.
            Positioned(
              left: 0,
              bottom: 0,
              child: IgnorePointer(
                child: ColoredBox(
                  color: Colors.white.withValues(alpha: 0.82),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    child: Text(
                      '© OpenStreetMap',
                      maxLines: 1,
                      style: TextStyle(fontSize: 11, color: Colors.black87),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              right: 8,
              bottom: 8,
              child: FilledButton.tonalIcon(
                style: FilledButton.styleFrom(
                  minimumSize: const Size(0, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  backgroundColor: scheme.surfaceContainerLowest,
                  foregroundColor: scheme.primary,
                  elevation: 2,
                  shape: const StadiumBorder(),
                ),
                onPressed: onTap,
                icon: const Icon(Icons.edit_location_alt_outlined, size: 18),
                label: const Text('Adjust pin'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
