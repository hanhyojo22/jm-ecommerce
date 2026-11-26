import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:phosphor_icons/phosphor_icons.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/formatters.dart';
import '../core/location_service.dart';
import '../models/store_branch.dart';
import '../providers/store_locator_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/status_notice.dart';
import '../widgets/store_locator.dart';

enum BranchFilter { all, openNow, open24Hours }

/// Represents either a single branch pin or a geographic cluster of nearby pins.
class BranchCluster {
  const BranchCluster({required this.center, required this.items});

  final LatLng center;
  final List<BranchDistance> items;

  bool get isCluster => items.length > 1;
}

/// Filters branch locations by search text, operational hours filter, and
/// optional visible map area bounds.
List<BranchDistance> filterLocatorBranches(
  List<BranchDistance> items, {
  required String query,
  required BranchFilter filter,
  LatLngBounds? areaBounds,
}) {
  final needle = query.trim().toLowerCase();

  return items.where((item) {
    final branch = item.branch;
    if (needle.isNotEmpty) {
      final matches =
          branch.name.toLowerCase().contains(needle) ||
          branch.shortName.toLowerCase().contains(needle) ||
          branch.fullAddress.toLowerCase().contains(needle) ||
          branch.description.toLowerCase().contains(needle) ||
          branch.city.toLowerCase().contains(needle) ||
          branch.province.toLowerCase().contains(needle);
      if (!matches) {
        return false;
      }
    }

    switch (filter) {
      case BranchFilter.all:
        break;
      case BranchFilter.openNow:
        if (!branch.isOpen) {
          return false;
        }
      case BranchFilter.open24Hours:
        if (!branch.open24Hours) {
          return false;
        }
    }

    if (areaBounds != null &&
        !areaBounds.contains(LatLng(branch.latitude, branch.longitude))) {
      return false;
    }

    return true;
  }).toList();
}

/// Groups nearby branches into numbered clusters when zoomed out below 11.5.
List<BranchCluster> clusterBranches(
  List<BranchDistance> items, {
  required double zoom,
}) {
  if (items.length < 2 || zoom >= 11.5) {
    return [
      for (final item in items)
        BranchCluster(
          center: LatLng(item.branch.latitude, item.branch.longitude),
          items: [item],
        ),
    ];
  }

  const geodesic = Distance();
  final zoomDelta = (11.5 - zoom).clamp(0.0, 6.0);
  final thresholdMeters = 4500.0 * math.pow(2.0, zoomDelta);
  final assigned = List<bool>.filled(items.length, false);
  final clusters = <BranchCluster>[];

  for (var i = 0; i < items.length; i++) {
    if (assigned[i]) {
      continue;
    }
    assigned[i] = true;
    final seed = items[i];
    final seedPos = LatLng(seed.branch.latitude, seed.branch.longitude);
    final group = <BranchDistance>[seed];

    for (var j = i + 1; j < items.length; j++) {
      if (assigned[j]) {
        continue;
      }
      final candidate = items[j];
      final candidatePos = LatLng(
        candidate.branch.latitude,
        candidate.branch.longitude,
      );
      if (geodesic.as(LengthUnit.Meter, seedPos, candidatePos) <=
          thresholdMeters) {
        assigned[j] = true;
        group.add(candidate);
      }
    }

    final avgLat =
        group.fold<double>(0, (sum, e) => sum + e.branch.latitude) /
        group.length;
    final avgLng =
        group.fold<double>(0, (sum, e) => sum + e.branch.longitude) /
        group.length;

    clusters.add(BranchCluster(center: LatLng(avgLat, avgLng), items: group));
  }

  return clusters;
}

/// Standalone physical Branch Locator screen (`/branches`) using a full-bleed
/// map paired with a 3-detent draggable sheet (`Peek`, `Half`, `Full`).
class BranchLocatorScreen extends ConsumerStatefulWidget {
  const BranchLocatorScreen({super.key, this.launchUri});

  /// Injectable external URI handler for deterministic widget testing.
  final Future<bool> Function(Uri uri)? launchUri;

  @override
  ConsumerState<BranchLocatorScreen> createState() =>
      _BranchLocatorScreenState();
}

class _BranchLocatorScreenState extends ConsumerState<BranchLocatorScreen> {
  static const _peekExtent = 0.28;
  static const _halfExtent = 0.52;
  static const _fullExtent = 0.88;
  static const _defaultCenter = LatLng(12.8797, 121.7740);

  final MapController _mapController = MapController();
  final DraggableScrollableController _sheetController =
      DraggableScrollableController();
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _searchFocus = FocusNode();

  bool _mapReady = false;
  bool _hasInitializedCamera = false;
  double _currentZoom = 12.5;
  double _sheetExtent = _halfExtent;
  String? _selectedBranchId;
  String _searchQuery = '';
  BranchFilter _filter = BranchFilter.all;
  bool _showSearchArea = false;
  LatLngBounds? _areaBounds;

  @override
  void initState() {
    super.initState();
    _sheetController.addListener(_onSheetChanged);
  }

