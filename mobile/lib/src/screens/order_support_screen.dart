import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_exception.dart';
import '../core/attachment_picker.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../providers/checkout_providers.dart';

class OrderSupportScreen extends ConsumerStatefulWidget {
  const OrderSupportScreen({
    super.key,
    required this.order,
    required this.sellerOrder,
    this.requestReturn = false,
  });

  final OrderDetail order;
  final SellerOrder sellerOrder;
  final bool requestReturn;

  @override
  ConsumerState<OrderSupportScreen> createState() => _OrderSupportScreenState();
}

class _OrderSupportScreenState extends ConsumerState<OrderSupportScreen> {
  final _form = GlobalKey<FormState>();
  final _details = TextEditingController();
  final _quantities = <String, int>{};
  late String _type;
  String _reason = 'wrong_item';
  String _remedy = 'replacement';
  Map<String, dynamic>? _case;
  PickedAttachment? _proof;
  bool _busy = false;
  bool _loading = false;
  bool _issueSent = false;
  String? _error;
  Timer? _refreshTimer;
  bool _refreshing = false;

  @override
  void initState() {
    super.initState();
    _type = widget.requestReturn ? 'return' : 'other';
    for (final line in widget.sellerOrder.lines) {
      _quantities[line.id] = line.quantity;
    }
    if (widget.sellerOrder.latestReturnId != null) {
      _load();
    }
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted &&
          !_busy &&
          !_refreshing &&
          WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed &&
          ModalRoute.of(context)?.isCurrent == true &&
          [
            'awaiting_seller',
            'appealed',
            'awaiting_receipt',
            'awaiting_fulfillment',
          ].contains(_case?['status'])) {
        _load(silent: true);
      }
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _details.dispose();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    _refreshing = true;
    setState(() {
      _loading = !silent;
      _error = null;
    });
    try {
      final value = await ref
          .read(apiClientProvider)
          .returnRequest(
            str(_case?['id'] ?? widget.sellerOrder.latestReturnId),
          );
      if (mounted) {
        setState(() => _case = value);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.friendly);
      }
    } finally {
      _refreshing = false;
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _submit() async {
    if (_busy) {
      return;
    }
    if (!(_form.currentState?.validate() ?? false)) {
      return;
    }
    if (_type == 'return' &&
        !_quantities.values.any((quantity) => quantity > 0)) {
      setState(() => _error = 'Choose at least one affected item.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = ref.read(apiClientProvider);
      if (_type == 'return') {
        final value = await api.openReturnRequest(
          widget.sellerOrder.id,
          reason: _reason,
          remedy: _remedy,
          details: _details.text.trim(),
          quantities: _quantities,
        );
        if (mounted) {
          setState(() => _case = value);
          _details.clear();
        }
      } else {
        await api.openOrderIssue(
          widget.sellerOrder.id,
          _type,
          _details.text.trim(),
        );
        if (mounted) {
          setState(() => _issueSent = true);
        }
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _act(String action) async {
    if (_busy) {
      return;
    }
    if (action == 'appeal' && _details.text.trim().length < 10) {
      setState(
        () => _error =
            'Explain why you need support review in at least 10 characters.',
      );
      return;
    }
    if (action == 'handoff' && _proof == null) {
      setState(() => _error = 'Attach proof that you handed the item back.');
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm this update?'),
        content: Text(
          action == 'confirm'
              ? 'Confirm only after you have received the refund or replacement.'
              : 'This update will be sent to the pharmacy.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep editing'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final value = await ref
          .read(apiClientProvider)
          .updateReturnRequest(
            str(_case!['id']),
            action,
            note: action == 'appeal' ? _details.text.trim() : null,
            proof: action == 'handoff' ? _proof : null,
          );
      if (mounted) {
        setState(() => _case = value);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.friendly);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Widget _caseView() {
    final value = _case!;
    final status = str(value['status']);
    final deadline = DateTime.tryParse(str(value['deadline']));
    final canAppeal =
        ['offered', 'rejected', 'awaiting_confirmation'].contains(status) ||
        (status == 'awaiting_seller' &&
            deadline != null &&
            deadline.isBefore(DateTime.now()));
    final title = switch (status) {
      'awaiting_seller' => 'Waiting for the pharmacy',
      'offered' => 'The pharmacy offered a solution',
      'rejected' => 'The pharmacy declined your request',
      'awaiting_return' => 'Return the affected item',
      'awaiting_receipt' => 'Waiting for the pharmacy to receive the item',
      'awaiting_fulfillment' => 'The pharmacy is arranging your solution',
      'awaiting_confirmation' => 'Confirm your refund or replacement',
      'appealed' => 'Support is reviewing your request',
      'completed' => 'Request completed',
      'closed' => 'Request closed',
      _ => statusLabel(status),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 12),
        Text(str(value['details'])),
        for (final item in listOf(value['items']))
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(str(item['product_name'])),
            subtitle: Text('Affected quantity: ${integer(item['quantity'])}'),
          ),
        if (mapOf(value['outcome'])['amount'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(
              'Refund recorded: ${money(str(mapOf(value['outcome'])['amount']))}',
            ),
          ),
        if (str(mapOf(value['outcome'])['shipment_reference']).isNotEmpty)
          Text(
            'Replacement reference: ${str(mapOf(value['outcome'])['shipment_reference'])}',
          ),
        if (str(value['response_note']).isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(
            'Pharmacy response',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          Text(str(value['response_note'])),
        ],
        if (str(value['return_instructions']).isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(str(value['return_instructions'])),
        ],
        if (value['deadline'] != null) ...[
          const SizedBox(height: 12),
          Text('Respond by ${dateTime(str(value['deadline']))}'),
        ],
        if (boolean(value['can_respond'])) ...[
          if (canAppeal) ...[
            const SizedBox(height: 16),
            TextField(
              controller: _details,
              maxLength: 2000,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Reason for support review',
                helperText: 'Required only when asking support to review.',
              ),
            ),
            if (status == 'offered')
              FilledButton(
                onPressed: _busy ? null : () => _act('accept'),
                child: const Text('Accept solution'),
              ),
            OutlinedButton(
              onPressed: _busy ? null : () => _act('appeal'),
              child: const Text('Ask support to review'),
            ),
          ],
          if (status == 'awaiting_return') ...[
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: _busy
                  ? null
                  : () async {
                      final proof = await ref
                          .read(attachmentPickerProvider)
                          .pickDocument();
                      if (mounted && proof != null) {
                        setState(() => _proof = proof);
                      }
                    },
              child: Text(_proof?.name ?? 'Attach return handoff proof'),
            ),
            FilledButton(
              onPressed: _busy ? null : () => _act('handoff'),
              child: const Text('I have returned the item'),
            ),
          ],
          if (status == 'awaiting_confirmation')
            FilledButton(
              onPressed: _busy ? null : () => _act('confirm'),
              child: const Text('I received the refund or replacement'),
            ),
        ],
        const SizedBox(height: 20),
        Text('Updates', style: Theme.of(context).textTheme.titleMedium),
        for (final event in listOf(value['events']))
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(
              str(event['note']).isEmpty
                  ? _eventLabel(str(event['type']))
                  : str(event['note']),
            ),
            subtitle: Text(
              [
                str(event['actor']),
                dateTime(str(event['created_at'])),
              ].join(' · '),
            ),
          ),
      ],
    );
  }

  String _eventLabel(String type) => switch (type) {
    'submitted' => 'Request submitted',
    'admin_opened' => 'Opened by support',
    'offer' => 'Solution offered',
    'reject' => 'Request declined',
    'accept' => 'Offer accepted',
    'appeal' => 'Sent to support for review',
    'handoff' => 'Item handed back',
    'receive' => 'Returned item received',
    'refund' => 'Refund recorded',
    'ship' => 'Replacement sent',
    'decide' => 'Support decision recorded',
    'takeover' => 'Support took over the request',
    'reopen' => 'Request reopened',
    'confirm' => 'Refund or replacement confirmed',
    'verify' => 'Completion verified',
    'close' => 'Request closed',
    'evidence_added' => 'Supporting document added',
    'reminder' => 'Response reminder sent',
    'restock' => 'Returned items inspected',
    'replacement_stock_reduced' => 'Replacement reserved',
    'replacement_stock_restored' => 'Replacement reservation released',
    _ => 'Request updated',
  };

  @override
  Widget build(BuildContext context) {
    final order = widget.sellerOrder;
    final windowEnd = DateTime.tryParse(order.returnWindowEndsAt ?? '');
    final canReturn =
        order.status == 'delivered' &&
        (windowEnd == null || !windowEnd.isBefore(DateTime.now()));
    final hasOpenIssue = _issueSent || order.latestIssueStatus == 'open';
    return Scaffold(
      appBar: AppBar(title: const Text('Order help')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  order.store?.name ?? 'Pharmacy',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text('Order ${widget.order.shortNumber}'),
                const SizedBox(height: 20),
                if (_case != null)
                  _caseView()
                else if (widget.sellerOrder.latestReturnId != null)
                  OutlinedButton(
                    onPressed: _load,
                    child: const Text('Retry loading request'),
                  )
                else if (hasOpenIssue) ...[
                  const Text('Your request is waiting for review.'),
                  const SizedBox(height: 12),
                  Text(
                    _issueSent
                        ? _details.text.trim()
                        : (order.latestIssueDetails ??
                              'The pharmacy will review your request.'),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Back to order'),
                  ),
                ] else
                  Form(
                    key: _form,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String>(
                          initialValue: _type,
                          isExpanded: true,
                          decoration: const InputDecoration(
                            labelText: 'What do you need?',
                          ),
                          items: [
                            const DropdownMenuItem(
                              value: 'other',
                              child: Text('Other order help'),
                            ),
                            const DropdownMenuItem(
                              value: 'delivery',
                              child: Text('Delivery problem'),
                            ),
                            if (['pending', 'confirmed'].contains(order.status))
                              const DropdownMenuItem(
                                value: 'cancellation',
                                child: Text('Request cancellation'),
                              ),
                            if (canReturn || _type == 'return')
                              const DropdownMenuItem(
                                value: 'return',
                                child: Text('Return or replacement'),
                              ),
                          ],
                          onChanged: _busy
                              ? null
                              : (value) => setState(() => _type = value!),
                        ),
                        if (_type == 'return') ...[
                          const SizedBox(height: 16),
                          Text(
                            'Report affected items within ${order.returnWindowHours} hours of delivery. The pharmacy reviews every request.',
                          ),
                          if (!canReturn)
                            const Text(
                              'The return request window has ended. Choose other order help to contact the pharmacy.',
                            ),
                          const SizedBox(height: 16),
                          DropdownButtonFormField<String>(
                            initialValue: _reason,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              labelText: 'Problem',
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 'wrong_item',
                                child: Text('Wrong item'),
                              ),
                              DropdownMenuItem(
                                value: 'missing_item',
                                child: Text('Missing item'),
                              ),
                              DropdownMenuItem(
                                value: 'damaged',
                                child: Text('Damaged item'),
                              ),
                              DropdownMenuItem(
                                value: 'defective',
                                child: Text('Defective item'),
                              ),
                              DropdownMenuItem(
                                value: 'misdescribed',
                                child: Text(
                                  'Item differs from its description',
                                ),
                              ),
                            ],
                            onChanged: _busy
                                ? null
                                : (value) => setState(() => _reason = value!),
                          ),
                          const SizedBox(height: 16),
                          DropdownButtonFormField<String>(
                            initialValue: _remedy,
                            decoration: const InputDecoration(
                              labelText: 'Preferred solution',
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 'replacement',
                                child: Text('Replacement'),
                              ),
                              DropdownMenuItem(
                                value: 'refund',
                                child: Text('Refund'),
                              ),
                            ],
                            onChanged: _busy
                                ? null
                                : (value) => setState(() => _remedy = value!),
                          ),
                          for (final line in order.lines)
                            Padding(
                              padding: const EdgeInsets.only(top: 16),
                              child: DropdownButtonFormField<int>(
                                initialValue: line.quantity,
                                isExpanded: true,
                                decoration: InputDecoration(
                                  labelText:
                                      'Affected quantity · ${line.productName}',
                                ),
                                items: [
                                  for (
                                    var quantity = 0;
                                    quantity <= line.quantity;
                                    quantity++
                                  )
                                    DropdownMenuItem(
                                      value: quantity,
                                      child: Text(
                                        '$quantity of ${line.quantity}',
                                      ),
                                    ),
                                ],
                                onChanged: _busy
                                    ? null
                                    : (value) => _quantities[line.id] = value!,
                              ),
                            ),
                        ],
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _details,
                          maxLength: 2000,
                          minLines: 3,
                          maxLines: 5,
                          decoration: const InputDecoration(
                            labelText: 'Tell us what happened',
                          ),
                          validator: (value) => (value ?? '').trim().length < 10
                              ? 'Please describe the problem in at least 10 characters.'
                              : null,
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _busy || (_type == 'return' && !canReturn)
                              ? null
                              : _submit,
                          child: Text(_busy ? 'Sending…' : 'Send request'),
                        ),
                      ],
                    ),
                  ),
                if (_busy)
                  const Padding(
                    padding: EdgeInsets.only(top: 16),
                    child: LinearProgressIndicator(),
                  ),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: Semantics(
                      liveRegion: true,
                      child: Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }
}
