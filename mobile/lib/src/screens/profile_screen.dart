import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:phosphor_icons/phosphor_icons.dart';

import '../core/api_exception.dart';
import '../core/formatters.dart';
import '../models/models.dart';
import '../providers/auth_providers.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/grouped_list_card.dart';
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
  final _nameFocus = FocusNode();
  final _phoneFocus = FocusNode();
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
  void initState() {
    super.initState();
    _nameFocus.addListener(_onFocusChange);
    _phoneFocus.addListener(_onFocusChange);
  }

  void _onFocusChange() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  void dispose() {
    _nameFocus
      ..removeListener(_onFocusChange)
      ..dispose();
    _phoneFocus
      ..removeListener(_onFocusChange)
      ..dispose();
    _name.dispose();
    _phone.dispose();
    _birthDateText.dispose();
    super.dispose();
  }

  Future<void> _pickBirthDate() async {
    FocusScope.of(context).unfocus();
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
      builder: (dialogContext) {
        final scheme = Theme.of(dialogContext).colorScheme;
        return AlertDialog(
          title: const Text('Delete your account?'),
          content: const Text(
            'Your profile, saved addresses, saved discount IDs and cart are deleted, and you are signed out.\n\n'
            'Past orders, prescriptions and receipts are kept as the pharmacy\'s records, and your reviews stay under "Deleted customer".\n\n'
            'This cannot be undone.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Keep account'),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size(0, 44),
                backgroundColor: scheme.error,
                foregroundColor: scheme.onError,
              ),
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Delete account'),
            ),
          ],
        );
      },
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

  InputDecoration _inlineDecoration(
    BuildContext context, {
    required String label,
    required bool focused,
    String? hint,
  }) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return InputDecoration(
      labelText: label,
      hintText: hint,
      filled: false,
      isDense: true,
      floatingLabelBehavior: FloatingLabelBehavior.always,
      contentPadding: const EdgeInsets.only(top: 4, bottom: 2),
      border: InputBorder.none,
      enabledBorder: InputBorder.none,
      focusedBorder: InputBorder.none,
      errorBorder: InputBorder.none,
      focusedErrorBorder: InputBorder.none,
      labelStyle: theme.textTheme.labelSmall?.copyWith(
        fontSize: 12,
        fontWeight: FontWeight.w600,
        color: scheme.onSurfaceVariant,
        letterSpacing: 0.1,
      ),
      floatingLabelStyle: theme.textTheme.labelSmall?.copyWith(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        color: focused ? scheme.primary : scheme.onSurfaceVariant,
        letterSpacing: 0.1,
      ),
      hintStyle: theme.textTheme.bodyLarge?.copyWith(
        fontSize: 15.5,
        fontWeight: FontWeight.w500,
        color: scheme.onSurfaceVariant.withValues(alpha: 0.55),
      ),
      errorStyle: theme.textTheme.bodySmall?.copyWith(
        fontSize: 12,
        fontWeight: FontWeight.w500,
        color: scheme.error,
        height: 1.3,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final fieldStyle = theme.textTheme.bodyLarge?.copyWith(
      fontSize: 15.5,
      fontWeight: FontWeight.w600,
      color: scheme.onSurface,
      height: 1.3,
    );

    return Form(
      key: _formKey,
      autovalidateMode: _submitted
          ? AutovalidateMode.always
          : AutovalidateMode.disabled,
      child: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _IdentityHero(
                  initialsText: initials(
                    _name.text.trim().isEmpty
                        ? widget.account.name
                        : _name.text,
                  ),
                  email: widget.account.email,
                  verified: widget.account.emailVerified,
                ),
                const SizedBox(height: 20),
                GroupedCard(
                  dividerIndent: 60,
                  children: [
                    _InlineFieldRow(
                      icon: PhosphorIconsRegular.user,
                      focused: _nameFocus.hasFocus,
                      onTap: _nameFocus.requestFocus,
                      child: TextFormField(
                        controller: _name,
                        focusNode: _nameFocus,
                        style: fieldStyle,
                        textCapitalization: TextCapitalization.words,
                        textInputAction: TextInputAction.next,
                        autofillHints: const [AutofillHints.name],
                        onChanged: (_) => setState(() {}),
                        decoration: _inlineDecoration(
                          context,
                          label: 'Full name',
                          focused: _nameFocus.hasFocus,
                        ),
                        validator: (value) => (value ?? '').trim().isEmpty
                            ? 'Enter your full name.'
                            : null,
                      ),
                    ),
                    _InlineFieldRow(
                      icon: PhosphorIconsRegular.cake,
                      focused: false,
                      onTap: _pickBirthDate,
                      trailing: Icon(
                        PhosphorIconsRegular.caretRight,
                        size: 16,
                        color: scheme.onSurfaceVariant,
                      ),
                      child: TextFormField(
                        controller: _birthDateText,
                        readOnly: true,
                        onTap: _pickBirthDate,
                        style: fieldStyle,
                        decoration: _inlineDecoration(
                          context,
                          label: 'Birth date',
                          focused: false,
                          hint: 'Select date',
                        ),
                        validator: (_) => _birthDate == null
                            ? 'Choose your birth date.'
                            : null,
                      ),
                    ),
                    _InlineFieldRow(
                      icon: PhosphorIconsRegular.deviceMobile,
                      focused: _phoneFocus.hasFocus,
                      onTap: _phoneFocus.requestFocus,
                      child: TextFormField(
                        controller: _phone,
                        focusNode: _phoneFocus,
                        style: fieldStyle,
                        keyboardType: TextInputType.phone,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.telephoneNumber],
                        decoration: _inlineDecoration(
                          context,
                          label: 'Mobile number',
                          focused: _phoneFocus.hasFocus,
                          hint: '09xxxxxxxxx',
                        ),
                        validator: (value) =>
                            RegExp(
                              r'^(?:\+63|0)9\d{9}$',
                            ).hasMatch((value ?? '').trim())
                            ? null
                            : 'Use a valid PH mobile number.',
                      ),
                    ),
                    _SexRow(
                      value: _sex,
                      onChanged: (value) => setState(() => _sex = value),
                    ),
                  ],
                ),
                if (!widget.completing) ...[
                  const SizedBox(height: 20),
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      backgroundColor: scheme.surfaceContainerLowest,
                      foregroundColor: scheme.error,
                      side: BorderSide(color: scheme.outlineVariant),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    onPressed: _deleting ? null : _deleteAccount,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          PhosphorIconsRegular.trash,
                          size: 18,
                          color: scheme.error,
                        ),
                        const SizedBox(width: 8),
                        Text(_deleting ? 'Deleting…' : 'Delete account'),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          DecoratedBox(
            decoration: BoxDecoration(
              color: scheme.surfaceContainerLowest,
              border: Border(top: BorderSide(color: scheme.outlineVariant)),
            ),
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton(
                      onPressed: _saving ? null : _save,
                      child: _saving
                          ? SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.2,
                                color: scheme.onPrimary,
                              ),
                            )
                          : const Text('Save'),
                    ),
                    if (widget.completing) ...[
                      const SizedBox(height: 6),
                      TextButton(
                        onPressed: () => Navigator.of(context).pop(),
                        child: const Text('Not now'),
                      ),
                    ],
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

class _IdentityHero extends StatelessWidget {
  const _IdentityHero({
    required this.initialsText,
    required this.email,
    required this.verified,
  });

  final String initialsText;
  final String email;
  final bool verified;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final brand = AppBrandColors.of(context);
    final dark = theme.brightness == Brightness.dark;

    return Column(
      children: [
        Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: 82,
              height: 82,
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: scheme.surfaceContainerLowest,
                border: Border.all(color: scheme.outlineVariant),
                boxShadow: dark
                    ? null
                    : const [
                        BoxShadow(
                          color: Color(0x120F1D40),
                          blurRadius: 10,
                          offset: Offset(0, 3),
                        ),
                      ],
              ),
              child: CircleAvatar(
                backgroundColor: scheme.primary,
                foregroundColor: scheme.onPrimary,
                child: Text(
                  initialsText,
                  style: const TextStyle(
                    fontFamily: appFontFamily,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                  ),
                ),
              ),
            ),
            if (verified)
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: brand.careTeal,
                    shape: BoxShape.circle,
                    border: Border.all(color: scheme.surface, width: 2.5),
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    PhosphorIconsRegular.check,
                    size: 12,
                    color: dark ? const Color(0xFF0B1020) : Colors.white,
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLowest,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: scheme.outlineVariant),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                PhosphorIconsRegular.envelopeSimple,
                size: 14,
                color: scheme.onSurfaceVariant,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  email,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: scheme.onSurface,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _InlineFieldRow extends StatelessWidget {
  const _InlineFieldRow({
    required this.icon,
    required this.focused,
    required this.child,
    this.onTap,
    this.trailing,
  });

  final IconData icon;
  final bool focused;
  final Widget child;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOut,
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: focused
                    ? scheme.primaryContainer
                    : scheme.surfaceContainer,
                borderRadius: BorderRadius.circular(10),
              ),
              alignment: Alignment.center,
              child: Icon(
                icon,
                size: 18,
                color: focused ? scheme.primary : scheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(child: child),
            if (trailing != null) ...[const SizedBox(width: 8), trailing!],
          ],
        ),
      ),
    );
  }
}