  @override
  void dispose() {
    _sheetController.removeListener(_onSheetChanged);
    _sheetController.dispose();
    _mapController.dispose();
    _searchController.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  void _onSheetChanged() {
    if (!_sheetController.isAttached) {
      return;
    }
    final next = _sheetController.size;
    if ((next - _sheetExtent).abs() > 0.01 && mounted) {
      setState(() => _sheetExtent = next);
    }
  }

  Future<void> _animateSheetTo(double target) async {
    if (!_sheetController.isAttached) {
      return;
    }
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    if (reduceMotion) {
      _sheetController.jumpTo(target);
      return;
    }
    await _sheetController.animateTo(
      target,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _cycleDetent() {
    final target = switch (_sheetExtent) {
      < 0.40 => _halfExtent,
      < 0.72 => _fullExtent,
      _ => _peekExtent,
    };
    return _animateSheetTo(target);
  }

  void _moveCamera(LatLng center, double zoom) {
    if (!_mapReady) {
      return;
    }
    _mapController.move(center, zoom);
    setState(() => _currentZoom = zoom);
  }

  /// Centres on the nearest branch once both the map and the branch list are
  /// ready, whichever arrives last.
  void _focusNearestBranchOnce() {
    final locator = ref.read(nearestStoreProvider).value;
    final focus = locator?.nearest ?? locator?.branches.firstOrNull;
    if (_hasInitializedCamera || !_mapReady || focus == null) {
      return;
    }
    _hasInitializedCamera = true;
    _focusBranch(focus);
  }

  void _focusBranch(BranchDistance item, {bool expandZoom = false}) {
    setState(() => _selectedBranchId = item.branch.id);
    if (!_mapReady) {
      return;
    }
    final targetZoom = expandZoom && _currentZoom < 13.0 ? 13.5 : _currentZoom;
    // Offset camera slightly south so the pin stays clearly above the bottom sheet.
    final latOffset = 0.012 / math.pow(2.0, targetZoom - 12.5);
    _moveCamera(
      LatLng(item.branch.latitude - latOffset, item.branch.longitude),
      targetZoom,
    );
  }

  void _fitAllBranches(List<BranchDistance> branches, LatLng? userPosition) {
    if (!_mapReady) {
      return;
    }
    final points = <LatLng>[
      for (final item in branches)
        LatLng(item.branch.latitude, item.branch.longitude),
      ?userPosition,
    ];
    if (points.isEmpty) {
      return;
    }
    if (points.length == 1) {
      _moveCamera(points.first, 14.0);
      return;
    }
    _mapController.fitCamera(
      CameraFit.coordinates(
        coordinates: points,
        padding: const EdgeInsets.fromLTRB(48, 170, 48, 320),
        maxZoom: 15.0,
      ),
    );
    setState(() {
      _currentZoom = _mapController.camera.zoom;
      _showSearchArea = false;
    });
  }

  void _recenterOnUser(StoreLocatorState? locator) {
    final userPos = locator?.userPosition;
    if (userPos == null) {
      AppNotice.of(context).show(
        'Turn on location access to center on your position.',
        kind: NoticeKind.info,
      );
      return;
    }
    _moveCamera(userPos, 13.5);
    setState(() => _showSearchArea = false);
  }

  Future<void> _launchExternal(Uri uri, String fallbackMessage) async {
    try {
      final launcher =
          widget.launchUri ??
          (target) => launchUrl(target, mode: LaunchMode.externalApplication);
      final opened = await launcher(uri);
      if (!opened && mounted) {
        AppNotice.of(context).show(fallbackMessage);
      }
    } catch (_) {
      if (mounted) {
        AppNotice.of(context).show(fallbackMessage);
      }
    }
  }

  void _copyText(String text, String confirmation) {
    unawaited(Clipboard.setData(ClipboardData(text: text)));
    AppNotice.of(context).show(confirmation, kind: NoticeKind.success);
  }

  Future<void> _openBranchDetail(
    BranchDistance item, {
    required bool isNearest,
  }) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _BranchDetailSheet(
        item: item,
        isNearest: isNearest,
        onDirections: () {
          Navigator.pop(context);
          unawaited(_openDirectionsChooser(item));
        },
        onCall: item.branch.phone.isEmpty
            ? null
            : () => _launchExternal(
                Uri(scheme: 'tel', path: item.branch.phone),
                'Could not open the phone dialer.',
              ),
        onCopyAddress: () => _copyText(
          item.branch.fullAddress,
          'Branch address copied to clipboard.',
        ),
        onShare: () {
          final branch = item.branch;
          final mapUrl =
              'https://www.google.com/maps/search/?api=1&query=${branch.latitude},${branch.longitude}';
          _copyText(
            '${branch.name}\n${branch.fullAddress}\n$mapUrl',
            'Branch location link copied to share.',
          );
        },
      ),
    );
  }

  Future<void> _openDirectionsChooser(BranchDistance item) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (_) => _DirectionsChooserSheet(
        item: item,
        onGoogleMaps: () {
          Navigator.pop(context);
          final b = item.branch;
          unawaited(
            _launchExternal(
              Uri.parse(
                'https://www.google.com/maps/dir/?api=1&destination=${b.latitude},${b.longitude}',
              ),
              'Could not open Google Maps.',
            ),
          );
        },
        onWaze: () {
          Navigator.pop(context);
          final b = item.branch;
          unawaited(
            _launchExternal(
              Uri.parse(
                'https://waze.com/ul?ll=${b.latitude},${b.longitude}&navigate=yes',
              ),
              'Could not open Waze.',
            ),
          );
        },
        onCopyAddress: () {
          Navigator.pop(context);
          _copyText(
            item.branch.fullAddress,
            'Branch address copied to clipboard.',
          );
        },
        onCopyCoordinates: () {
          Navigator.pop(context);
          final b = item.branch;
          _copyText(
            '${b.latitude.toStringAsFixed(6)}, ${b.longitude.toStringAsFixed(6)}',
            'GPS coordinates copied to clipboard.',
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final locatorAsync = ref.watch(nearestStoreProvider);
    final locator = locatorAsync.value;
    final allBranches = locator?.branches ?? const <BranchDistance>[];
    final filtered = filterLocatorBranches(
      allBranches,
      query: _searchQuery,
      filter: _filter,
      areaBounds: _areaBounds,
    );

    if (!_hasInitializedCamera && locator != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _focusNearestBranchOnce();
        }
      });
    }

    final selected =
        filtered.where((b) => b.branch.id == _selectedBranchId).firstOrNull ??
        filtered.firstOrNull;
    final selectedId = selected?.branch.id;
    final clusters = clusterBranches(filtered, zoom: _currentZoom);
    final userPos = locator?.userPosition;
    final showRoute =
        userPos != null &&
        selected != null &&
        clusters.any(
          (c) => !c.isCluster && c.items.first.branch.id == selectedId,
        );

    final initialCenter =
        userPos ??
        (allBranches.isNotEmpty
            ? LatLng(
                allBranches.first.branch.latitude,
                allBranches.first.branch.longitude,
              )
            : _defaultCenter);

    final screenSize = MediaQuery.sizeOf(context);
    final viewPadding = MediaQuery.viewPaddingOf(context);
    final controlsBottom = (screenSize.height * _sheetExtent + 12).clamp(
      120.0,
      screenSize.height * 0.72,
    );

    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        value: (dark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark)
            .copyWith(statusBarColor: Colors.transparent),
        child: Stack(
          children: [
            // 1. Interactive Full-Bleed Map
            Positioned.fill(
              child: FlutterMap(
                mapController: _mapController,
                options: MapOptions(
                  initialCenter: initialCenter,
                  initialZoom: _currentZoom,
                  minZoom: 5.5,
                  maxZoom: 18,
                  interactionOptions: const InteractionOptions(
                    flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
                  ),
                  onMapReady: () {
                    _mapReady = true;
                    _focusNearestBranchOnce();
                  },
                  onPositionChanged: (camera, hasGesture) {
                    final zoomChanged =
                        (camera.zoom - _currentZoom).abs() > 0.05;
                    if (zoomChanged || (hasGesture && !_showSearchArea)) {
                      setState(() {
                        _currentZoom = camera.zoom;
                        if (hasGesture) {
                          _showSearchArea = true;
                        }
                      });
                    }
                  },
                ),
                children: [
                  TileLayer(
                    urlTemplate:
                        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.jmmart.jmmart',
                    tileBuilder: dark ? _darkTileBuilder : null,
                  ),
                  if (showRoute)
                    PolylineLayer(
                      polylines: [
                        Polyline(
                          points: [
                            userPos,
                            LatLng(
                              selected.branch.latitude,
                              selected.branch.longitude,
                            ),
                          ],
                          strokeWidth: 3.5,
                          color: scheme.primary.withValues(alpha: 0.85),
                          pattern: StrokePattern.dashed(segments: const [8, 6]),
                        ),
                      ],
                    ),
                  if (showRoute && selected.distanceKm != null)
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: LatLng(
                            (userPos.latitude + selected.branch.latitude) / 2,
                            (userPos.longitude + selected.branch.longitude) / 2,
                          ),
                          width: 120,
                          height: 28,
                          child: Center(child: _DistanceChip(item: selected)),
                        ),
                      ],
                    ),
                  if (userPos != null)
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: userPos,
                          width: 34,
                          height: 34,
                          child: _UserLocationDot(),
                        ),
                      ],
                    ),
                  MarkerLayer(
                    markers: [
                      for (final cluster in clusters)
                        if (cluster.isCluster)
                          Marker(
                            point: cluster.center,
                            width: 52,
                            height: 52,
                            child: _ClusterMarker(
                              count: cluster.items.length,
                              onTap: () {
                                if (!_mapReady) {
                                  return;
                                }
                                _mapController.fitCamera(
                                  CameraFit.coordinates(
                                    coordinates: [
                                      for (final item in cluster.items)
                                        LatLng(
                                          item.branch.latitude,
                                          item.branch.longitude,
                                        ),
                                    ],
                                    padding: const EdgeInsets.fromLTRB(
                                      56,
                                      180,
                                      56,
                                      320,
                                    ),
                                    maxZoom: 14.5,
                                  ),
                                );
                                setState(() {
                                  _currentZoom = _mapController.camera.zoom;
                                });
                              },
                            ),
                          )
                        else
                          Marker(
                            point: cluster.center,
                            width: 168,
                            height: 76,
                            alignment: Alignment.bottomCenter,
                            child: _BranchPinMarker(
                              item: cluster.items.first,
                              selected:
                                  cluster.items.first.branch.id == selectedId,
                              onTap: () => _focusBranch(
                                cluster.items.first,
                                expandZoom: true,
                              ),
                            ),
                          ),
                    ],
                  ),
                ],
              ),
            ),

            // 2. Top Safe-Area Search, Filters, GPS Banner & "Search this area"
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          _FloatingCircleButton(
                            tooltip: 'Back',
                            icon: PhosphorIconsRegular.arrowLeft,
                            onPressed: () => Navigator.of(context).maybePop(),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _LocatorSearchBar(
                              controller: _searchController,
                              focusNode: _searchFocus,
                              query: _searchQuery,
                              onChanged: (value) =>
                                  setState(() => _searchQuery = value),
                              onClear: () {
                                _searchController.clear();
                                setState(() => _searchQuery = '');
                              },
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      SizedBox(
                        height: 44,
                        child: ListView(
                          scrollDirection: Axis.horizontal,
                          children: [
                            _FilterPill(
                              label: 'All',
                              selected: _filter == BranchFilter.all,
                              onTap: () =>
                                  setState(() => _filter = BranchFilter.all),
                            ),
                            const SizedBox(width: 8),
                            _FilterPill(
                              label: 'Open now',
                              icon: PhosphorIconsRegular.sun,
                              selected: _filter == BranchFilter.openNow,
                              onTap: () => setState(
                                () => _filter = BranchFilter.openNow,
                              ),
                            ),
                            const SizedBox(width: 8),
                            _FilterPill(
                              label: '24 Hours',
                              icon: PhosphorIconsRegular.clock,
                              selected: _filter == BranchFilter.open24Hours,
                              onTap: () => setState(
                                () => _filter = BranchFilter.open24Hours,
                              ),
                            ),
                            if (_areaBounds != null) ...[
                              const SizedBox(width: 8),
                              _FilterPill(
                                label: 'This area ×',
                                selected: true,
                                onTap: () => setState(() => _areaBounds = null),
                              ),
                            ],
                          ],
                        ),
                      ),
                      if (locator?.issue != null) ...[
                        const SizedBox(height: 8),
                        _GpsIssueBanner(
                          issue: locator!.issue!,
                          onAction: () async {
                            switch (locator.issue!) {
                              case LocationIssue.serviceDisabled:
                                await Geolocator.openLocationSettings();
                              case LocationIssue.deniedForever:
                                await Geolocator.openAppSettings();
                              case LocationIssue.denied:
                                if (context.mounted) {
                                  await askForLocation(context, ref);
                                }
                              case LocationIssue.unavailable:
                                ref.invalidate(nearestStoreProvider);
                            }
                          },
                        ),
                      ],
                      if (_showSearchArea) ...[
                        const SizedBox(height: 10),
                        FilledButton.tonalIcon(
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(0, 40),
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            backgroundColor: scheme.surfaceContainerLowest,
                            foregroundColor: scheme.primary,
                            elevation: 3,
                            shape: const StadiumBorder(),
                          ),
                          onPressed: () {
                            if (!_mapReady) {
                              return;
                            }
                            setState(() {
                              _areaBounds = _mapController.camera.visibleBounds;
                              _showSearchArea = false;
                            });
                          },
                          icon: const Icon(
                            PhosphorIconsRegular.arrowsClockwise,
                            size: 16,
                          ),
                          label: const Text('Search this area'),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),

            // 3. Attribution & Floating Map Controls above Sheet
            if (_sheetExtent < 0.78) ...[
              Positioned(
                left: 14,
                bottom: controlsBottom,
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainerLowest.withValues(
                        alpha: 0.86,
                      ),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      child: Text(
                        '© OpenStreetMap',
                        style: theme.textTheme.labelSmall?.copyWith(
                          fontSize: 10.5,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                right: 14,
                bottom: controlsBottom,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _FloatingCircleButton(
                      tooltip: 'Zoom in',
                      icon: PhosphorIconsRegular.plus,
                      onPressed: () => _moveCamera(
                        _mapReady
                            ? _mapController.camera.center
                            : initialCenter,
                        (_currentZoom + 1.0).clamp(5.5, 18.0),
                      ),
                    ),
                    const SizedBox(height: 8),
                    _FloatingCircleButton(
                      tooltip: 'Zoom out',
                      icon: PhosphorIconsRegular.minus,
                      onPressed: () => _moveCamera(
                        _mapReady
                            ? _mapController.camera.center
                            : initialCenter,
                        (_currentZoom - 1.0).clamp(5.5, 18.0),
                      ),
                    ),
                    const SizedBox(height: 8),
                    _FloatingCircleButton(
                      tooltip: 'Fit all branches',
                      icon: PhosphorIconsRegular.globe,
                      onPressed: () => _fitAllBranches(allBranches, userPos),
                    ),
                    const SizedBox(height: 8),
                    _FloatingCircleButton(
                      tooltip: 'Center on my location',
                      icon: PhosphorIconsRegular.crosshair,
                      primary: true,
                      onPressed: () => _recenterOnUser(locator),
                    ),
                  ],
                ),
              ),
            ],

            // 4. 3-Detent Draggable Scrollable Sheet (Peek / Half / Full)
            DraggableScrollableSheet(
              controller: _sheetController,
              initialChildSize: _halfExtent,
              minChildSize: _peekExtent,
              maxChildSize: _fullExtent,
              snap: true,
              snapSizes: const [_peekExtent, _halfExtent, _fullExtent],
              builder: (context, scrollController) {
                return DecoratedBox(
                  decoration: BoxDecoration(
                    color: scheme.surface,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(appModalRadius),
                    ),
                    border: Border(
                      top: BorderSide(color: scheme.outlineVariant),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: dark ? 0.4 : 0.14,
                        ),
                        blurRadius: 20,
                        offset: const Offset(0, -4),
                      ),
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(appModalRadius),
                    ),
                    child: CustomScrollView(
                      controller: scrollController,
                      slivers: [
                        SliverPersistentHeader(
                          pinned: true,
                          delegate: _SheetHeaderDelegate(
                            countText: _sheetHeading(
                              locatorAsync,
                              filtered.length,
                              hasIssue: locator?.issue != null,
                            ),
                            detentExtent: _sheetExtent,
                            onCycleDetent: _cycleDetent,
                            backgroundColor: scheme.surfaceContainerLowest,
                            borderColor: scheme.outlineVariant,
                          ),
                        ),
                        ..._buildSheetBody(
                          context,
                          locatorAsync: locatorAsync,
                          allBranches: allBranches,
                          filtered: filtered,
                          selectedId: selectedId,
                          nearestId: locator?.nearest?.branch.id,
                          bottomSafePad: viewPadding.bottom + 16,
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  String _sheetHeading(
    AsyncValue<StoreLocatorState> locatorAsync,
    int count, {
    required bool hasIssue,
  }) {
    if (locatorAsync.isLoading && !locatorAsync.hasValue) {
      return 'Locating nearby branches…';
    }
    if (locatorAsync.hasError && !locatorAsync.hasValue) {
      return 'Branches unavailable';
    }
    final noun = count == 1 ? 'branch' : 'branches';
    return hasIssue ? '$count $noun' : '$count $noun near you';
  }

  List<Widget> _buildSheetBody(
    BuildContext context, {
    required AsyncValue<StoreLocatorState> locatorAsync,
    required List<BranchDistance> allBranches,
    required List<BranchDistance> filtered,
    required String? selectedId,
    required String? nearestId,
    required double bottomSafePad,
  }) {
    if (locatorAsync.isLoading && !locatorAsync.hasValue) {
      return [
        SliverPadding(
          padding: EdgeInsets.fromLTRB(14, 12, 14, bottomSafePad),
          sliver: SliverList.builder(
            itemCount: 3,
            itemBuilder: (_, _) => const Padding(
              padding: EdgeInsets.only(bottom: 10),
              child: _BranchSkeletonCard(),
            ),
          ),
        ),
      ];
    }

    if (locatorAsync.hasError && !locatorAsync.hasValue) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: ErrorView(
            error: locatorAsync.error!,
            onRetry: () {
              ref.invalidate(storeBranchesProvider);
              ref.invalidate(nearestStoreProvider);
            },
          ),
        ),
      ];
    }

    if (allBranches.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  PhosphorIconsRegular.storefront,
                  size: 40,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
                const SizedBox(height: 12),
                Text(
                  'No branch locations available yet.',
                  style: Theme.of(context).textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: () {
                    ref.invalidate(storeBranchesProvider);
                    ref.invalidate(nearestStoreProvider);
                  },
                  child: const Text('Try again'),
                ),
              ],
            ),
          ),
        ),
      ];
    }

    if (filtered.isEmpty) {
      return [
        SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.fromLTRB(14, 14, 14, bottomSafePad),
            child: _EmptyFilterCard(
              query: _searchQuery,
              onReset: () {
                _searchController.clear();
                setState(() {
                  _searchQuery = '';
                  _filter = BranchFilter.all;
                  _areaBounds = null;
                });
              },
            ),
          ),
        ),
      ];
    }

    final displayItems = _sheetExtent < 0.36
        ? [
            filtered.firstWhere(
              (b) => b.branch.id == selectedId,
              orElse: () => filtered.first,
            ),
          ]
        : filtered;

    return [
      SliverPadding(
        padding: EdgeInsets.fromLTRB(14, 12, 14, bottomSafePad),
        sliver: SliverList.builder(
          itemCount: displayItems.length,
          itemBuilder: (context, index) {
            final item = displayItems[index];
            final isNearest = item.branch.id == nearestId;
            return Padding(
              key: ValueKey(item.branch.id),
              padding: const EdgeInsets.only(bottom: 10),
              child: _BranchLocationCard(
                item: item,
                selected: item.branch.id == selectedId,
                isNearest: isNearest,
                onSelect: () => _focusBranch(item, expandZoom: true),
                onInfo: () => _openBranchDetail(item, isNearest: isNearest),
                onDirections: () => _openDirectionsChooser(item),
              ),
            );
          },
        ),
      ),
    ];
  }

  Widget _darkTileBuilder(
    BuildContext context,
    Widget tileWidget,
    TileImage tile,
  ) {
    return ColorFiltered(
      colorFilter: const ColorFilter.matrix(<double>[
        -0.88,
        0,
        0,
        0,
        232,
        0,
        -0.88,
        0,
        0,
        236,
        0,
        0,
        -0.82,
        0,
        245,
        0,
        0,
        0,
        1,
        0,
      ]),
      child: tileWidget,
    );
  }
}

