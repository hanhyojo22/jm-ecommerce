import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';

/// Opens the buyers' ratings of [store], newest first.
Future<void> showStoreRatings(BuildContext context, StoreInfo store) =>
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.7,
        maxChildSize: 0.95,
        builder: (context, controller) =>
            _StoreRatingsList(store: store, controller: controller),
      ),
    );

/// "★ 4.6 (128 ratings)", or a no-ratings line, matching product ratings.
String storeRatingLabel(StoreInfo store) {
  final count = store.ratingCount ?? 0;
  return count == 0
      ? 'No store ratings yet'
      : '${store.ratingAverage.toStringAsFixed(1)} ($count ${count == 1 ? 'rating' : 'ratings'})';
}

class _StoreRatingsList extends ConsumerStatefulWidget {
  const _StoreRatingsList({required this.store, required this.controller});

  final StoreInfo store;
  final ScrollController controller;

  @override
  ConsumerState<_StoreRatingsList> createState() => _StoreRatingsListState();
}

class _StoreRatingsListState extends ConsumerState<_StoreRatingsList> {
  final _ratings = <StoreRating>[];
  int _page = 0;
  int _lastPage = 1;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadMore();
  }

  Future<void> _loadMore() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await ref
          .read(apiClientProvider)
          .storeRatings(store: widget.store.slug, page: _page + 1);
      if (mounted) {
        setState(() {
          _ratings.addAll(page.ratings);
          _page = page.page;
          _lastPage = page.lastPage;
        });
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );

    return ListView(
      controller: widget.controller,
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      children: [
        Text(
          '${widget.store.name} ratings',
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 4),
        Row(
          children: [
            Icon(
              Icons.star_rounded,
              size: 18,
              color: theme.colorScheme.secondary,
            ),
            const SizedBox(width: 4),
            Text(storeRatingLabel(widget.store), style: muted),
          ],
        ),
        const Divider(height: 28),
        for (final rating in _ratings) ...[
          Row(
            children: [
              Expanded(
                child: Text(
                  rating.author,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Text(shortDate(rating.createdAt), style: muted),
            ],
          ),
          const SizedBox(height: 4),
          Semantics(
            label: '${rating.rating} out of 5 stars',
            excludeSemantics: true,
            child: Row(
              children: List.generate(
                5,
                (index) => Icon(
                  index < rating.rating
                      ? Icons.star_rounded
                      : Icons.star_outline_rounded,
                  size: 16,
                  color: theme.colorScheme.secondary,
                ),
              ),
            ),
          ),
          if (rating.comment != null && rating.comment!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(rating.comment!, style: theme.textTheme.bodyMedium),
          ],
          const Divider(height: 28),
        ],
        if (_loading)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_error != null)
          TextButton(onPressed: _loadMore, child: const Text('Retry ratings'))
        else if (_ratings.isEmpty)
          Text('No one has rated this store yet.', style: muted)
        else if (_page < _lastPage)
          TextButton(
            onPressed: _loadMore,
            child: const Text('Show more ratings'),
          ),
      ],
    );
  }
}
