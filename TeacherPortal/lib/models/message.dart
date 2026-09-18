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

  Message({
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
  });

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      id: json['id'],
      senderId: json['sender_id'],
      receiverId: json['receiver_id'],
      subject: json['subject'],
      content: json['content'],
      readAt: json['read_at'] != null ? DateTime.parse(json['read_at']) : null,
      parentId: json['parent_id'],
      createdAt: DateTime.parse(json['created_at']),
      sender: json['sender'],
      receiver: json['receiver'],
    );
  }
}
