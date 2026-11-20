import 'dart:io';

import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';

import 'attachment_picker.dart';

/// What could be read off a senior citizen / PWD ID photo. Either part may be missing; the
/// customer always checks and edits them, since a read is typing help, never a verification.
/// [type] is 'senior' or 'pwd' when the card says which it is.
typedef DiscountIdFields = ({String? type, String? number, String? name});

/// Reads the printed text of an ID photo on the phone; nothing is sent anywhere.
abstract class DiscountIdReader {
  Future<String> readText(PickedAttachment photo);
}

class MlKitDiscountIdReader implements DiscountIdReader {
  const MlKitDiscountIdReader();

  @override
  Future<String> readText(PickedAttachment photo) async {
    // ML Kit reads files, not encoded bytes; the copy is removed as soon as it is read.
    final file = File(
      '${Directory.systemTemp.path}/discount-id-${DateTime.now().microsecondsSinceEpoch}.${photo.extension}',
    );
    final recognizer = TextRecognizer();
    try {
      await file.writeAsBytes(photo.bytes, flush: true);

      return (await recognizer.processImage(InputImage.fromFilePath(file.path)))
          .text;
    } finally {
      await recognizer.close();
      if (await file.exists()) {
        await file.delete();
      }
    }
  }
}

final _numberLabel = RegExp(
  r'\b(?:(?:OSCA|PWD|SC|SCID|CONTROL|ID|CARD)\s*(?:ID\s*)?(?:NO|NUMBER|#)\.?)',
  caseSensitive: false,
);
final _nameLabel = RegExp(
  r'^\s*(?:FULL\s*)?NAME\b\.?\s*:?',
  caseSensitive: false,
);
final _unifiedPwdNumber = RegExp(r'\b\d{2}-\d{4}-\d{3}-\d{7}\b');
final _numberToken = RegExp(
  r'[A-Z0-9][A-Z0-9-]{2,}[A-Z0-9]',
  caseSensitive: false,
);
final _nameShape = RegExp(r"^[A-Za-zÑñ][A-Za-zÑñ.,'\- ]+$");
const _notNames = [
  'REPUBLIC',
  'PHILIPPINES',
  'PROVINCE',
  'CITY',
  'MUNICIPALITY',
  'OFFICE',
  'SENIOR',
  'CITIZEN',
  'DISABILITY',
  'PERSON',
  'SIGNATURE',
  'ADDRESS',
  'VALID',
  'DATE',
  'BIRTH',
  'AFFAIRS',
  'MAYOR',
  'BARANGAY',
  'TYPE',
];

/// Picks the ID number and the holder's name out of OCR text from a Philippine senior
/// citizen (OSCA) or PWD card. Cards differ by LGU, so this only follows the labels most of
/// them print ("ID No.", "Name") and the Unified PWD ID number shape.
DiscountIdFields parseDiscountId(String text) {
  final lines = text
      .split('\n')
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .toList();

  return (type: _type(text), number: _number(lines, text), name: _name(lines));
}

/// PWD wording or number shape wins: a PWD card may also mention its holder's senior status.
String? _type(String text) {
  if (_unifiedPwdNumber.hasMatch(text) ||
      RegExp(
        r'\b(?:PWD|DISABILITY|DISABLED)\b',
        caseSensitive: false,
      ).hasMatch(text)) {
    return 'pwd';
  }
  if (RegExp(r'\b(?:SENIOR|OSCA)\b', caseSensitive: false).hasMatch(text)) {
    return 'senior';
  }

  return null;
}

String? _number(List<String> lines, String text) {
  final unified = _unifiedPwdNumber.firstMatch(text);
  if (unified != null) {
    return unified.group(0);
  }
  for (var i = 0; i < lines.length; i++) {
    final label = _numberLabel.firstMatch(lines[i]);
    if (label == null) {
      continue;
    }
    final found =
        _numberIn(lines[i].substring(label.end)) ??
        (i + 1 < lines.length ? _numberIn(lines[i + 1]) : null);
    if (found != null) {
      return found;
    }
  }

  return null;
}

/// The first run of letters, digits and dashes with at least three digits in it.
String? _numberIn(String value) {
  for (final match in _numberToken.allMatches(value)) {
    final token = match.group(0)!;
    if (RegExp(r'\d').allMatches(token).length >= 3) {
      return token.toUpperCase();
    }
  }

  return null;
}

String? _name(List<String> lines) {
  for (var i = 0; i < lines.length; i++) {
    final label = _nameLabel.firstMatch(lines[i]);
    if (label == null) {
      continue;
    }
    // "Name: JUAN DELA CRUZ", the name on the next line, or printed above a "Name" caption.
    final candidates = [
      lines[i].substring(label.end),
      if (i + 1 < lines.length) lines[i + 1],
      if (i > 0) lines[i - 1],
    ];
    for (final candidate in candidates) {
      final name = candidate.trim().replaceAll(RegExp(r'\s+'), ' ');
      if (_looksLikeName(name)) {
        return name;
      }
    }
  }

  return null;
}

bool _looksLikeName(String value) {
  if (!_nameShape.hasMatch(value) || value.split(' ').length < 2) {
    return false;
  }
  final upper = value.toUpperCase();

  return !_notNames.any(upper.contains);
}
