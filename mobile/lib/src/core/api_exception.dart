import 'package:dio/dio.dart';

class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    this.fields = const {},
    this.requestId,
    this.statusCode,
  });

  final String code;
  final String message;
  final Map<String, List<String>> fields;
  final String? requestId;
  final int? statusCode;

  bool get isUnauthenticated =>
      statusCode == 401 || code == 'auth.unauthenticated';

  /// The phone never got an answer: no connection, a timeout, or a cancelled request.
  bool get isNetwork => code == 'network.error' && statusCode == null;

  /// Trying again may work: the request never arrived, or the server failed on its side.
  bool get isRetryable => isNetwork || (statusCode ?? 0) >= 500;

  String get friendly {
    for (final values in fields.values) {
      if (values.isNotEmpty && values.first.trim().isNotEmpty) {
        return values.first;
      }
    }

    return message.isEmpty
        ? 'Something went wrong. Please try again.'
        : message;
  }

  factory ApiException.fromDio(DioException error) {
    final status = error.response?.statusCode;
    final body = error.response?.data;

    if (body is Map) {
      final envelope = body['error'];

      if (envelope is Map) {
        final fields = <String, List<String>>{};
        final rawFields = envelope['fields'];

        if (rawFields is Map) {
          rawFields.forEach((key, value) {
            if (value is List) {
              fields['$key'] = value
                  .map((item) => '$item')
                  .toList(growable: false);
            } else if (value != null) {
              fields['$key'] = ['$value'];
            }
          });
        }

        return ApiException(
          code: '${envelope['code'] ?? 'request.failed'}',
          message: '${envelope['message'] ?? 'Something went wrong.'}',
          fields: fields,
          requestId: body['request_id'] as String?,
          statusCode: status,
        );
      }

      if (body['message'] is String) {
        return ApiException(
          code: 'request.failed',
          message: body['message'] as String,
          statusCode: status,
        );
      }
    }

    final message = switch (error.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.sendTimeout ||
      DioExceptionType.receiveTimeout => 'Your connection is too slow right now. Check your Wi-Fi or mobile data and try again.',
      DioExceptionType.connectionError =>
        'No internet connection. Check your Wi-Fi or mobile data.',
      DioExceptionType.badCertificate =>
        'The server certificate could not be trusted.',
      DioExceptionType.cancel => 'The request was cancelled.',
      // Render answers 502/503 with a plain page while the server restarts.
      DioExceptionType.badResponse when (status ?? 0) >= 500 =>
        "Can't reach Everyday Pharmacy right now. Try again in a minute.",
      _ => 'Something went wrong. Please try again.',
    };

    return ApiException(
      code: 'network.error',
      message: message,
      statusCode: status,
    );
  }

  @override
  String toString() => 'ApiException($code): $message';
}
