import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/cart_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key, this.embedded = false, this.onBrowseProducts});

  /// When true the screen is rendered inside the shell's tab stack, which
  /// already supplies the scaffold and bottom navigation.
  final bool embedded;
  final VoidCallback? onBrowseProducts;

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  bool _editing = false;
  bool _busy = false;

  Future<void> _runCartAction(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } on ApiException catch (error) {
      _snack(error.friendly);
    } catch (_) {
      _snack('Could not update your cart. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _browseProducts() {
    if (widget.onBrowseProducts != null) {
      widget.onBrowseProducts!();
    } else if (context.canPop()) {
      context.pop();
    } else {
      context.go('/');
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(cartProvider);
    final body = AsyncView(
      value: cart,
      onRetry: () => ref.invalidate(cartProvider),
      builder: (value) {
        if (value.lines.isEmpty) {
          return EmptyState(
            icon: Icons.shopping_bag_outlined,
            title: 'Your cart is empty',
            message: 'Products you add from the catalog will appear here.',
            action: FilledButton(
              onPressed: _browseProducts,
              child: const Text('Browse products'),
            ),
          );
        }

        final theme = Theme.of(context);
        final scheme = theme.colorScheme;
        final available = value.lines.where((line) => line.available);
        final allSelected =
            available.isNotEmpty && available.every((line) => line.selected);
        final stores = <String, List<CartLine>>{};
        for (final line in value.lines) {
          stores.putIfAbsent(line.storeSlug, () => []).add(line);
        }

        final groups = stores.values.toList(growable: false);

        return Column(
          children: [
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _runCartAction(
                  () => ref.read(cartProvider.notifier).refresh(),
                ),
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.only(bottom: 16),
                  itemCount: stores.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 6),
                  itemBuilder: (context, index) {
                    final group = groups[index];

                    return ColoredBox(
                      color: scheme.surfaceContainerLowest,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _StoreHeader(
                            group: group,
                            onToggle: _busy
                                ? null
                                : (selected) =>
                                      _selectStore(ref, value, group, selected),
                          ),
                          for (final line in group)
                            _CartLineRow(
                              line: line,
                              editing: _editing,
                              onToggle: _busy
                                  ? null
                                  : (selected) =>
                                        _toggle(ref, value, line, selected),
                              onQuantity: _busy
                                  ? null
                                  : (quantity) =>
                                        _updateQuantity(ref, line, quantity),
                              onRemove: _busy ? null : () => _remove(ref, line),
                            ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ),
            // One-row marketplace footer: text scaling is capped like the
            // home search header so the checkout button never gets squeezed out.
            MediaQuery.withClampedTextScaling(
              maxScaleFactor: 1.3,
              child: SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsetsDirectional.fromSTEB(4, 8, 12, 8),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerLowest,
                    border: Border(
                      top: BorderSide(color: scheme.outlineVariant),
                    ),
                  ),
                  child: Row(
                    children: [
                      InkWell(
                        borderRadius: BorderRadius.circular(appSurfaceRadius),
                        onTap: (available.isEmpty || _busy)
                            ? null
                            : () => _selectAll(ref, value, !allSelected),
                        child: Padding(
                          padding: const EdgeInsetsDirectional.only(end: 8),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Checkbox(
                                value: allSelected,
                                onChanged: (available.isEmpty || _busy)
                                    ? null
                                    : (checked) => _selectAll(
                                        ref,
                                        value,
                                        checked ?? false,
                                      ),
                              ),
                              Text(
                                'All',
                                semanticsLabel:
                                    'Select all (${available.length})',
                                style: theme.textTheme.bodyLarge,
                              ),
                            ],
                          ),
                        ),
                      ),
                      Expanded(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: AlignmentDirectional.centerEnd,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text.rich(
                                TextSpan(
                                  text: 'Subtotal: ',
                                  children: [
                                    TextSpan(
                                      text: money(
                                        value.selectedCount == 0
                                            ? '0'
                                            : value.subtotal,
                                      ),
                                      style: TextStyle(
                                        color: scheme.primary,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ],
                                ),
                                style: theme.textTheme.titleMedium,
                              ),
                              Text(
                                '${value.selectedCount} ${value.selectedCount == 1 ? 'item' : 'items'} selected',
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      // Sized to its label (no flex) so it sits flush right;
                      // the cap keeps room for the subtotal on narrow phones.
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 160),
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            minimumSize: const Size(96, 48),
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                          ),
                          onPressed: _busy || value.selectedCount == 0
                              ? null
                              : () => context.push('/checkout'),
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              value.selectedCount == 0
                                  ? 'Checkout'
                                  : 'Checkout (${value.selectedCount})',
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );

    final content = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _header(cart.value),
        SizedBox(
          height: 2,
          child: _busy
              ? const LinearProgressIndicator(semanticsLabel: 'Updating cart')
              : null,
        ),
        Expanded(child: body),
      ],
    );
    return widget.embedded ? content : Scaffold(body: content);
  }

  Widget _header(Cart? cart) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value:
          (scheme.brightness == Brightness.dark
                  ? SystemUiOverlayStyle.light
                  : SystemUiOverlayStyle.dark)
              .copyWith(statusBarColor: Colors.transparent),
      child: Material(
        color: scheme.surfaceContainerLowest,
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(8, 4, 12, 4),
            child: Row(
              children: [
                if (!widget.embedded && Navigator.canPop(context))
                  const BackButton(),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Cart${cart == null || cart.lines.isEmpty ? '' : ' (${cart.lines.length})'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: !_busy && cart?.lines.isNotEmpty == true
                      ? () => setState(() => _editing = !_editing)
                      : null,
                  icon: Icon(
                    _editing
                        ? Icons.check_rounded
                        : Icons.delete_outline_rounded,
                    size: 22,
                  ),
                  label: Text(_editing ? 'Done' : 'Edit'),
                  style: TextButton.styleFrom(
                    foregroundColor: scheme.onSurface,
                    minimumSize: const Size(48, 48),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _toggle(
    WidgetRef ref,
    Cart cart,
    CartLine line,
    bool selected,
  ) async {
    final ids = cart.lines
        .where((item) => item.selected)
        .map((item) => item.id)
        .toSet();

    if (selected) {
      ids.add(line.id);
    } else {
      ids.remove(line.id);
    }

    await _runCartAction(() async {
      await ref.read(cartProvider.notifier).selectOnly(ids.toList());
    });
  }

  /// Selects or clears every buyable line of one store, keeping the rest of
  /// the cart's selection as it was.
  Future<void> _selectStore(
    WidgetRef ref,
    Cart cart,
    List<CartLine> group,
    bool selected,
  ) async {
    final ids = cart.lines
        .where((item) => item.selected)
        .map((item) => item.id)
        .toSet();
    final storeIds = group
        .where((line) => line.available)
        .map((line) => line.id);

    if (selected) {
      ids.addAll(storeIds);
    } else {
      ids.removeAll(storeIds);
    }

    await _runCartAction(() async {
      await ref.read(cartProvider.notifier).selectOnly(ids.toList());
    });
  }

  Future<void> _selectAll(WidgetRef ref, Cart cart, bool selected) async {
    await _runCartAction(() async {
      await ref
          .read(cartProvider.notifier)
          .selectOnly(
            selected
                ? cart.lines
                      .where((line) => line.available)
                      .map((line) => line.id)
                      .toList()
                : const [],
          );
    });
  }

  Future<void> _updateQuantity(
    WidgetRef ref,
    CartLine line,
    int quantity,
  ) async {
    await _runCartAction(() async {
      await ref.read(cartProvider.notifier).updateQuantity(line.id, quantity);
    });
  }

  Future<void> _remove(WidgetRef ref, CartLine line) async {
    await _runCartAction(() async {
      await ref.read(cartProvider.notifier).remove(line.id);
    });
  }

  void _snack(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }
}

class _StoreHeader extends StatelessWidget {
  const _StoreHeader({required this.group, required this.onToggle});

  final List<CartLine> group;
  final ValueChanged<bool>? onToggle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final store = group.first;
    final buyable = group.where((line) => line.available);
    final selectedCount = buyable.where((line) => line.selected).length;
    final bool? state = selectedCount == 0
        ? false
        : selectedCount == buyable.length
        ? true
        : null;

    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(4, 4, 4, 0),
      child: Row(
        children: [
          Semantics(
            label: 'Select ${store.storeName}',
            child: Checkbox(
              tristate: true,
              value: state,
              onChanged: buyable.isEmpty || onToggle == null
                  ? null
                  : (_) => onToggle!(state != true),
            ),
          ),
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(appSurfaceRadius),
              onTap: () => context.push('/store/${store.storeSlug}'),
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 48),
                child: Row(
                  children: [
                    StoreAvatar(
                      name: store.storeName,
                      storeSlug: store.storeSlug,
                      radius: 14,
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        store.storeName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Icon(
                      Icons.chevron_right_rounded,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CartLineRow extends StatelessWidget {
  const _CartLineRow({
    required this.line,
    required this.editing,
    required this.onToggle,
    required this.onQuantity,
    required this.onRemove,
  });

  final CartLine line;
  final bool editing;
  final ValueChanged<bool>? onToggle;
  final ValueChanged<int>? onQuantity;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final imageSize =
        MediaQuery.sizeOf(context).width < 360 ||
            MediaQuery.textScalerOf(context).scale(14) > 18
        ? 64.0
        : 100.0;

    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(4, 8, 12, 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: imageSize,
            child: Center(
              child: Checkbox(
                semanticLabel: 'Select ${line.productName}',
                value: line.selected,
                onChanged: line.available && onToggle != null
                    ? (value) => onToggle!(value ?? false)
                    : null,
              ),
            ),
          ),
          Opacity(
            opacity: line.available ? 1 : 0.45,
            child: NetworkThumb(
              url: line.imageUrl,
              width: imageSize,
              height: imageSize,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  line.productName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                    height: 1.25,
                  ),
                ),
                if (line.variantLabel?.trim().isNotEmpty == true) ...[
                  const SizedBox(height: 4),
                  Text(
                    line.variantLabel!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                ],
                if (!line.available) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Unavailable — not included in checkout',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    Text(
                      money(line.unitPrice),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: scheme.primary,
                      ),
                    ),
                    _CartQuantityControl(
                      quantity: line.quantity,
                      max: 100,
                      onChanged: line.available ? onQuantity : null,
                    ),
                  ],
                ),
                if (editing || !line.available)
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton.icon(
                      onPressed: onRemove,
                      icon: const Icon(Icons.delete_outline_rounded, size: 18),
                      label: const Text('Remove'),
                      style: TextButton.styleFrom(
                        foregroundColor: scheme.error,
                        minimumSize: const Size(48, 48),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CartQuantityControl extends StatelessWidget {
  const _CartQuantityControl({
    required this.quantity,
    required this.max,
    required this.onChanged,
  });

  final int quantity;
  final int max;
  final ValueChanged<int>? onChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final style = IconButton.styleFrom(
      backgroundColor: scheme.surfaceContainerLow,
      disabledBackgroundColor: scheme.surfaceContainerLow,
      foregroundColor: scheme.onSurfaceVariant,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(appSurfaceRadius),
      ),
      minimumSize: const Size(48, 48),
      padding: EdgeInsets.zero,
    );
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Decrease quantity',
          style: style,
          onPressed: onChanged != null && quantity > 1
              ? () => onChanged!(quantity - 1)
              : null,
          icon: const Icon(Icons.remove_rounded, size: 18),
        ),
        Flexible(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Semantics(
              liveRegion: true,
              label: 'Quantity $quantity',
              child: ExcludeSemantics(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    '$quantity',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
          ),
        ),
        IconButton(
          tooltip: 'Increase quantity',
          style: style,
          onPressed: onChanged != null && quantity < max
              ? () => onChanged!(quantity + 1)
              : null,
          icon: const Icon(Icons.add_rounded, size: 18),
        ),
      ],
    );
  }
}
