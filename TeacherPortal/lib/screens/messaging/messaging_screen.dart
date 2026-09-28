import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/message.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import 'compose_message_screen.dart';
import 'message_thread_screen.dart';

/// Threads with a counselor: notices sent to the teacher, and any reply the
/// teacher has sent back. Pushed from the Messages floating action button,
/// not a bottom-nav tab, so — like Log Incident / File Referral — it uses a
/// plain AppBar rather than the navy tab header.
class MessagingScreen extends StatefulWidget {
  const MessagingScreen({super.key});

  @override
  State<MessagingScreen> createState() => _MessagingScreenState();
}

class _MessagingScreenState extends State<MessagingScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<TeacherProvider>().loadMessages();
    });
  }

  Future<void> _openThread(Message message) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MessageThreadScreen(messageId: message.id),
      ),
    );
    // The thread may have just been marked read, or gained a reply.
    if (mounted) context.read<TeacherProvider>().loadMessages();
  }

  Future<void> _openCompose() async {
    final sent = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const ComposeMessageScreen()),
    );
    if (sent == true && mounted) {
      context.read<TeacherProvider>().loadMessages();
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<TeacherProvider>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Messages',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_comment_outlined),
            tooltip: 'New message',
            onPressed: _openCompose,
          ),
        ],
      ),
      body: _buildBody(provider),
    );
  }

  Widget _buildBody(TeacherProvider provider) {
    if (provider.loadingMessages && provider.messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (provider.messagesError != null && provider.messages.isEmpty) {
      return CenteredMessage(
        icon: Icons.wifi_off_rounded,
        title: 'Could not load messages',
        message: provider.messagesError!,
        actionLabel: 'Retry',
        onAction: () => provider.loadMessages(),
      );
    }

    if (provider.messages.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => provider.loadMessages(),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 120),
            CenteredMessage(
              icon: Icons.mail_outline_rounded,
              title: 'No messages yet',
              message: 'Notices from your guidance counselor will appear here.',
              actionLabel: 'Message a counselor',
              onAction: _openCompose,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.loadMessages(),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        itemCount: provider.messages.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (_, i) {
          final message = provider.messages[i];
          return _MessageCard(
            message: message,
            onTap: () => _openThread(message),
          );
        },
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  final Message message;
  final VoidCallback onTap;
  const _MessageCard({required this.message, required this.onTap});

  String get _timeLabel {
    final dt = message.createdAt.toLocal();
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return DateFormat('MMM d, yyyy').format(dt);
  }

  static String _initial(String name) => name.isNotEmpty ? name[0].toUpperCase() : '?';

  @override
  Widget build(BuildContext context) {
    final unread = !message.isRead;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          // Unread gets a faint tint + accent border, same treatment as
          // NotificationsScreen's unread card.
          color: unread ? AppColors.accentLight : AppColors.surface,
          border: Border.all(
            color: unread ? AppColors.accent.withValues(alpha: 0.4) : AppColors.border,
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: unread ? AppColors.accent : AppColors.background,
              child: Text(
                _initial(message.senderName),
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: unread ? Colors.white : AppColors.text2,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          message.senderName,
                          style: GoogleFonts.inter(
                            fontSize: 13.5,
                            fontWeight: unread ? FontWeight.w700 : FontWeight.w600,
                            color: AppColors.text1,
                          ),
                        ),
                      ),
                      if (unread)
                        Container(
                          width: 8,
                          height: 8,
                          margin: const EdgeInsets.only(left: 6, top: 3),
                          decoration: const BoxDecoration(
                            color: AppColors.accent,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  if (message.subject != null && message.subject!.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      message.subject!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTextStyles.meta,
                    ),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    message.content,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.inter(
                      fontSize: 12.5,
                      color: AppColors.text2,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(_timeLabel, style: AppTextStyles.meta),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, size: 18, color: AppColors.text3),
          ],
        ),
      ),
    );
  }
}
