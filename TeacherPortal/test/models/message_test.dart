import 'package:flutter_test/flutter_test.dart';
import 'package:teacher_portal/models/message.dart';

void main() {
  Map<String, dynamic> baseJson({Map<String, dynamic>? overrides}) => {
        'id': 1,
        'sender_id': 9,
        'receiver_id': 4,
        'subject': 'Reminder',
        'content': 'Please see me about Angelo.',
        'read_at': null,
        'parent_id': null,
        'created_at': '2026-09-23T08:00:00Z',
        'sender': {'id': 9, 'name': "Ma'am Edago", 'role': 'admin'},
        'receiver': {'id': 4, 'name': 'Mr. Ramos', 'role': 'teacher'},
        ...?overrides,
      };

  test('parses a top-level message with no replies', () {
    final m = Message.fromJson(baseJson());

    expect(m.id, 1);
    expect(m.subject, 'Reminder');
    expect(m.isRead, isFalse);
    expect(m.senderName, "Ma'am Edago");
    expect(m.replies, isNull);
  });

  test('isRead reflects read_at', () {
    final m = Message.fromJson(baseJson(overrides: {'read_at': '2026-09-23T09:00:00Z'}));
    expect(m.isRead, isTrue);
  });

  test('senderName falls back when sender was not eager-loaded', () {
    final m = Message.fromJson(baseJson(overrides: {'sender': null}));
    expect(m.senderName, 'Unknown');
  });

  test('parses nested replies from the thread endpoint, oldest first', () {
    final m = Message.fromJson(baseJson(overrides: {
      'replies': [
        {
          'id': 2, 'sender_id': 4, 'receiver_id': 9, 'subject': null,
          'content': 'On my way.', 'read_at': null, 'parent_id': 1,
          'created_at': '2026-09-23T08:05:00Z',
          'sender': {'id': 4, 'name': 'Mr. Ramos', 'role': 'teacher'},
          'receiver': {'id': 9, 'name': "Ma'am Edago", 'role': 'admin'},
        },
      ],
    }));

    expect(m.replies, hasLength(1));
    expect(m.replies!.first.content, 'On my way.');
    expect(m.replies!.first.parentId, 1);
    expect(m.replies!.first.replies, isNull, reason: 'replies never nest more than one level');
  });

  test('a non-list replies value is treated as no replies, not a crash', () {
    final m = Message.fromJson(baseJson(overrides: {'replies': 'not-a-list'}));
    expect(m.replies, isNull);
  });
}
