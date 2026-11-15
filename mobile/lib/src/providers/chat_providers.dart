import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gal/gal.dart';
import 'package:http/http.dart' as http;

import '../config.dart';
import '../core/attachment_picker.dart';
import '../core/reverb_client.dart';
import '../models/models.dart';
import 'auth_providers.dart';

/// Downloads a chat photo and saves it to the phone gallery. Overridden in tests.
final imageSaverProvider =
    Provider<Future<void> Function(String url, String name)>(
      (ref) => (url, name) async {
        final response = await http.get(Uri.parse(url));

        if (response.statusCode != 200) {
          throw http.ClientException(
            'Download failed (${response.statusCode})',
          );
        }

        if (!await Gal.hasAccess(toAlbum: true)) {
          await Gal.requestAccess(toAlbum: true);
        }

        await Gal.putImageBytes(
          response.bodyBytes,
          album: 'JMMart',
          name: name.replaceAll(RegExp(r'\.[^.]+$'), ''),
        );
      },
    );

final conversationsProvider = FutureProvider.autoDispose<List<Conversation>>(
  (ref) => ref.watch(apiClientProvider).conversations(),
);

final conversationProvider = FutureProvider.autoDispose
    .family<Conversation, String>(
      (ref, id) => ref.watch(apiClientProvider).conversation(id),
    );

final messagesProvider = FutureProvider.autoDispose
    .family<List<ChatMessage>, String>(
      (ref, id) => ref.watch(apiClientProvider).messages(id),
    );

final attachmentPickerProvider = Provider<AttachmentPicker>(
  (ref) => const FilePickerAttachmentPicker(),
);

final reverbProvider = Provider.autoDispose<ReverbClient>((ref) {
  final api = ref.watch(apiClientProvider);
  final client = ReverbClient(
    key: AppConfig.reverbKey,
    host: AppConfig.reverbHost,
    port: AppConfig.reverbPort,
    useTls: AppConfig.reverbUseTls,
    origin: AppConfig.reverbOrigin,
    authorize: (socketId, channelName) =>
        api.broadcastAuth(socketId: socketId, channelName: channelName),
  );

  ref.onDispose(client.dispose);

  return client;
});
