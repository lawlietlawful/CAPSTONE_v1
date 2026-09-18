import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/services/message_service.dart';
import '../../models/message.dart';
import 'compose_message_screen.dart';

class MessagingScreen extends StatefulWidget {
  const MessagingScreen({super.key});

  @override
  State<MessagingScreen> createState() => _MessagingScreenState();
}

class _MessagingScreenState extends State<MessagingScreen> {
  List<Message> _inbox = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadInbox();
  }

  Future<void> _loadInbox() async {
    setState(() => _isLoading = true);
    try {
      final messages = await MessageService.fetchInbox();
      setState(() {
        _inbox = messages;
      });
    } catch (e) {
      // Handle error gently
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text('Counselor Notices', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 18)),
        backgroundColor: AppColors.navy,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadInbox,
              child: _inbox.isEmpty
                  ? ListView(
                      children: const [
                        SizedBox(height: 100),
                        Center(
                          child: Column(
                            children: [
                              Icon(Icons.inbox_outlined, size: 64, color: Colors.black26),
                              SizedBox(height: 16),
                              Text('No messages found', style: TextStyle(color: Colors.black54)),
                            ],
                          ),
                        ),
                      ],
                    )
                  : ListView.builder(
                      itemCount: _inbox.length,
                      itemBuilder: (context, index) {
                        final msg = _inbox[index];
                        final senderName = msg.sender != null ? msg.sender!['name'] : 'Unknown';
                        final isUnread = msg.readAt == null;

                        return Container(
                          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          decoration: BoxDecoration(
                            color: isUnread ? Colors.blue[50] : Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.grey[200]!),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.02),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: ListTile(
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            leading: CircleAvatar(
                              backgroundColor: isUnread ? AppColors.accent : Colors.grey[200],
                              foregroundColor: isUnread ? Colors.white : Colors.black54,
                              child: Text(senderName.substring(0, 1).toUpperCase()),
                            ),
                            title: Text(
                              senderName,
                              style: TextStyle(fontWeight: isUnread ? FontWeight.bold : FontWeight.w600, fontSize: 15),
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const SizedBox(height: 4),
                                Text(
                                  msg.content,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(color: isUnread ? Colors.black87 : Colors.black54),
                                ),
                              ],
                            ),
                            trailing: isUnread
                                ? Container(
                                    width: 10,
                                    height: 10,
                                    decoration: const BoxDecoration(
                                      color: AppColors.accent,
                                      shape: BoxShape.circle,
                                    ),
                                  )
                                : null,
                            onTap: () async {
                              // Show dialog
                              showDialog(
                                context: context,
                                builder: (ctx) => AlertDialog(
                                  title: Text('Notice from $senderName'),
                                  content: SingleChildScrollView(
                                    child: Text(msg.content, style: const TextStyle(fontSize: 16)),
                                  ),
                                  actions: [
                                    TextButton(
                                      onPressed: () => Navigator.pop(ctx),
                                      child: const Text('Close'),
                                    ),
                                  ],
                                ),
                              );

                              // Mark as read by hitting the show endpoint (the
                              // backend marks read_at as a side effect of GET).
                              if (isUnread) {
                                try {
                                  await MessageService.fetchThread(msg.id);
                                  _loadInbox();
                                } catch (e) {
                                  // ignore
                                }
                              }
                            },
                          ),
                        );
                      },
                    ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const ComposeMessageScreen()),
          );
          if (result == true) {
            _loadInbox();
          }
        },
        backgroundColor: AppColors.accent,
        icon: const Icon(Icons.send_rounded, color: Colors.white),
        label: const Text('Message Counselor', style: TextStyle(color: Colors.white)),
      ),
    );
  }
}
