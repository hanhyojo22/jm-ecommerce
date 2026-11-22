import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_client.dart';
import 'package:jmmart/src/core/api_exception.dart';

/// Fails the first request as a dropped connection, then answers normally.
class _DropsFirstConnection implements HttpClientAdapter {
  int calls = 0;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    calls++;
    if (calls == 1) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'Connection reset by peer',
      );
    }

    return ResponseBody.fromString(
      jsonEncode({
        'data': [
          {'id': 'c1', 'name': 'Pain & Fever', 'slug': 'pain-fever'},
        ],
      }),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

ApiClient _client(HttpClientAdapter adapter) => ApiClient(
  baseUrl: 'https://example.test/api/v1',
  tokenProvider: () async => null,
  httpClientAdapter: adapter,
);

void main() {
  test('a read retries once when the connection drops', () async {
    final adapter = _DropsFirstConnection();

    final categories = await _client(adapter).categories();

    expect(categories.single.slug, 'pain-fever');
    expect(adapter.calls, 2);
  });

  test('a write is never retried, so an order cannot be sent twice', () async {
    final adapter = _DropsFirstConnection();

    await expectLater(
      _client(adapter).deleteAccount(),
      throwsA(isA<ApiException>()),
    );
    expect(adapter.calls, 1);
  });
}
