import 'dart:ui' as ui;

import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/attachment_picker.dart';

Future<PickedAttachment> _png(String name, int width, int height) async {
  final recorder = ui.PictureRecorder();
  ui.Canvas(recorder).drawColor(const ui.Color(0xFF3366CC), ui.BlendMode.src);
  final image = await recorder.endRecording().toImage(width, height);
  final png = await image.toByteData(format: ui.ImageByteFormat.png);

  return PickedAttachment(name: name, bytes: png!.buffer.asUint8List());
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('the front and back of an ID become one PNG, front on top', () async {
    final joined = await stackCardSides(
      await _png('front.jpg', 856, 540),
      await _png('back.jpg', 428, 270),
    );

    expect(joined.mimeType, 'image/png');
    final codec = await ui.instantiateImageCodec(joined.bytes);
    final image = (await codec.getNextFrame()).image;
    // Both sides scaled to 1400 wide (883 tall each) with a 24px gap.
    expect(image.width, 1400);
    expect(image.height, 883 + 24 + 883);
  });
}
