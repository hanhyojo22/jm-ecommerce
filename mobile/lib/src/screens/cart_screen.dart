import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/cart_providers.dart';
import '../widgets/common.dart';
import 'auth_gate.dart';

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
  bool _busy = false;
  String? _selectAttempt;

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

  /// Like a food order, everything in the cart is bought. Lines left
  /// unselected by an older app version are selected again here so the
  /// total and checkout always cover the whole cart.
  void _selectEverythingAvailable(Cart cart) {
    final available = cart.lines.where((line) => line.available).toList();
    if (_busy || available.every((line) => line.selected)) return;
    // Once per set of lines, so a refused request cannot retry in a loop.
    final attempt = available.map((line) => line.id).join(',');
    if (attempt == _selectAttempt) return;
    _selectAttempt = attempt;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _runCartAction(
        () => ref
            .read(cartProvider.notifier)
            .selectOnly(available.map((line) => line.id).toList()),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(cartProvider);
    final body = AsyncView(
      value: cart,
      onRetry: () => ref.invalidate(cartProvider),
      builder: (value) {
        // Covers every cart icon (home, product, store) pushing /cart for a guest.
        if (!ref.watch(isSignedInProvider)) {
          return const SignInPrompt(
            icon: PhosphorIconsRegular.tote,
            title: 'Sign in to start your cart',
            message:
                'Browse freely, then sign in to add medicines and check out.',
          );
        }
        if (value.lines.isEmpty) {
          return EmptyState(
            icon: Icons.shopping_bag_outlined,
            title: 'Your cart is empty',
            message: 'Medicines you add will appear here.',
            action: FilledButton(
              onPressed: _browseProducts,
              child: const Text('Shop medicines'),
            ),
          );
        }

        _selectEverythingAvailable(value);
        final theme = Theme.of(context);
        final scheme = theme.colorScheme;
        final itemCount = value.lines
            .where((line) => line.available)
            .fold<int>(0, (sum, line) => sum + line.quantity);

        return Column(
          children: [
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => _runCartAction(
                  () => ref.read(cartProvider.notifier).refresh(),
                ),
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
                  children: [
                    Material(
                      color: scheme.surfaceContainerLowest,
                      borderRadius: BorderRadius.circular(16),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _BranchLine(name: value.lines.first.storeName),
                          for (var i = 0; i < value.lines.length; i++) ...[
                            if (i > 0)
                              Divider(
                                height: 1,
                                indent: 16,
                                endIndent: 16,
                                color: scheme.outlineVariant,
                              ),
                            _CartLineRow(
                              line: value.lines[i],
                              onQuantity: _busy
                                  ? null
                                  : (quantity) => _updateQuantity(
                                      ref,
                                      value.lines[i],
                                      quantity,
                                    ),
                              onRemove: _busy
                                  ? null
                                  : () => _remove(ref, value.lines[i]),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            MediaQuery.withClampedTextScaling(
              maxScaleFactor: 1.3,
              child: SafeArea(
                top: false,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerLowest,
                    border: Border(
                      top: BorderSide(color: scheme.outlineVariant),
                    ),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Subtotal',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                            Text(
                              money(value.subtotal),
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.5,
                              ),
                            ),
                            Text(
                              '$itemCount ${itemCount == 1 ? 'item' : 'items'} · delivery fee at checkout',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      FilledButton(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(140, 52),
                          padding: const EdgeInsets.symmetric(horizontal: 22),
                          shape: const StadiumBorder(),
                        ),
                        onPressed: _busy || value.selectedCount == 0
                            ? null
                            : () => context.push('/checkout'),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text('Checkout'),
                            SizedBox(width: 8),
                            Icon(Icons.arrow_forward_rounded, size: 18),
                          ],
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
        _header(),
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

  Widget _header() {
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
            child: SizedBox(
              height: 48,
              child: Row(
                children: [
                  if (!widget.embedded && Navigator.canPop(context))
                    const BackButton(),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Cart',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
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

/// The one branch every item in the cart comes from.
class _BranchLine extends StatelessWidget {
  const _BranchLine({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
      child: Row(
        children: [
          // Inline icon, as on the checkout section headings.
          Icon(Icons.storefront_outlined, size: 20, color: scheme.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'From $name',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
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
    required this.onQuantity,
    required this.onRemove,
  });

  final CartLine line;
  final ValueChanged<int>? onQuantity;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final imageSize =
        MediaQuery.sizeOf(context).width < 360 ||
            MediaQuery.textScalerOf(context).scale(14) > 18
        ? 60.0
        : 76.0;

    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(16, 12, 6, 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Opacity(
            opacity: line.available ? 1 : 0.45,
            child: NetworkThumb(
              url: line.imageUrl,
              width: imageSize,
              height: imageSize,
              radius: 12,
              placeholderIcon: PhosphorIconsRegular.pill,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        line.productName,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          height: 1.3,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Remove ${line.productName}',
                      onPressed: onRemove,
                      icon: const Icon(Icons.delete_outline_rounded, size: 20),
                      color: scheme.onSurfaceVariant,
                      visualDensity: VisualDensity.compact,
                    ),
                  ],
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
                if (line.requiresPrescription) ...[
                  const SizedBox(height: 4),
                  const PrescriptionBadge(),
                ],
                if (!line.available) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Not available at this branch — remove it to check out',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          money(line.lineTotal),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.3,
                          ),
                        ),
                        if (line.quantity > 1)
                          Text(
                            '${money(line.unitPrice)} each',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                          ),
                      ],
                    ),
                    _CartQuantityControl(
                      quantity: line.quantity,
                      max: 100,
                      onChanged: line.available ? onQuantity : null,
                    ),
                  ],
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
    // The same pill as the product card's in-cart stepper.
    final style = IconButton.styleFrom(
      foregroundColor: scheme.onSecondaryContainer,
      disabledForegroundColor: scheme.onSecondaryContainer.withValues(
        alpha: 0.35,
      ),
      minimumSize: const Size(44, 44),
      padding: EdgeInsets.zero,
    );
    return DecoratedBox(
      decoration: ShapeDecoration(
        color: onChanged == null
            ? scheme.surfaceContainerHigh
            : scheme.secondaryContainer,
        shape: const StadiumBorder(),
      ),
      child: _stepper(context, style),
    );
  }

  Widget _stepper(BuildContext context, ButtonStyle style) {
    final scheme = Theme.of(context).colorScheme;
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
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      color: scheme.onSecondaryContainer,
                    ),
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