class _SexRow extends StatelessWidget {
  const _SexRow({required this.value, required this.onChanged});

  final String? value;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dark = theme.brightness == Brightness.dark;

    const options = [
      (value: 'female', label: 'Female', flex: 2),
      (value: 'male', label: 'Male', flex: 2),
      (value: null, label: 'Prefer not to say', flex: 3),
    ];

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: scheme.surfaceContainer,
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Icon(
                  PhosphorIconsRegular.genderIntersex,
                  size: 18,
                  color: scheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(width: 12),
              Text(
                'Sex',
                style: theme.textTheme.labelSmall?.copyWith(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: scheme.onSurfaceVariant,
                  letterSpacing: 0.1,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(
              color: scheme.surfaceContainer,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              children: [
                for (final option in options)
                  Expanded(
                    flex: option.flex,
                    child: Semantics(
                      button: true,
                      selected: value == option.value,
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () => onChanged(option.value),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 180),
                          curve: Curves.easeOut,
                          height: 36,
                          alignment: Alignment.center,
                          padding: const EdgeInsets.symmetric(horizontal: 6),
                          decoration: BoxDecoration(
                            color: value == option.value
                                ? scheme.surfaceContainerLowest
                                : Colors.transparent,
                            borderRadius: BorderRadius.circular(8),
                            boxShadow: value == option.value && !dark
                                ? const [
                                    BoxShadow(
                                      color: Color(0x140F1D40),
                                      blurRadius: 3,
                                      offset: Offset(0, 1),
                                    ),
                                  ]
                                : null,
                          ),
                          child: Text(
                            option.label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.labelMedium?.copyWith(
                              fontSize: 12.5,
                              fontWeight: value == option.value
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                              color: value == option.value
                                  ? scheme.onSurface
                                  : scheme.onSurfaceVariant,
                            ),
                          ),
                        ),
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
