import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers/address_providers.dart';

/// The form a place name is compared in, so "Mandaue", "Mandaue City" and
/// "City of Mandaue" are one place. Mirrors the server's PhilippineLocations::key.
String placeKey(String name) => name
    .toLowerCase()
    .replaceAll(RegExp(r'\(.*?\)'), '')
    .replaceAll('ñ', 'n')
    .trim()
    .replaceAll(RegExp(r'^city of |\bcity$'), '')
    .replaceAll(RegExp(r'[^a-z0-9]'), '');

/// The listed spelling of [name], or null when the list does not have it.
String? matchPlace(Iterable<String> names, String name) {
  final key = placeKey(name);
  if (key.isEmpty) {
    return null;
  }
  for (final candidate in names) {
    if (placeKey(candidate) == key) {
      return candidate;
    }
  }
  return null;
}

/// Rewrites a looked-up province and city in their listed spelling. When only
/// one province has the city, that province wins over a missing or other one.
void matchPlaceNames(
  Map<String, List<String>> provinces,
  TextEditingController province,
  TextEditingController city,
) {
  final named = matchPlace(provinces.keys, province.text);
  final homes = [
    for (final entry in provinces.entries)
      if (matchPlace(entry.value, city.text) != null) entry.key,
  ];
  final listed = homes.length == 1 && !homes.contains(named)
      ? homes.single
      : named;
  if (listed == null) {
    return;
  }
  province.text = listed;
  city.text = matchPlace(provinces[listed]!, city.text) ?? city.text;
}

/// Province, city/municipality and barangay, each picked from a searchable
/// list narrowed by the one before it. Names already in the boxes (saved or
/// looked up) stay, and a name missing from the list can still be typed.
class PlaceFields extends ConsumerWidget {
  const PlaceFields({
    super.key,
    required this.province,
    required this.city,
    required this.barangay,
  });

  final TextEditingController province;
  final TextEditingController city;
  final TextEditingController barangay;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Future<Map<String, List<String>>> provinces() async {
      try {
        return await ref.read(provincesProvider.future);
      } catch (_) {
        // Try again on the next tap instead of keeping the failure.
        ref.invalidate(provincesProvider);
        rethrow;
      }
    }

    // Rebuilds when a box changes, so City and Barangay unlock once the box
    // before them is filled.
    return ListenableBuilder(
      listenable: Listenable.merge([province, city]),
      builder: (context, _) => Column(
        children: [
          _PlaceField(
            controller: province,
            label: 'Province',
            hint: 'Metro Manila if in NCR',
            missing: 'Choose the province.',
            options: () async => (await provinces()).keys.toList(),
            onPicked: (picked) async {
              final cities = (await provinces())[picked] ?? const [];
              final kept = matchPlace(cities, city.text);
              if (kept == null) {
                city.clear();
                barangay.clear();
              } else {
                city.text = kept;
              }
            },
          ),
          const SizedBox(height: 10),
          _PlaceField(
            controller: city,
            label: 'City / municipality',
            missing: 'Choose the city or municipality.',
            enabled: province.text.trim().isNotEmpty,
            options: () async {
              final all = await provinces();
              return all[matchPlace(all.keys, province.text)] ?? const [];
            },
            onPicked: (_) async => barangay.clear(),
          ),
          const SizedBox(height: 10),
          _PlaceField(
            controller: barangay,
            label: 'Barangay',
            missing: 'Choose the barangay.',
            enabled: city.text.trim().isNotEmpty,
            options: () => ref.read(
              barangaysProvider((province.text.trim(), city.text.trim()))
                  .future,
            ),
          ),
        ],
      ),
    );
  }
}

class _PlaceField extends StatelessWidget {
  const _PlaceField({
    required this.controller,
    required this.label,
    required this.missing,
    required this.options,
    this.hint,
    this.enabled = true,
    this.onPicked,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final String missing;
  final bool enabled;
  final Future<List<String>> Function() options;

  /// Runs when the pick changes the place, to clear or fix the boxes after it.
  final Future<void> Function(String picked)? onPicked;

  Future<void> _open(BuildContext context) async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _PlaceSheet(label: label, options: options()),
    );
    if (picked == null) {
      return;
    }
    final changed = placeKey(picked) != placeKey(controller.text);
    controller.text = picked;
    if (changed) {
      await onPicked?.call(picked);
    }
  }

  @override
  Widget build(BuildContext context) => TextFormField(
    controller: controller,
    readOnly: true,
    enabled: enabled,
    onTap: () => _open(context),
    decoration: InputDecoration(
      labelText: label,
      hintText: hint,
      suffixIcon: const Icon(Icons.arrow_drop_down),
    ),
    validator: (value) => (value ?? '').trim().isEmpty ? missing : null,
  );
}

class _PlaceSheet extends StatefulWidget {
  const _PlaceSheet({required this.label, required this.options});

  final String label;
  final Future<List<String>> options;

  @override
  State<_PlaceSheet> createState() => _PlaceSheetState();
}

class _PlaceSheetState extends State<_PlaceSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final query = _query.trim();

    return Padding(
      padding: EdgeInsets.only(bottom: media.viewInsets.bottom),
      child: SizedBox(
        height: media.size.height * 0.7,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: TextField(
                autofocus: true,
                textCapitalization: TextCapitalization.words,
                decoration: InputDecoration(
                  labelText: 'Search ${widget.label.toLowerCase()}',
                  prefixIcon: const Icon(Icons.search),
                ),
                onChanged: (value) => setState(() => _query = value),
              ),
            ),
            Expanded(
              child: FutureBuilder<List<String>>(
                future: widget.options,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  final names = snapshot.data ?? const <String>[];
                  final needle = query.toLowerCase();
                  final shown = [
                    for (final name in names)
                      if (name.toLowerCase().contains(needle)) name,
                  ];
                  final typed =
                      query.isNotEmpty && matchPlace(names, query) == null;

                  return ListView(
                    children: [
                      if (snapshot.hasError || names.isEmpty)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                          child: Text(
                            snapshot.hasError
                                ? 'Could not load the list. Type the name above.'
                                : 'No list for this place yet. Type the name above.',
                          ),
                        ),
                      for (final name in shown)
                        ListTile(
                          title: Text(name),
                          onTap: () => Navigator.of(context).pop(name),
                        ),
                      if (typed)
                        ListTile(
                          leading: const Icon(Icons.edit_outlined),
                          title: Text('Use “$query”'),
                          onTap: () => Navigator.of(context).pop(query),
                        ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
