import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../core/api_exception.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../widgets/common.dart';
import '../widgets/status_notice.dart';
import 'account_screen.dart';

/// Full name, birth date, mobile number and sex. With [completing] it is the
/// once-per-session "Complete your profile" prompt, which can be put off.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key, this.completing = false});

  final bool completing;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        title: Text(completing ? 'Complete your profile' : 'Edit profile'),
      ),
      body: AsyncView(
        value: ref.watch(meProvider),
        onRetry: () => ref.invalidate(meProvider),
        builder: (account) =>
            ProfileForm(account: account, completing: completing),
      ),
    );
  }
}

class ProfileForm extends ConsumerStatefulWidget {
  const ProfileForm({
    super.key,
    required this.account,
    this.completing = false,
  });

  final Account account;
  final bool completing;

  @override
  ConsumerState<ProfileForm> createState() => _ProfileFormState();
}

class _ProfileFormState extends ConsumerState<ProfileForm> {
  final _formKey = GlobalKey<FormState>();
  late final _name = TextEditingController(text: widget.account.name);
  late final _phone = TextEditingController(text: widget.account.phone);
  late final _birthDateText = TextEditingController(
    text: _formatDate(widget.account.birthDate),
  );
  late DateTime? _birthDate = widget.account.birthDate;
  late String? _sex = widget.account.sex;
  bool _saving = false;
  bool _deleting = false;
  bool _submitted = false;

  static String _formatDate(DateTime? date) =>
      date == null ? '' : DateFormat('MMMM d, yyyy').format(date);

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _birthDateText.dispose();
    super.dispose();
  }

  Future<void> _pickBirthDate() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthDate ?? DateTime(today.year - 30),
      firstDate: DateTime(1900),
      lastDate: today.subtract(const Duration(days: 1)),
      initialEntryMode: DatePickerEntryMode.calendarOnly,
      helpText: 'Birth date',
    );
    if (picked != null) {
      setState(() {
        _birthDate = picked;
        _birthDateText.text = _formatDate(picked);
      });
    }
  }

  Future<void> _save() async {
    setState(() => _submitted = true);
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    setState(() => _saving = true);
    final feedback = AppNotice.of(context);
    final navigator = Navigator.of(context);
    try {
      await ref
          .read(apiClientProvider)
          .updateProfile(
            name: _name.text.trim(),
            birthDate: _birthDate!,
            phone: _phone.text.trim(),
            sex: _sex,
          );
      ref.invalidate(meProvider);
      feedback.show('Profile saved.', kind: NoticeKind.success);
      navigator.pop();
    } on ApiException catch (error) {
      feedback.show(
        error.friendly,
        onAction: error.isRetryable
            ? () {
                if (mounted) return _save();
              }
            : null,
      );
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _deleteAccount() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete your account?'),
        content: const Text(
          'Your profile, saved addresses, saved discount IDs and cart are deleted, and you are signed out.\n\n'
          'Past orders, prescriptions and receipts are kept as the pharmacy\'s records, and your reviews stay under "Deleted customer".\n\n'
          'This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep account'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
              foregroundColor: Theme.of(context).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete account'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }

    final feedback = AppNotice.of(context);
    setState(() => _deleting = true);
    try {
      await ref.read(apiClientProvider).deleteAccount();
    } on ApiException catch (error) {
      feedback.show(error.friendly);
      if (mounted) {
        setState(() => _deleting = false);
      }
      return;
    }
    if (!mounted) {
      return;
    }
    final navigator = Navigator.of(context);
    await signOutOfApp(ClerkAuth.of(context), ref);
    navigator.popUntil((route) => route.isFirst);
    feedback.show('Your account was deleted.', kind: NoticeKind.success);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Form(
      key: _formKey,
      autovalidateMode: _submitted
          ? AutovalidateMode.always
          : AutovalidateMode.disabled,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          if (widget.completing) ...[
            Text(
              'Your pharmacist uses these to match prescriptions to you and to check senior citizen and PWD discounts.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 16),
          ],
          TextFormField(
            controller: _name,
            textCapitalization: TextCapitalization.words,
            autofillHints: const [AutofillHints.name],
            decoration: const InputDecoration(
              labelText: 'Full name',
              helperText: 'As it appears on your ID',
            ),
            validator: (value) =>
                (value ?? '').trim().isEmpty ? 'Enter your full name.' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _birthDateText,
            readOnly: true,
            onTap: _pickBirthDate,
            decoration: const InputDecoration(
              labelText: 'Birth date',
              helperText: 'For senior citizen discounts and age limits on some medicines',
              helperMaxLines: 2,
              suffixIcon: Icon(Icons.calendar_today_outlined),
            ),
            validator: (_) =>
                _birthDate == null ? 'Choose your birth date.' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            autofillHints: const [AutofillHints.telephoneNumber],
            decoration: const InputDecoration(
              labelText: 'Mobile number',
              hintText: '09xxxxxxxxx',
            ),
            validator: (value) =>
                RegExp(r'^(?:\+63|0)9\d{9}$').hasMatch((value ?? '').trim())
                ? null
                : 'Use a valid PH mobile number.',
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String?>(
            initialValue: _sex,
            decoration: const InputDecoration(labelText: 'Sex (optional)'),
            items: const [
              DropdownMenuItem(value: 'female', child: Text('Female')),
              DropdownMenuItem(value: 'male', child: Text('Male')),
              DropdownMenuItem(child: Text('Prefer not to say')),
            ],
            onChanged: (value) => setState(() => _sex = value),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Saving…' : 'Save'),
          ),
          if (widget.completing)
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Not now'),
            )
          else ...[
            const SizedBox(height: 36),
            const Divider(),
            const SizedBox(height: 12),
            Text(
              'Delete account',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Removes your profile and saved details from Everyday Pharmacy.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 10),
            OutlinedButton(
              style: OutlinedButton.styleFrom(
                foregroundColor: scheme.error,
                side: BorderSide(color: scheme.error),
              ),
              onPressed: _deleting ? null : _deleteAccount,
              child: Text(_deleting ? 'Deleting…' : 'Delete account'),
            ),
          ],
        ],
      ),
    );
  }
}
