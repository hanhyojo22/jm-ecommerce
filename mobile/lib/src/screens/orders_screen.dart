import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/order_providers.dart';
import '../widgets/common.dart';
import '../widgets/shell.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen>
    with SingleTickerProviderStateMixin {
  static const _tabs = [
    (key: 'all', label: 'All'),
    (key: 'to_pay', label: 'To pay'),
    (key: 'to_dispatch', label: 'To ship'),
    (key: 'to_receive', label: 'To receive'),
    (key: 'to_review', label: 'To review'),
    (key: 'delivered', label: 'Delivered'),
    (key: 'returns', label: 'Returns'),
  ];

  late final TabController _tabController;
  final _searchController = TextEditingController();
  final _searchFocus = FocusNode();
  Timer? _searchDebounce;
  String _tab = 'all';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _tabController.dispose();
    _searchController.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  void _selectTab(int index) {
    final nextKey = _tabs[index].key;
    if (_tabController.index != index) {
      _tabController.animateTo(index);
    }
    ref.read(ordersTabProvider.notifier).select(nextKey);
    if (_tab != nextKey) {
      setState(() => _tab = nextKey);
    }
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 300), () {
      if (mounted) {
        ref.read(ordersSearchProvider.notifier).setQuery(value);
      }
    });
  }

  void _submitSearch(String value) {
    _searchDebounce?.cancel();
    _searchFocus.unfocus();
    ref.read(ordersSearchProvider.notifier).setQuery(value);
  }

  void _clearSearch() {
    _searchDebounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(ordersSearchProvider.notifier).setQuery('');
  }

  Future<void> _showFilterSheet(Map<String, int> tabCounts) async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) {
        final theme = Theme.of(context);
        final scheme = theme.colorScheme;

        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Filter orders',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (_tab != 'all' || _searchController.text.isNotEmpty)
                    TextButton(
                      onPressed: () => Navigator.of(context).pop('__reset__'),
                      child: const Text('Reset'),
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final tab in _tabs)
                    ChoiceChip(
                      label: Text(switch (tabCounts[tab.key] ?? 0) {
                        0 => tab.label,
                        final count => '${tab.label} ($count)',
                      }),
                      selected: _tab == tab.key,
                      selectedColor: scheme.primary,
                      labelStyle: TextStyle(
                        color: _tab == tab.key
                            ? scheme.onPrimary
                            : scheme.onSurface,
                        fontWeight: _tab == tab.key
                            ? FontWeight.w700
                            : FontWeight.w500,
                      ),
                      onSelected: (_) => Navigator.of(context).pop(tab.key),
                    ),
                ],
              ),
            ],
          ),
        );
      },
    );

    if (!mounted || selected == null) {
      return;
    }
    if (selected == '__reset__') {
      _clearSearch();
      _selectTab(0);
      return;
    }
    final index = _tabs.indexWhere((tab) => tab.key == selected);
    if (index >= 0) {
      _selectTab(index);
    }
  }

  @override
  Widget build(BuildContext context) {
    final requestedTab = ref.watch(ordersTabProvider);
    final requestedIndex = _tabs.indexWhere((t) => t.key == requestedTab);
    if (requestedIndex >= 0 && _tab != requestedTab) {
      _tab = requestedTab;
      if (_tabController.index != requestedIndex) {
        _tabController.index = requestedIndex;
      }
    }
    final orders = ref.watch(ordersProvider(_tab));
    final searchQuery = ref.watch(ordersSearchProvider);
    final scheme = Theme.of(context).colorScheme;
    final tabCounts = orders.value?.tabCounts ?? const <String, int>{};

    return Column(
      children: [
        _OrdersTopBar(
          controller: _searchController,
          focusNode: _searchFocus,
          hasActiveFilter: _tab != 'all' || searchQuery.isNotEmpty,
          onChanged: _onSearchChanged,
          onSubmitted: _submitSearch,
          onClear: _clearSearch,
          onFilterTap: () => _showFilterSheet(tabCounts),
        ),
        DecoratedBox(
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLowest,
            border: Border(bottom: BorderSide(color: scheme.outlineVariant)),
          ),
          child: TabBar(
            controller: _tabController,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            dividerHeight: 0,
            indicatorSize: TabBarIndicatorSize.label,
            indicatorWeight: 3,
            indicatorColor: scheme.primary,
            labelColor: scheme.primary,
            labelPadding: const EdgeInsets.symmetric(horizontal: 14),
            labelStyle: const TextStyle(
              fontFamily: 'Roboto',
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
            unselectedLabelStyle: const TextStyle(
              fontFamily: 'Roboto',
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
            unselectedLabelColor: scheme.onSurface,
            onTap: _selectTab,
            tabs: [
              for (final tab in _tabs)
                Tab(
                  height: 42,
                  text: switch (tabCounts[tab.key] ?? 0) {
                    0 => tab.label,
                    final count => '${tab.label} ($count)',
                  },
                ),
            ],
          ),
        ),
        Expanded(
          child: AsyncView(
            value: orders,
            onRetry: () => ref.invalidate(ordersProvider(_tab)),
            builder: (result) {
              if (result.orders.isEmpty) {
                return EmptyState(
                  icon: Icons.receipt_long_outlined,
                  title: searchQuery.isNotEmpty
                      ? 'No matching orders'
                      : 'No orders here',
                  message: searchQuery.isNotEmpty
                      ? 'No orders matched "$searchQuery". Try another store or product name.'
                      : 'Orders you place will appear in this tab.',
                  action: searchQuery.isNotEmpty
                      ? OutlinedButton(
                          onPressed: _clearSearch,
                          child: const Text('Clear search'),
                        )
                      : FilledButton(
                          onPressed: () =>
                              ref.read(shellTabProvider.notifier).select(0),
                          child: const Text('Start shopping'),
                        ),
                );
              }

              return RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(ordersProvider(_tab));
                  await ref.read(ordersProvider(_tab).future);
                },
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: result.orders.length,
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: 8),
                  itemBuilder: (context, index) =>
                      _OrderCard(order: result.orders[index]),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

/// Lazada-style top bar: "My Orders" title, inline search box ("Search by
/// seller name..."), and a trailing "Filter" button.
class _OrdersTopBar extends StatelessWidget {
  const _OrdersTopBar({
    required this.controller,
    required this.focusNode,
    required this.hasActiveFilter,
    required this.onChanged,
    required this.onSubmitted,
    required this.onClear,
    required this.onFilterTap,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final bool hasActiveFilter;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onClear;
  final VoidCallback onFilterTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;

    final title = Text(
      'My Orders',
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: theme.textTheme.titleMedium?.copyWith(
        fontSize: largeText ? 16 : 18,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.3,
      ),
    );

    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    final searchBox = ListenableBuilder(
      listenable: Listenable.merge([controller, focusNode]),
      builder: (context, child) {
        final focused = focusNode.hasFocus;
        final hasText = controller.text.isNotEmpty;

        return AnimatedContainer(
          duration: reduceMotion
              ? Duration.zero
              : const Duration(milliseconds: 180),
          curve: Curves.easeOutCubic,
          height: 38,
          decoration: BoxDecoration(
            color: focused
                ? scheme.surfaceContainerLowest
                : scheme.surfaceContainerHighest.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: focused
                  ? scheme.primary
                  : scheme.outlineVariant.withValues(alpha: 0.7),
              width: focused ? 1.5 : 1,
            ),
          ),
          alignment: Alignment.center,
          child: Semantics(
            label: 'Search orders by seller or product',
            child: TextField(
              controller: controller,
              focusNode: focusNode,
              textInputAction: TextInputAction.search,
              onChanged: onChanged,
              onSubmitted: onSubmitted,
              onTapOutside: (_) => focusNode.unfocus(),
              style: theme.textTheme.bodyMedium?.copyWith(
                fontSize: 13.5,
                fontWeight: FontWeight.w600,
              ),
              decoration: InputDecoration(
                isDense: true,
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 8),
                prefixIcon: Icon(
                  Icons.search_rounded,
                  size: 18,
                  color: focused || hasText
                      ? scheme.primary
                      : scheme.onSurfaceVariant,
                ),
                prefixIconConstraints: const BoxConstraints(
                  minWidth: 34,
                  minHeight: 34,
                ),
                hintText: 'Search by seller name…',
                hintStyle: theme.textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                  fontSize: 13,
                ),
                suffixIcon: !hasText
                    ? null
                    : IconButton(
                        tooltip: 'Clear search',
                        padding: EdgeInsets.zero,
                        visualDensity: VisualDensity.compact,
                        icon: Icon(
                          Icons.cancel_rounded,
                          size: 16,
                          color: scheme.onSurfaceVariant,
                        ),
                        onPressed: onClear,
                      ),
                suffixIconConstraints: const BoxConstraints(
                  minWidth: 32,
                  minHeight: 32,
                ),
              ),
            ),
          ),
        );
      },
    );

    final filterButton = InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: onFilterTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.filter_alt_outlined,
              size: 18,
              color: hasActiveFilter ? scheme.primary : scheme.onSurface,
            ),
            const SizedBox(width: 3),
            Text(
              'Filter',
              style: theme.textTheme.labelLarge?.copyWith(
                fontSize: 13,
                fontWeight: hasActiveFilter ? FontWeight.w800 : FontWeight.w600,
                color: hasActiveFilter ? scheme.primary : scheme.onSurface,
              ),
            ),
          ],
        ),
      ),
    );

    return DecoratedBox(
      decoration: BoxDecoration(color: scheme.surfaceContainerLowest),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(16, largeText ? 6 : 10, 12, 6),
          child: largeText
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(child: title),
                        const SizedBox(width: 8),
                        Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: filterButton,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    searchBox,
                  ],
                )
              : Row(
                  children: [
                    title,
                    const SizedBox(width: 10),
                    Expanded(child: searchBox),
                    const SizedBox(width: 4),
                    filterButton,
                  ],
                ),
        ),
      ),
    );
  }
}

