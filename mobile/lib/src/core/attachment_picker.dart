import 'dart:io';
import 'dart:ui' as ui;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_document_scanner/google_mlkit_document_scanner.dart';
import 'package:image_picker/image_picker.dart';

/// Upload limit for picked files (prescriptions): 5 MB, under the server's 8 MB.
const maxChatAttachmentBytes = 5 * 1024 * 1024;

/// A file chosen on the phone, ready to be sent as multipart form data.
class PickedAttachment {
  const PickedAttachment({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;

  String get extension {
    final dot = name.lastIndexOf('.');

    return dot == -1 ? '' : name.substring(dot + 1).toLowerCase();
  }

  String get mimeType => switch (extension) {
    'jpg' || 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
    _ => 'application/octet-stream',
  };

  bool get isImage => mimeType.startsWith('image/');
}

class AttachmentTooLargeException implements Exception {
  const AttachmentTooLargeException();
}

abstract class AttachmentPicker {
  Future<PickedAttachment?> pickImage();

  /// A new photo from the phone's camera app, or null when the customer backs out.
  Future<PickedAttachment?> takePhoto();

  /// A card scanned with Google's document scanner, which finds the card's edges and
  /// crops it straight; falls back to [takePhoto] when the scanner is not available.
  Future<PickedAttachment?> scanCard();

  /// The front and back of an ID as one picture, since an order keeps a single ID photo.
  Future<PickedAttachment> joinCardSides(
    PickedAttachment front,
    PickedAttachment back,
  );

  Future<PickedAttachment?> pickDocument();

  /// Every page the customer picked in one go, for a multi-page prescription.
  Future<List<PickedAttachment>> pickImages();

  Future<List<PickedAttachment>> pickDocuments();
}

class FilePickerAttachmentPicker implements AttachmentPicker {
  const FilePickerAttachmentPicker();

  @override
  Future<PickedAttachment?> pickImage() =>
      _pick(type: FileType.image, dialogTitle: 'Choose a photo');

  @override
  Future<PickedAttachment?> takePhoto() async {
    // Camera shots run 4-10 MB; 2000px at 85% stays readable and under the limit.
    final shot = await ImagePicker().pickImage(
      source: ImageSource.camera,
      maxWidth: 2000,
      maxHeight: 2000,
      imageQuality: 85,
    );
    if (shot == null) {
      return null;
    }

    final bytes = await shot.readAsBytes();
    if (bytes.length > maxChatAttachmentBytes) {
      throw const AttachmentTooLargeException();
    }

    return PickedAttachment(name: shot.name, bytes: bytes);
  }

  @override
  Future<PickedAttachment?> scanCard() async {
    // Base mode: crop and rotate only, no filters that could grey out the photo.
    final scanner = DocumentScanner(
      options: DocumentScannerOptions(mode: ScannerMode.base),
    );
    final List<String> pages;
    try {
      pages = (await scanner.scanDocument()).images ?? const [];
    } on PlatformException catch (error) {
      if (error.message == 'Operation cancelled') {
        return null;
      }

      // No Google Play services, or the scanner module could not download.
      return takePhoto();
    } finally {
      await scanner.close();
    }
    if (pages.isEmpty) {
      return null;
    }

    final file = File(pages.first);
    final bytes = await file.readAsBytes();
    if (bytes.length > maxChatAttachmentBytes) {
      throw const AttachmentTooLargeException();
    }

    return PickedAttachment(name: file.uri.pathSegments.last, bytes: bytes);
  }

  @override
  Future<PickedAttachment> joinCardSides(
    PickedAttachment front,
    PickedAttachment back,
  ) => stackCardSides(front, back);

  @override
  Future<PickedAttachment?> pickDocument() => _pick(
    type: FileType.custom,
    allowedExtensions: const ['pdf'],
    dialogTitle: 'Choose a document',
  );

  @override
  Future<List<PickedAttachment>> pickImages() => _pickMany(
    type: FileType.image,
    dialogTitle: 'Choose prescription photos',
  );

  @override
  Future<List<PickedAttachment>> pickDocuments() => _pickMany(
    type: FileType.custom,
    allowedExtensions: const ['pdf'],
    dialogTitle: 'Choose prescription documents',
  );

  Future<PickedAttachment?> _pick({
    required FileType type,
    required String dialogTitle,
    List<String>? allowedExtensions,
  }) async {
    final file = await FilePicker.pickFile(
      type: type,
      allowedExtensions: allowedExtensions,
      dialogTitle: dialogTitle,
    );

    return file == null ? null : _read(file);
  }

  Future<List<PickedAttachment>> _pickMany({
    required FileType type,
    required String dialogTitle,
    List<String>? allowedExtensions,
  }) async {
    final files = await FilePicker.pickFiles(
      type: type,
      allowedExtensions: allowedExtensions,
      dialogTitle: dialogTitle,
    );

    return [for (final file in files) await _read(file)];
  }

  Future<PickedAttachment> _read(PlatformFile file) async {
    // Check the reported size before reading, so a huge file cannot exhaust
    // memory just to be rejected by the server a moment later.
    final length = file.lengthSync() ?? await file.length();

    if (length != null && length > maxChatAttachmentBytes) {
      throw const AttachmentTooLargeException();
    }

    final bytes = await file.readAsBytes();

    if (bytes.length > maxChatAttachmentBytes) {
      throw const AttachmentTooLargeException();
    }

    return PickedAttachment(name: file.name, bytes: bytes);
  }
}

/// Stacks the front above the back on white, as a PNG under the upload limit.
Future<PickedAttachment> stackCardSides(
  PickedAttachment front,
  PickedAttachment back,
) async {
  // Narrower until the PNG fits; 900px still shows an ID's small print.
  for (final width in const [1400, 1100, 900]) {
    final bytes = await _stack([front.bytes, back.bytes], width);
    if (bytes.length <= maxChatAttachmentBytes) {
      return PickedAttachment(name: 'discount-id-front-back.png', bytes: bytes);
    }
  }
  throw const AttachmentTooLargeException();
}

Future<Uint8List> _stack(List<Uint8List> sides, int width) async {
  const gap = 24;
  final images = <ui.Image>[];
  try {
    for (final side in sides) {
      final codec = await ui.instantiateImageCodec(side, targetWidth: width);
      images.add((await codec.getNextFrame()).image);
      codec.dispose();
    }
    final height =
        images.fold(0, (sum, image) => sum + image.height) +
        gap * (images.length - 1);
    final recorder = ui.PictureRecorder();
    final canvas = ui.Canvas(recorder)
      ..drawColor(const ui.Color(0xFFFFFFFF), ui.BlendMode.src);
    var top = 0;
    for (final image in images) {
      canvas.drawImage(image, ui.Offset(0, top.toDouble()), ui.Paint());
      top += image.height + gap;
    }
    final picture = recorder.endRecording();
    final joined = await picture.toImage(width, height);
    picture.dispose();
    try {
      final png = await joined.toByteData(format: ui.ImageByteFormat.png);

      return png!.buffer.asUint8List();
    } finally {
      joined.dispose();
    }
  } finally {
    for (final image in images) {
      image.dispose();
    }
  }
}
