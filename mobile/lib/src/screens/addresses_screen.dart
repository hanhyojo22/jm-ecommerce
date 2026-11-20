import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../core/api_exception.dart';
import '../core/location_service.dart';
import '../models/models.dart';
import '../providers/address_providers.dart';
import '../providers/auth_providers.dart';
import '../providers/store_locator_providers.dart';
import '../widgets/common.dart';

class AddressesScreen extends ConsumerWidget {
  const AddressesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final addresses = ref.watch(addressesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Delivery addresses')),
      body: AsyncView(
        value: addresses,
        onRetry: () => ref.invalidate(addressesProvider),
        builder: (items) {
          if (items.isEmpty) {
            return EmptyState(
              icon: Icons.location_on_outlined,
              title: 'No saved addresses',
              message: 'Add one now, or save it during checkout.',
              action: FilledButton(
                onPressed: () => _openForm(context, ref, null),
                child: const Text('Add address'),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(addressesProvider);
              await ref.read(addressesProvider.future);
            },
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: items.length,
              separatorBuilder: (context, index) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final address = items[index];

                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.fromLTRB(16, 10, 8, 10),
                    title: Text(
                      address.recipientName,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      '${address.addressLine}, ${address.city}, ${address.province} ${address.postalCode}\n${address.phone}',
                    ),
                    isThreeLine: true,
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          tooltip: 'Edit',
                          onPressed: () => _openForm(context, ref, address),
                          icon: const Icon(Icons.edit_outlined),
                        ),
                        IconButton(
                          tooltip: 'Delete',
                          onPressed: () => _delete(context, ref, address),
                          icon: const Icon(Icons.delete_outline_rounded),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
      floatingActionButton: (addresses.value?.isEmpty ?? true)
          ? null
          : FloatingActionButton.extended(
              onPressed: () => _openForm(context, ref, null),
              icon: const Icon(Icons.add_location_alt_outlined),
              label: const Text('Add address'),
            ),
    );
  }

  Future<void> _openForm(
    BuildContext context,
    WidgetRef ref,
    Address? existing,
  ) async {
    final address = await showModalBottomSheet<Address>(
      context: context,
      isScrollControlled: true,
      builder: (context) => AddressForm(initial: existing),
    );

    if (address == null) {
      return;
    }

    try {
      if (existing?.id == null) {
        await ref.read(apiClientProvider).createAddress(address);
      } else {
        await ref
            .read(apiClientProvider)
            .updateAddress(address.copyWith(id: existing!.id));
      }

      ref.invalidate(addressesProvider);

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              existing == null ? 'Address saved.' : 'Address updated.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.friendly)));
      }
    }
  }

  Future<void> _delete(
    BuildContext context,
    WidgetRef ref,
    Address address,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Remove address?'),
        content: Text(
          '${address.recipientName}, ${address.addressLine} will be removed.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );

    if (confirmed != true || address.id == null) {
      return;
    }

    try {
      await ref.read(apiClientProvider).deleteAddress(address.id!);
      ref.invalidate(addressesProvider);

      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Address removed.')));
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.friendly)));
      }
    }
  }
}

class AddressForm extends ConsumerStatefulWidget {
  const AddressForm({super.key, this.initial});

  final Address? initial;

  @override
  ConsumerState<AddressForm> createState() => _AddressFormState();
}

