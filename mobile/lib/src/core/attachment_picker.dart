import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

/// Server limit for chat attachments: 5 MB.
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

    if (file == null) {
      return null;
    }

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
