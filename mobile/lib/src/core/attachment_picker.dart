import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

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