class _AddressFormState extends ConsumerState<AddressForm> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _line = TextEditingController();
  final _city = TextEditingController();
  final _province = TextEditingController();
  final _postal = TextEditingController();
  bool _locating = false;
  String? _locationError;

  /// Fills street, city, province and postal code from where the phone is.
  /// Parts the lookup could not find keep whatever the customer typed.
  Future<void> _fillFromLocation() async {
    final location = ref.read(locationServiceProvider);
    setState(() {
      _locating = true;
      _locationError = null;
    });
    try {
      if (await location.permission() == LocationPermission.denied) {
        await location.requestPermission();
      }
      final here = await location.currentPosition(fresh: true);
      final place = await ref
          .read(apiClientProvider)
          .lookupAddress(here.latitude, here.longitude);
      if (!mounted) {
        return;
      }
      for (final (field, key) in [
        (_line, 'address_line'),
        (_city, 'city'),
        (_province, 'province'),
        (_postal, 'postal_code'),
      ]) {
        final value = (place[key] as String? ?? '').trim();
        if (value.isNotEmpty) {
          field.text = value;
        }
      }
    } on LocationFailure catch (failure) {
      _locationError = switch (failure.issue) {
        LocationIssue.serviceDisabled =>
          'Turn on location on your phone, then try again.',
        LocationIssue.denied => 'Allow location to fill in your address.',
        LocationIssue.deniedForever =>
          'Location is off for this app. Turn it on in Settings.',
        LocationIssue.unavailable =>
          'Could not find your location. Type your address instead.',
      };
    } on ApiException catch (error) {
      _locationError = error.friendly;
    } finally {
      if (mounted) {
        setState(() => _locating = false);
      }
    }
  }

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;

    if (initial != null) {
      _recipient.text = initial.recipientName;
      _phone.text = initial.phone;
      _line.text = initial.addressLine;
      _city.text = initial.city;
      _province.text = initial.province;
      _postal.text = initial.postalCode;
    }
  }

  @override
  void dispose() {
    _recipient.dispose();
    _phone.dispose();
    _line.dispose();
    _city.dispose();
    _province.dispose();
    _postal.dispose();
    super.dispose();
  }

  Widget _cityProvinceFields(BuildContext context) {
    final city = TextFormField(
      controller: _city,
      textCapitalization: TextCapitalization.words,
      decoration: const InputDecoration(labelText: 'City'),
      validator: (value) =>
          (value ?? '').trim().isEmpty ? 'Enter the city.' : null,
    );
    final province = TextFormField(
      controller: _province,
      textCapitalization: TextCapitalization.words,
      decoration: const InputDecoration(labelText: 'Province (optional)'),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final stack =
            constraints.maxWidth < 300 ||
            MediaQuery.textScalerOf(context).scale(14) > 21;

        if (stack) {
          return Column(children: [city, const SizedBox(height: 10), province]);
        }

        return Row(
          children: [
            Expanded(child: city),
            const SizedBox(width: 10),
            Expanded(child: province),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.initial == null ? 'Add address' : 'Edit address',
                style: Theme.of(context).textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _locating ? null : _fillFromLocation,
                icon: _locating
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.my_location, size: 18),
                label: Text(
                  _locating
                      ? 'Finding your address…'
                      : 'Use my current location',
                ),
              ),
              if (_locationError != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    _locationError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 13,
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _recipient,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Recipient name'),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Enter the recipient name.'
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone',
                  hintText: '09xxxxxxxxx',
                ),
                validator: (value) =>
                    RegExp(r'^(?:\+63|0)9\d{9}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Use a valid PH mobile number.',
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _line,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Street address'),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? 'Enter the street address.'
                    : null,
              ),
              const SizedBox(height: 10),
              _cityProvinceFields(context),
              const SizedBox(height: 10),
              TextFormField(
                controller: _postal,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Postal code (optional)',
                ),
                validator: (value) =>
                    (value ?? '').trim().isEmpty ||
                        RegExp(r'^\d{4}$').hasMatch((value ?? '').trim())
                    ? null
                    : 'Use a 4-digit postal code.',
              ),
              const SizedBox(height: 18),
              FilledButton(
                onPressed: () {
                  if (!(_formKey.currentState?.validate() ?? false)) {
                    return;
                  }

                  Navigator.of(context).pop(
                    Address(
                      id: widget.initial?.id,
                      recipientName: _recipient.text.trim(),
                      phone: _phone.text.trim(),
                      addressLine: _line.text.trim(),
                      city: _city.text.trim(),
                      province: _province.text.trim(),
                      postalCode: _postal.text.trim(),
                    ),
                  );
                },
                child: Text(
                  widget.initial == null ? 'Save address' : 'Update address',
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
