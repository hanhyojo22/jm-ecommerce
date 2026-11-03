import 'package:clerk_auth/clerk_auth.dart' as clerk;
import 'package:http/http.dart' as http;

/// Marks every Clerk Frontend API request as native.
///
/// A development instance rejects an environment bootstrap that carries
/// neither a development-browser token nor the `_is_native` marker. Keeping
/// the marker on every request avoids `dev_browser_unauthenticated`, and the
/// timeout stops a stalled auth request from leaving the loading overlay up.
class NativeClerkHttpService implements clerk.HttpService {
  const NativeClerkHttpService();

  static const clerk.DefaultHttpService _delegate = clerk.DefaultHttpService();

  Uri _native(Uri uri) =>
      uri.replace(queryParameters: {...uri.queryParameters, '_is_native': '1'});

  Map<String, String> _headers(Map<String, String>? headers) => {
    ...?headers,
    'x-mobile': '1',
  };

  @override
  Future<void> initialize() => _delegate.initialize();

  @override
  void terminate() => _delegate.terminate();

  @override
  Future<bool> ping(Uri uri, {required Duration timeout}) =>
      _delegate.ping(_native(uri), timeout: timeout);

  @override
  Future<http.Response> send(
    clerk.HttpMethod method,
    Uri uri, {
    Map<String, String>? headers,
    Map<String, dynamic>? params,
    String? body,
  }) => _delegate
      .send(
        method,
        _native(uri),
        headers: _headers(headers),
        params: params,
        body: body,
      )
      .timeout(const Duration(seconds: 30));

  @override
  Future<http.Response> sendByteStream(
    clerk.HttpMethod method,
    Uri uri,
    http.ByteStream byteStream,
    int length,
    Map<String, String> headers,
  ) => _delegate.sendByteStream(
    method,
    _native(uri),
    byteStream,
    length,
    _headers(headers),
  );
}