class _SheetHeaderDelegate extends SliverPersistentHeaderDelegate {
  const _SheetHeaderDelegate({
    required this.countText,
    required this.detentExtent,
    required this.onCycleDetent,
    required this.backgroundColor,
    required this.borderColor,
  });

  final String countText;
  final double detentExtent;
  final VoidCallback onCycleDetent;
  final Color backgroundColor;
  final Color borderColor;

  @override
  double get minExtent => 72;

  @override
  double get maxExtent => 72;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final (label, icon) = switch (detentExtent) {
      < 0.40 => ('Show list', PhosphorIconsRegular.listBullets),
      < 0.72 => ('Expand', PhosphorIconsRegular.caretUp),
      _ => ('Show map', PhosphorIconsRegular.mapTrifold),
    };

    return Material(
      color: backgroundColor,
      child: Container(
        decoration: BoxDecoration(
          border: Border(bottom: BorderSide(color: borderColor)),
        ),
        padding: const EdgeInsets.fromLTRB(16, 8, 12, 8),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Center(
              child: Container(
                width: 38,
                height: 4,
                decoration: BoxDecoration(
                  color: scheme.outline.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: Text(
                    countText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                TextButton.icon(
                  style: TextButton.styleFrom(
                    minimumSize: const Size(0, 40),
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    backgroundColor: scheme.primaryContainer,
                    foregroundColor: scheme.onPrimaryContainer,
                    shape: const StadiumBorder(),
                  ),
                  onPressed: onCycleDetent,
                  icon: Icon(icon, size: 16),
                  label: Text(label),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _SheetHeaderDelegate oldDelegate) =>
      countText != oldDelegate.countText ||
      detentExtent != oldDelegate.detentExtent ||
      backgroundColor != oldDelegate.backgroundColor ||
      borderColor != oldDelegate.borderColor;
}

class _BranchLocationCard extends StatelessWidget {
  const _BranchLocationCard({
    required this.item,
    required this.selected,
    required this.isNearest,
    required this.onSelect,
    required this.onInfo,
    required this.onDirections,
  });

  final BranchDistance item;
  final bool selected;
  final bool isNearest;
  final VoidCallback onSelect;
  final VoidCallback onInfo;
  final VoidCallback onDirections;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final branch = item.branch;

    return Card(
      color: selected
          ? (dark ? scheme.surfaceContainerHigh : const Color(0xFFF9FCFF))
          : scheme.surfaceContainerLowest,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: selected ? scheme.primary : scheme.outlineVariant,
          width: selected ? 1.8 : 1,
        ),
      ),
      child: InkWell(
        onTap: onSelect,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 6,
                          runSpacing: 4,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              branch.shortName,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            if (isNearest)
                              _BadgePill(
                                label: 'Nearest',
                                icon: PhosphorIconsFill.mapPin,
                                background: scheme.primaryContainer,
                                foreground: scheme.onPrimaryContainer,
                              ),
                            if (branch.open24Hours)
                              _BadgePill(
                                label: '24 Hours',
                                background: dark
                                    ? const Color(0xFFFFC94A)
                                    : const Color(0xFFF8BF0B),
                                foreground: dark
                                    ? const Color(0xFF3D2800)
                                    : const Color(0xFF000B88),
                              ),
                          ],
                        ),
                        if (branch.fullAddress.isNotEmpty) ...[
                          const SizedBox(height: 3),
                          Text(
                            branch.fullAddress,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (item.distanceKm != null) ...[
                    const SizedBox(width: 10),
                    Text(
                      '${item.distanceKm!.toStringAsFixed(1)} km',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ],
              ),
              if (branch.description.isNotEmpty) ...[
                const SizedBox(height: 8),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: scheme.surface,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        PhosphorIconsRegular.compass,
                        size: 14,
                        color: scheme.primary,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          branch.description,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 10),
              _BranchStatusPill(branch: branch),
              const SizedBox(height: 12),
              Divider(height: 1, color: scheme.outlineVariant),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(0, 44),
                        backgroundColor: scheme.surfaceContainerHigh,
                      ),
                      onPressed: onInfo,
                      icon: const Icon(
                        PhosphorIconsRegular.storefront,
                        size: 16,
                      ),
                      label: const Text('Branch info'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        minimumSize: const Size(0, 44),
                      ),
                      onPressed: onDirections,
                      icon: const Icon(
                        PhosphorIconsRegular.navigationArrow,
                        size: 16,
                      ),
                      label: const Text('Directions'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BranchStatusPill extends StatelessWidget {
  const _BranchStatusPill({required this.branch});

  final StoreBranch branch;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final openColour = statusColour(context, 'completed');
    final openBg = dark ? const Color(0xFF122E22) : const Color(0xFFEAF7EF);
    final closedBg = dark ? const Color(0xFF3D1519) : const Color(0xFFFDECEC);

    final String label;
    if (branch.isOpen) {
      if (branch.open24Hours) {
        label = 'Open 24 hours';
      } else {
        final closes = branch.formattedClosingTime;
        label = closes == null ? 'Open now' : 'Open · Closes $closes';
      }
    } else {
      final opens = branch.nextOpeningAt;
      label = opens == null ? 'Closed' : 'Closed · Opens ${opensLabel(opens)}';
    }

    return DecoratedBox(
      decoration: BoxDecoration(
        color: branch.isOpen ? openBg : closedBg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              branch.isOpen
                  ? PhosphorIconsRegular.sun
                  : PhosphorIconsRegular.moon,
              size: 13,
              color: branch.isOpen ? openColour : scheme.error,
            ),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: branch.isOpen ? openColour : scheme.error,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BadgePill extends StatelessWidget {
  const _BadgePill({
    required this.label,
    required this.background,
    required this.foreground,
    this.icon,
  });

  final String label;
  final Color background;
  final Color foreground;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 11, color: foreground),
              const SizedBox(width: 3),
            ],
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
                color: foreground,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocatorSearchBar extends StatelessWidget {
  const _LocatorSearchBar({
    required this.controller,
    required this.focusNode,
    required this.query,
    required this.onChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final String query;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: scheme.surfaceContainerLowest,
      elevation: 3,
      shadowColor: Colors.black.withValues(alpha: 0.14),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: Row(
          children: [
            const SizedBox(width: 12),
            Icon(
              PhosphorIconsRegular.magnifyingGlass,
              size: 18,
              color: scheme.onSurfaceVariant,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: controller,
                focusNode: focusNode,
                onChanged: onChanged,
                textInputAction: TextInputAction.search,
                decoration: const InputDecoration(
                  hintText: 'Search branch, street, or city',
                  border: InputBorder.none,
                  enabledBorder: InputBorder.none,
                  focusedBorder: InputBorder.none,
                  filled: false,
                  isDense: true,
                  contentPadding: EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
            if (query.isNotEmpty)
              IconButton(
                tooltip: 'Clear search',
                constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
                onPressed: onClear,
                icon: Icon(
                  PhosphorIconsRegular.x,
                  size: 18,
                  color: scheme.onSurfaceVariant,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  const _FilterPill({
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: selected ? scheme.primary : scheme.surfaceContainerLowest,
      elevation: 2,
      shadowColor: Colors.black.withValues(alpha: 0.10),
      shape: StadiumBorder(
        side: BorderSide(
          color: selected ? scheme.primary : scheme.outlineVariant,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        customBorder: const StadiumBorder(),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 40),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[
                  Icon(
                    icon,
                    size: 15,
                    color: selected ? scheme.onPrimary : scheme.onSurface,
                  ),
                  const SizedBox(width: 6),
                ],
                Text(
                  label,
                  maxLines: 1,
                  softWrap: false,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    fontSize: 12.5,
                    color: selected ? scheme.onPrimary : scheme.onSurface,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FloatingCircleButton extends StatelessWidget {
  const _FloatingCircleButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
    this.primary = false,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback onPressed;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: primary ? scheme.primary : scheme.surfaceContainerLowest,
      elevation: 3,
      shadowColor: Colors.black.withValues(alpha: 0.16),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: primary ? scheme.primary : scheme.outlineVariant,
        ),
      ),
      child: IconButton(
        tooltip: tooltip,
        constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
        onPressed: onPressed,
        icon: Icon(
          icon,
          size: 20,
          color: primary ? scheme.onPrimary : scheme.onSurface,
        ),
      ),
    );
  }
}

class _GpsIssueBanner extends StatelessWidget {
  const _GpsIssueBanner({required this.issue, required this.onAction});

  final LocationIssue issue;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final (title, actionLabel) = switch (issue) {
      LocationIssue.serviceDisabled => (
        'Location is turned off. Enable GPS to sort by distance.',
        'Settings',
      ),
      LocationIssue.denied => (
        'Allow location access to see distance from you.',
        'Allow',
      ),
      LocationIssue.deniedForever => (
        'Location permission is blocked in Settings.',
        'Settings',
      ),
      LocationIssue.unavailable => (
        'Could not determine your GPS location.',
        'Retry',
      ),
    };

    return Material(
      color: scheme.tertiaryContainer,
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: scheme.tertiary.withValues(alpha: 0.3)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
        child: Row(
          children: [
            Icon(
              Icons.location_off_outlined,
              size: 18,
              color: scheme.onTertiaryContainer,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                title,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: scheme.onTertiaryContainer,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 8),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 40),
                padding: const EdgeInsets.symmetric(horizontal: 12),
              ),
              onPressed: onAction,
              child: Text(actionLabel),
            ),
          ],
        ),
      ),
    );
  }
}

class _BranchPinMarker extends StatelessWidget {
  const _BranchPinMarker({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final BranchDistance item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final branch = item.branch;
    final openColour = statusColour(context, 'completed');
    final pinFill = selected
        ? scheme.primary
        : (branch.isOpen
              ? scheme.surfaceContainerLowest
              : scheme.surfaceContainerHigh);
    final innerDot = selected
        ? (dark ? const Color(0xFFFFC94A) : const Color(0xFFF8BF0B))
        : (branch.isOpen ? openColour : scheme.error);

    final label = item.distanceKm == null
        ? branch.shortName
        : '${branch.shortName} · ${item.distanceKm!.toStringAsFixed(1)} km';

    return Semantics(
      button: true,
      selected: selected,
      label: '${branch.name}, ${branch.isOpen ? 'Open' : 'Closed'}',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            if (selected)
              Container(
                margin: const EdgeInsets.only(bottom: 3),
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(
                  color: scheme.primary,
                  borderRadius: BorderRadius.circular(999),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.22),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: scheme.onPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            SizedBox(
              width: 48,
              height: 44,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Container(
                    width: selected ? 36 : 30,
                    height: selected ? 36 : 30,
                    decoration: BoxDecoration(
                      color: pinFill,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: selected ? scheme.onPrimary : scheme.primary,
                        width: selected ? 2.4 : 1.8,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.2),
                          blurRadius: 6,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Center(
                      child: Container(
                        width: selected ? 12 : 10,
                        height: selected ? 12 : 10,
                        decoration: BoxDecoration(
                          color: innerDot,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                  ),
                  if (branch.open24Hours)
                    Positioned(
                      right: 0,
                      top: 0,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 4,
                          vertical: 1,
                        ),
                        decoration: BoxDecoration(
                          color: dark
                              ? const Color(0xFFFFC94A)
                              : const Color(0xFFF8BF0B),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: scheme.surfaceContainerLowest,
                            width: 1.2,
                          ),
                        ),
                        child: Text(
                          '24h',
                          style: TextStyle(
                            fontSize: 8.5,
                            fontWeight: FontWeight.w800,
                            color: dark
                                ? const Color(0xFF3D2800)
                                : const Color(0xFF000B88),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ClusterMarker extends StatelessWidget {
  const _ClusterMarker({required this.count, required this.onTap});

  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      button: true,
      label: 'Cluster of $count branches. Tap to zoom in.',
      child: GestureDetector(
        onTap: onTap,
        child: Center(
          child: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: scheme.primary,
              shape: BoxShape.circle,
              border: Border.all(color: scheme.onPrimary, width: 2.5),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.24),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Center(
              child: Text(
                '$count',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: scheme.onPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _UserLocationDot extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Your current location',
      child: Center(
        child: Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: const Color(0xFF2563EB).withValues(alpha: 0.18),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Container(
              width: 14,
              height: 14,
              decoration: BoxDecoration(
                color: const Color(0xFF2563EB),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2.5),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Straight-line distance only: there is no routing data, so no travel time is shown.
class _DistanceChip extends StatelessWidget {
  const _DistanceChip({required this.item});

  final BranchDistance item;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = '${item.distanceKm!.toStringAsFixed(1)} km';

    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: scheme.primary, width: 1.2),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.14),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
        child: Text(
          text,
          maxLines: 1,
          style: Theme.of(context).textTheme.labelSmall
              ?.copyWith(color: scheme.primary, fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}

class _BranchSkeletonCard extends StatelessWidget {
  const _BranchSkeletonCard();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final bar = scheme.outlineVariant.withValues(alpha: 0.45);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Container(
                    height: 16,
                    decoration: BoxDecoration(
                      color: bar,
                      borderRadius: BorderRadius.circular(6),
                    ),
                  ),
                ),
                const SizedBox(width: 48),
                Container(
                  width: 48,
                  height: 16,
                  decoration: BoxDecoration(
                    color: bar,
                    borderRadius: BorderRadius.circular(6),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Container(
              height: 12,
              width: 220,
              decoration: BoxDecoration(
                color: bar,
                borderRadius: BorderRadius.circular(6),
              ),
            ),
            const SizedBox(height: 12),
            Container(
              height: 24,
              width: 140,
              decoration: BoxDecoration(
                color: bar,
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyFilterCard extends StatelessWidget {
  const _EmptyFilterCard({required this.query, required this.onReset});

  final String query;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                PhosphorIconsRegular.magnifyingGlass,
                color: scheme.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'No branches match those filters',
              style: theme.textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 6),
            Text(
              query.isNotEmpty
                  ? 'No branch found for "$query".'
                  : 'Clear filters to view all available branch locations.',
              style: theme.textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: onReset,
              child: const Text('Show all branches'),
            ),
          ],
        ),
      ),
    );
  }
}

class _BranchDetailSheet extends StatefulWidget {
  const _BranchDetailSheet({
    required this.item,
    required this.isNearest,
    required this.onDirections,
    required this.onCopyAddress,
    required this.onShare,
    this.onCall,
  });

  final BranchDistance item;
  final bool isNearest;
  final VoidCallback onDirections;
  final VoidCallback? onCall;
  final VoidCallback onCopyAddress;
  final VoidCallback onShare;

  @override
  State<_BranchDetailSheet> createState() => _BranchDetailSheetState();
}

class _BranchDetailSheetState extends State<_BranchDetailSheet> {
  bool _hoursExpanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;
    final sky = skySlab(scheme);
    final item = widget.item;
    final branch = item.branch;
    final hoursRange = branch.formattedHoursRange;

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewPaddingOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 8, 10),
            child: Column(
              children: [
                Center(
                  child: Container(
                    width: 38,
                    height: 4,
                    decoration: BoxDecoration(
                      color: scheme.outline.withValues(alpha: 0.45),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Wrap(
                            spacing: 6,
                            runSpacing: 4,
                            children: [
                              _BranchStatusPill(branch: branch),
                              if (widget.isNearest)
                                _BadgePill(
                                  label: 'Nearest Branch',
                                  background: scheme.primaryContainer,
                                  foreground: scheme.onPrimaryContainer,
                                ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(branch.name, style: theme.textTheme.titleLarge),
                          if (branch.fullAddress.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              branch.fullAddress,
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Close branch details',
                      constraints: const BoxConstraints(
                        minWidth: 48,
                        minHeight: 48,
                      ),
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(PhosphorIconsRegular.x),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Divider(height: 1, color: scheme.outlineVariant),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Storefront Card
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: sky.fill,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: scheme.outlineVariant),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _BadgePill(
                                label: 'EVERYDAY PHARMACY',
                                background: dark
                                    ? const Color(0xFFFFC94A)
                                    : const Color(0xFFF8BF0B),
                                foreground: dark
                                    ? const Color(0xFF3D2800)
                                    : const Color(0xFF000B88),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '${branch.shortName} Branch',
                                style: theme.textTheme.titleMedium?.copyWith(
                                  color: sky.ink,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                branch.formattedCoordinates,
                                style: theme.textTheme.labelSmall?.copyWith(
                                  color: sky.ink.withValues(alpha: 0.8),
                                ),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          PhosphorIconsRegular.storefront,
                          size: 38,
                          color: sky.ink,
                        ),
                      ],
                    ),
                  ),
                  if (item.distanceKm != null) ...[
                    const SizedBox(height: 12),
                    _MetricTile(
                      value: '${item.distanceKm!.toStringAsFixed(1)} km',
                      label: 'Straight-line distance',
                    ),
                  ],
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _QuickActionTile(
                          icon: PhosphorIconsRegular.navigationArrow,
                          label: 'Directions',
                          primary: true,
                          onTap: widget.onDirections,
                        ),
                      ),
                      if (widget.onCall != null) ...[
                        const SizedBox(width: 8),
                        Expanded(
                          child: _QuickActionTile(
                            icon: PhosphorIconsRegular.phone,
                            label: 'Call',
                            onTap: widget.onCall!,
                          ),
                        ),
                      ],
                      const SizedBox(width: 8),
                      Expanded(
                        child: _QuickActionTile(
                          icon: PhosphorIconsRegular.copy,
                          label: 'Copy',
                          onTap: widget.onCopyAddress,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _QuickActionTile(
                          icon: PhosphorIconsRegular.shareNetwork,
                          label: 'Share',
                          onTap: widget.onShare,
                        ),
                      ),
                    ],
                  ),
                  if (branch.description.isNotEmpty ||
                      branch.phone.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (branch.description.isNotEmpty)
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(
                                    PhosphorIconsRegular.compass,
                                    size: 16,
                                    color: scheme.primary,
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      branch.description,
                                      style: theme.textTheme.bodyMedium,
                                    ),
                                  ),
                                ],
                              ),
                            if (branch.description.isNotEmpty &&
                                branch.phone.isNotEmpty)
                              const Divider(height: 18),
                            if (branch.phone.isNotEmpty)
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Row(
                                      children: [
                                        Icon(
                                          PhosphorIconsRegular.phone,
                                          size: 16,
                                          color: scheme.onSurfaceVariant,
                                        ),
                                        const SizedBox(width: 8),
                                        Flexible(
                                          child: Text(
                                            'Branch Phone',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: theme.textTheme.labelLarge,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Flexible(
                                    child: Text(
                                      branch.phone,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      textAlign: TextAlign.end,
                                      style: theme.textTheme.titleSmall,
                                    ),
                                  ),
                                ],
                              ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Card(
                    child: Column(
                      children: [
                        InkWell(
                          borderRadius: BorderRadius.circular(appSurfaceRadius),
                          onTap: () =>
                              setState(() => _hoursExpanded = !_hoursExpanded),
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(minHeight: 48),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 10,
                              ),
                              child: Row(
                                children: [
                                  Icon(
                                    PhosphorIconsRegular.clock,
                                    size: 16,
                                    color: scheme.onSurface,
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      branch.open24Hours
                                          ? 'Opening Hours (24/7)'
                                          : 'Opening Hours',
                                      style: theme.textTheme.labelLarge,
                                    ),
                                  ),
                                  Icon(
                                    _hoursExpanded
                                        ? PhosphorIconsRegular.caretUp
                                        : PhosphorIconsRegular.caretDown,
                                    size: 16,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        if (_hoursExpanded) ...[
                          const Divider(height: 1),
                          Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      'Daily',
                                      style: theme.textTheme.bodySmall,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Text(
                                        hoursRange ??
                                            (branch.isOpen
                                                ? 'Open today'
                                                : 'Closed now'),
                                        textAlign: TextAlign.end,
                                        style: theme.textTheme.labelLarge,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'All times shown in Philippine Time (Asia/Manila).',
                                  style: theme.textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: FilledButton.icon(
              onPressed: widget.onDirections,
              icon: const Icon(PhosphorIconsRegular.navigationArrow, size: 18),
              label: Text('Get Directions to ${branch.shortName}'),
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(label, style: theme.textTheme.labelSmall),
        ],
      ),
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  const _QuickActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.primary = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Material(
      color: primary ? scheme.primaryContainer : scheme.surfaceContainerHigh,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 18,
                  color: primary ? scheme.onPrimaryContainer : scheme.onSurface,
                ),
                const SizedBox(height: 4),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: primary
                        ? scheme.onPrimaryContainer
                        : scheme.onSurface,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DirectionsChooserSheet extends StatelessWidget {
  const _DirectionsChooserSheet({
    required this.item,
    required this.onGoogleMaps,
    required this.onWaze,
    required this.onCopyAddress,
    required this.onCopyCoordinates,
  });

  final BranchDistance item;
  final VoidCallback onGoogleMaps;
  final VoidCallback onWaze;
  final VoidCallback onCopyAddress;
  final VoidCallback onCopyCoordinates;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final branch = item.branch;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        0,
        16,
        20 + MediaQuery.viewPaddingOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Navigate to ${branch.shortName}',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          if (branch.fullAddress.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(branch.fullAddress, style: theme.textTheme.bodySmall),
          ],
          if (item.distanceKm != null) ...[
            const SizedBox(height: 6),
            Text(
              '${item.distanceKm!.toStringAsFixed(1)} km away',
              style: theme.textTheme.labelLarge?.copyWith(
                color: scheme.primary,
              ),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: onGoogleMaps,
                  icon: const Icon(
                    PhosphorIconsRegular.navigationArrow,
                    size: 18,
                  ),
                  label: const Text('Google Maps'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton.tonalIcon(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, 52),
                    backgroundColor: scheme.primaryContainer,
                    foregroundColor: scheme.onPrimaryContainer,
                  ),
                  onPressed: onWaze,
                  icon: const Icon(PhosphorIconsRegular.car, size: 18),
                  label: const Text('Waze'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onCopyAddress,
                  icon: const Icon(PhosphorIconsRegular.copy, size: 16),
                  label: const Text('Copy address'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onCopyCoordinates,
                  icon: const Icon(PhosphorIconsRegular.mapPin, size: 16),
                  label: const Text('Copy GPS pin'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