/// Lazada-style full-width order card:
/// 1. Store header row (store icon + bold store name + chevron on left, colored status on right)
/// 2. Product rows with thumbnail, 2-line title, gray variant chip, service tags, price & Qty
/// 3. Right-aligned "Total(N Item): ₱..." summary
/// 4. Bottom action bar with "More"/order number on left and outlined/primary buttons on right
class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final SellerOrder order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final largeText = MediaQuery.textScalerOf(context).scale(14) > 18;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: scheme.onSurfaceVariant,
    );
    final itemCount = order.lines.fold<int>(
      0,
      (sum, line) => sum + line.quantity,
    );
    final openOrder = order.orderId == null
        ? null
        : () => context.push('/orders/${order.orderId}');
    final openStore = order.store?.slug != null && order.store!.slug.isNotEmpty
        ? () => context.push('/store/${order.store!.slug}')
        : openOrder;

    // On normal text scale, show up to 3 full Lazada item rows like the Lazada
    // My Orders screen; on accessibility large-text scale, show the lead row +
    // compact thumbnail strip so the card fits small screens cleanly.
    final visibleLines = largeText
        ? order.lines.take(1).toList(growable: false)
        : order.lines.take(3).toList(growable: false);
    final hiddenLines = order.lines.skip(visibleLines.length).toList();

    return Material(
      color: scheme.surfaceContainerLowest,
      child: InkWell(
        onTap: openOrder,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: InkWell(
                      borderRadius: BorderRadius.circular(6),
                      onTap: openStore,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            StoreAvatar(
                              name: order.store?.name ?? 'Store',
                              logoUrl: order.store?.logoUrl,
                              radius: 14,
                            ),
                            const SizedBox(width: 7),
                            Flexible(
                              child: Text(
                                order.store?.name ?? 'Store',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14.5,
                                ),
                              ),
                            ),
                            const SizedBox(width: 2),
                            Icon(
                              Icons.chevron_right_rounded,
                              size: 19,
                              color: scheme.onSurfaceVariant,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: AlignmentDirectional.centerEnd,
                      child: StatusPill(status: order.status),
                    ),
                  ),
                ],
              ),
              for (final line in visibleLines) ...[
                const SizedBox(height: 12),
                _OrderLineRow(
                  line: line,
                  status: order.status,
                  largeText: largeText,
                ),
              ],
              if (hiddenLines.isNotEmpty) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    for (final line in hiddenLines.take(3))
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: 6),
                        child: NetworkThumb(
                          url: line.imageUrl,
                          width: 40,
                          height: 40,
                          radius: 6,
                        ),
                      ),
                    Flexible(
                      child: Text(
                        '+${hiddenLines.length} more',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: muted,
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 12),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: Text.rich(
                  TextSpan(
                    text:
                        'Total($itemCount ${itemCount == 1 ? 'Item' : 'Items'}): ',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 13.5,
                      color: scheme.onSurface,
                    ),
                    children: [
                      TextSpan(
                        text: money(order.total),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          color: scheme.onSurface,
                        ),
                      ),
                    ],
                  ),
                  textAlign: TextAlign.end,
                ),
              ),
              const SizedBox(height: 12),
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 8,
                runSpacing: 8,
                children: [
                  Text(
                    [
                      if (order.orderShortNumber != null)
                        order.orderShortNumber!,
                      if (order.orderCreatedAt != null)
                        shortDate(order.orderCreatedAt),
                    ].join(' · '),
                    style: muted,
                  ),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (!largeText && !order.canConfirmReceipt)
                        _OrderAction(
                          label: order.status == 'delivered'
                              ? 'Return/Refund'
                              : 'Order details',
                          emphasised: false,
                          accentOutline: false,
                          onPressed: openOrder,
                        ),
                      _OrderAction(
                        label: order.canConfirmReceipt
                            ? 'Tap to confirm receipt'
                            : order.status == 'delivered'
                            ? 'Buy again'
                            : 'View order',
                        emphasised: order.canConfirmReceipt,
                        accentOutline: !order.canConfirmReceipt,
                        onPressed: openOrder,
                      ),
                    ],
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

