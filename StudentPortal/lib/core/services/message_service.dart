import 'api_service.dart';
import '../../models/message.dart';

class MessageService {
  static Future<List<Message>> fetchInbox() async {
    final response = await ApiService.get('/messages');
    final List data = response['data'] ?? [];
    return data.map((json) => Message.fromJson(json)).toList();
  }

  static Future<Map<String, dynamic>> fetchThread(int id) async {
    final response = await ApiService.get('/messages/$id');
    return response;
  }

  // Students cannot initiate new messages, but can reply if the thread allows it.
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
