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
      DioExceptionType.receiveTimeout => 'The server took too long to answer. Check your connection and try again.',
      DioExceptionType.connectionError => 'Cannot reach JMMart. Check your connection and that API_BASE_URL points at the server.',
      DioExceptionType.badCertificate =>
        'The server certificate could not be trusted.',
      DioExceptionType.cancel => 'The request was cancelled.',
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
