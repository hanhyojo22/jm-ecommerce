import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:jmmart/src/core/api_exception.dart';

DioException _dioError({
  int? status,
  Object? body,
  DioExceptionType type = DioExceptionType.badResponse,
}) {
  final request = RequestOptions(path: '/test');
  return DioException(
    requestOptions: request,
    type: type,
    response: status == null && body == null
        ? null
        : Response(requestOptions: request, statusCode: status, data: body),
  );
}

void main() {
  group('ApiException.fromDio', () {
    test('reads the shared error envelope with field errors', () {
      final error = ApiException.fromDio(
        _dioError(
          status: 422,
          body: {
            'error': {
              'code': 'validation.failed',
              'message': 'Check the form.',
              'fields': {
                'quantity': ['Only 2 left in stock.'],
                'note': 'Too long.',
              },
            },
            'request_id': 'req-123',
          },
        ),
      );

      expect(error.code, 'validation.failed');
      expect(error.message, 'Check the form.');
      expect(error.statusCode, 422);
      expect(error.requestId, 'req-123');
      expect(error.fields['quantity'], ['Only 2 left in stock.']);
      expect(error.fields['note'], ['Too long.']);
    });

    test('falls back to a plain message body', () {
      final error = ApiException.fromDio(
        _dioError(status: 500, body: {'message': 'Server Error'}),
      );

      expect(error.code, 'request.failed');
      expect(error.message, 'Server Error');
    });

    test('explains timeouts and offline devices in plain words', () {
      final timeout = ApiException.fromDio(
        _dioError(type: DioExceptionType.receiveTimeout),
      );
      final offline = ApiException.fromDio(
        _dioError(type: DioExceptionType.connectionError),
      );

      expect(timeout.code, 'network.error');
      expect(timeout.message, contains('took too long'));
      expect(offline.message, contains('Cannot reach JMMart'));
    });
  });

  group('friendly', () {
    test('prefers the first field error over the general message', () {
      const error = ApiException(
        code: 'validation.failed',
        message: 'Check the form.',
        fields: {
          'quantity': ['Only 2 left in stock.'],
        },
      );

      expect(error.friendly, 'Only 2 left in stock.');
    });

    test('skips blank field errors and empty messages', () {
      const blankField = ApiException(
        code: 'x',
        message: 'Use this.',
        fields: {
          'name': ['  '],
        },
      );
      const empty = ApiException(code: 'x', message: '');

      expect(blankField.friendly, 'Use this.');
      expect(empty.friendly, 'Something went wrong. Please try again.');
    });
  });

  test('a 401 or the unauthenticated code means the session is gone', () {
    expect(
      const ApiException(
        code: 'x',
        message: '',
        statusCode: 401,
      ).isUnauthenticated,
      isTrue,
    );
    expect(
      const ApiException(
        code: 'auth.unauthenticated',
        message: '',
      ).isUnauthenticated,
      isTrue,
    );
    expect(
      const ApiException(
        code: 'x',
        message: '',
        statusCode: 403,
      ).isUnauthenticated,
      isFalse,
    );
  });
}
