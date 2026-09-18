import 'api_service.dart';
import '../../models/message.dart';

class MessageService {
  static Future<List<Message>> fetchInbox() async {
    final response = await ApiService.get('/messages');
    final List data = response['data'] ?? [];
    return data.map((json) => Message.fromJson(json)).toList();
  }

  static Future<List<Map<String, dynamic>>> fetchCounselors() async {
    final response = await ApiService.get('/counselors');
    return List<Map<String, dynamic>>.from(response['data'] ?? []);
  }

  static Future<Map<String, dynamic>> fetchThread(int id) async {
    final response = await ApiService.get('/messages/$id');
    return response;
  }

  static Future<void> sendMessage({
    required int receiverId,
    String? subject,
    required String content,
  }) async {
    await ApiService.post('/messages', body: {
      'receiver_id': receiverId,
      'subject': subject,
      'content': content,
    });
  }

  static Future<void> sendReply({
    required int parentId,
    required String content,
  }) async {
    await ApiService.post('/messages', body: {
      'parent_id': parentId,
      'content': content,
    });
  }
}
