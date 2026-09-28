/// A message between a teacher and a counselor, as returned by
/// GET /api/messages, GET /api/messages/sent, GET /api/messages/{id}, and the
/// POST create/reply response's `data`.
class Message {
  final int id;
  final int senderId;
  final int receiverId;
  final String? subject;
  final String content;
  final DateTime? readAt;
  final int? parentId;
  final DateTime createdAt;
  final Map<String, dynamic>? sender;
  final Map<String, dynamic>? receiver;

  /// Only populated by GET /api/messages/{id} (the thread endpoint) — the
  /// replies to this message, oldest first. Null on every other payload,
  /// including a top-level message inside the inbox/sent list.
  final List<Message>? replies;

  const Message({
    required this.id,
    required this.senderId,
    required this.receiverId,
    this.subject,
    required this.content,
    this.readAt,
    this.parentId,
    required this.createdAt,
    this.sender,
    this.receiver,
    this.replies,
  });

  bool get isRead => readAt != null;

  /// The other party's display name — whichever of sender/receiver is
  /// actually loaded on this payload. Falls back to a generic label rather
  /// than throwing when a relation wasn't eager-loaded.
  String get senderName => sender?['name']?.toString() ?? 'Unknown';

  factory Message.fromJson(Map<String, dynamic> json) {
    final repliesJson = json['replies'];
    return Message(
      id: (json['id'] as num).toInt(),
      senderId: (json['sender_id'] as num).toInt(),
      receiverId: (json['receiver_id'] as num).toInt(),
      subject: json['subject']?.toString(),
      content: json['content']?.toString() ?? '',
      readAt: json['read_at'] != null
          ? DateTime.tryParse(json['read_at'].toString())
          : null,
      parentId: json['parent_id'] == null
          ? null
          : (json['parent_id'] as num).toInt(),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
      sender: (json['sender'] as Map?)?.cast<String, dynamic>(),
      receiver: (json['receiver'] as Map?)?.cast<String, dynamic>(),
      replies: repliesJson is List
          ? repliesJson
              .whereType<Map>()
              .map((e) => Message.fromJson(e.cast<String, dynamic>()))
              .toList()
          : null,
    );
  }
}