class _OrderLineRow extends StatelessWidget {
  const _OrderLineRow({
    required this.line,
    required this.status,
    required this.largeText,
  });

  final OrderLine line;
  final String status;
  final bool largeText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final thumbSize = largeText ? 68.0 : 80.0;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        NetworkThumb(
          url: line.imageUrl,
          width: thumbSize,
          height: thumbSize,
          radius: 6,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                line.productName,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w500,
                  height: 1.25,
                ),
              ),
              if (line.variantLabel != null &&
                  line.variantLabel!.trim().isNotEmpty) ...[
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: scheme.surfaceContainerHighest.withValues(
                      alpha: 0.65,
                    ),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    line.variantLabel!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 6),
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 8,
                runSpacing: 2,
                children: [
                  Text(
                    money(line.unitPrice),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                  Text(
                    'Qty: ${line.quantity}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurface,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _OrderAction extends StatelessWidget {
  const _OrderAction({
    required this.label,
    required this.emphasised,
    this.accentOutline = false,
    required this.onPressed,
  });

  final String label;
  final bool emphasised;
  final bool accentOutline;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    const size = Size(0, 36);
    const padding = EdgeInsets.symmetric(horizontal: 14);
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(6),
    );

    if (emphasised) {
      return FilledButton(
        style: FilledButton.styleFrom(
          minimumSize: size,
          padding: padding,
          shape: shape,
          tapTargetSize: MaterialTapTargetSize.padded,
        ),
        onPressed: onPressed,
        child: Text(label),
      );
    }

    return OutlinedButton(
      style: OutlinedButton.styleFrom(
        minimumSize: size,
        padding: padding,
        shape: shape,
        foregroundColor: accentOutline ? scheme.primary : scheme.onSurface,
        side: BorderSide(
          color: accentOutline ? scheme.primary : scheme.outlineVariant,
        ),
        tapTargetSize: MaterialTapTargetSize.padded,
      ),
      onPressed: onPressed,
      child: Text(label),
    );
  }
}
