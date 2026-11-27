import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/models.dart';

/// Optional backing file for device persistence; null in widget tests so
/// FakeAsync never waits on disk I/O.
final themeModeFileProvider = Provider<File?>((ref) => null);

/// Initial snapshot loaded before runApp when a backing file exists.
final initialThemeModeProvider = Provider<ThemeMode>((ref) => ThemeMode.system);

/// Parses a persisted theme mode name safely, falling back to [ThemeMode.system].
ThemeMode parseThemeMode(Object? raw) => switch (raw) {
  'light' => ThemeMode.light,
  'dark' => ThemeMode.dark,
  _ => ThemeMode.system,
};

/// Human-readable label for Settings summaries and accessibility.
String themeModeLabel(ThemeMode mode) => switch (mode) {
  ThemeMode.system => 'System',
  ThemeMode.light => 'Light',
  ThemeMode.dark => 'Dark',
};

/// Loads the persisted [ThemeMode] from [file], returning [ThemeMode.system]
/// if the file does not exist or cannot be parsed.
Future<ThemeMode> loadThemeMode(File file) async {
  try {
    if (!await file.exists()) {
      return ThemeMode.system;
    }
    final decoded = mapOf(jsonDecode(await file.readAsString()));
    return parseThemeMode(decoded['theme_mode']);
  } catch (_) {
    return ThemeMode.system;
  }
}

class ThemeModeNotifier extends Notifier<ThemeMode> {
  @override
  ThemeMode build() => ref.watch(initialThemeModeProvider);

  void setMode(ThemeMode mode) {
    if (state == mode) {
      return;
    }
    state = mode;
    _persist(mode);
  }

  void _persist(ThemeMode mode) {
    final file = ref.read(themeModeFileProvider);
    if (file == null) {
      return;
    }
    final payload = jsonEncode({'theme_mode': mode.name});
    unawaited(file.writeAsString(payload, flush: true).catchError((_) => file));
  }
}

final themeModeProvider = NotifierProvider<ThemeModeNotifier, ThemeMode>(
  ThemeModeNotifier.new,
);
