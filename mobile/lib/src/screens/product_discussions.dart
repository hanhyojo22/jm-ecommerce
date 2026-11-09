import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_exception.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/catalog_providers.dart';

class ProductDiscussions extends ConsumerStatefulWidget {
  const ProductDiscussions({super.key, required this.productKey});

  final ProductKey productKey;

  @override
  ConsumerState<ProductDiscussions> createState() => _ProductDiscussionsState();
}

class _ProductDiscussionsState extends ConsumerState<ProductDiscussions> {
  final _composer = TextEditingController();
  final List<_PendingDiscussion> _pending = [];
  int _page = 1;
  int _nextId = 0;
  bool _canPost = false;

  void _onComposerChanged(String value) {
    final canPost = value.trim().isNotEmpty;

    if (canPost != _canPost) {
      setState(() => _canPost = canPost);
    }
  }

  @override
  void dispose() {
    _composer.dispose();
    super.dispose();
  }

  Future<void> _reply(ProductDiscussion parent) async {
    var draft = '';
    final body = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Reply to ${parent.author}'),
        content: TextField(
          onChanged: (value) => draft = value,
          autofocus: true,
          maxLength: 2000,
          maxLines: 4,
          decoration: const InputDecoration(labelText: 'Your reply'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, draft),
            child: const Text('Post reply'),
          ),
        ],
      ),
    );
    if (body != null && mounted) _post(body, parent: parent);
  }

  Future<void> _post(String value, {ProductDiscussion? parent}) async {
    final body = value.trim();
    if (body.isEmpty || body.length > 2000) return;
    final pending = _PendingDiscussion(
      localId: ++_nextId,
      rootId: parent?.rootId,
      parentId: parent?.id,
      body: body,
    );
    setState(() {
      if (parent == null) {
        _page = 1;
        _composer.clear();
        _canPost = false;
      }
      _pending.add(pending);
    });
    try {
      final saved = await ref
          .read(apiClientProvider)
          .postProductDiscussion(
            store: widget.productKey.store,
            product: widget.productKey.slug,
            body: body,
            parentId: parent?.id,
          );
      if (!mounted) return;
      setState(() => pending.serverId = saved.id);
      ref.invalidate(
        productDiscussionsProvider((product: widget.productKey, page: _page)),
      );
    } on ApiException catch (error) {
      if (mounted) {
        setState(
          () => pending.error = error.code == 'network.error'
              ? 'Could not confirm. Refresh the discussion before retrying.'
              : error.friendly,
        );
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => pending.error =
              'Could not confirm. Refresh the discussion before retrying.',
        );
      }
    }
  }

  Widget _pendingTile(_PendingDiscussion item) => ListTile(
    key: ValueKey('pending-discussion-${item.localId}'),
    contentPadding: EdgeInsets.only(left: item.parentId == null ? 0 : 20),
    title: const Text('You'),
    subtitle: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(item.body),
        Text(
          item.error ?? (item.serverId == null ? 'Sending...' : 'Posted'),
          style: TextStyle(
            color: item.error == null
                ? Theme.of(context).colorScheme.onSurfaceVariant
                : Theme.of(context).colorScheme.error,
          ),
        ),
      ],
    ),
  );

  Widget _discussionTile(
    ProductDiscussion item, {
    bool reply = false,
  }) => Padding(
    padding: EdgeInsets.only(left: reply ? 20 : 0, bottom: 8),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          item.authorType == 'seller' ? '${item.author} · Seller' : item.author,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 3),
        Text(item.body),
        TextButton(onPressed: () => _reply(item), child: const Text('Reply')),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final provider = productDiscussionsProvider((
      product: widget.productKey,
      page: _page,
    ));
    final discussions = ref.watch(provider);
    final loaded =
        discussions.value?.discussions ?? const <ProductDiscussion>[];
    final confirmedIds = {
      for (final root in loaded) root.id,
      for (final root in loaded)
        for (final reply in root.replies) reply.id,
    };

    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _composer,
            maxLength: 2000,
            maxLines: 3,
            onChanged: _onComposerChanged,
            decoration: const InputDecoration(
              labelText: 'Ask a question or add a comment',
            ),
          ),
          FilledButton(
            onPressed: _canPost ? () => _post(_composer.text) : null,
            child: const Text('Post comment'),
          ),
          for (final item in _pending.where(
            (item) =>
                item.rootId == null &&
                (item.serverId == null ||
                    !confirmedIds.contains(item.serverId)),
          ))
            _pendingTile(item),
          const SizedBox(height: 12),
          discussions.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (_, _) => TextButton(
              onPressed: () => ref.invalidate(provider),
              child: const Text('Retry discussions'),
            ),
            data: (page) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final root in page.discussions) ...[
                  _discussionTile(root),
                  for (final reply in root.replies)
                    _discussionTile(reply, reply: true),
                  for (final item in _pending.where(
                    (item) =>
                        item.rootId == root.id &&
                        (item.serverId == null ||
                            !confirmedIds.contains(item.serverId)),
                  ))
                    _pendingTile(item),
                  const Divider(),
                ],
                if (page.discussions.isEmpty && _pending.isEmpty)
                  const Text('No discussions yet.'),
                if (page.lastPage > 1)
                  Wrap(
                    spacing: 8,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      TextButton(
                        onPressed: page.page > 1
                            ? () => setState(() => _page--)
                            : null,
                        child: const Text('Previous'),
                      ),
                      Text('Page ${page.page} of ${page.lastPage}'),
                      TextButton(
                        onPressed: page.page < page.lastPage
                            ? () => setState(() => _page++)
                            : null,
                        child: const Text('Next'),
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

class _PendingDiscussion {
  _PendingDiscussion({
    required this.localId,
    required this.body,
    this.rootId,
    this.parentId,
  });

  final int localId;
  final String body;
  final String? rootId;
  final String? parentId;
  String? serverId;
  String? error;
}
