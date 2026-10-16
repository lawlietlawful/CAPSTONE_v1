import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../widgets/premium/skeleton.dart';
import '../../core/constants/app_text_styles.dart';
import '../../models/message.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';
import '../../widgets/common/surfaces.dart';
import 'compose_message_screen.dart';
import 'message_thread_screen.dart';

/// Threads with a counselor: notices sent to the teacher, and any reply the
/// teacher has sent back. Pushed from the Messages floating action button,
/// not a bottom-nav tab, so — like Report a concern / File a referral — it uses a
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
        title: const Text('Messages'),
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
      return const SkeletonList(leading: true);
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
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          CardGroup(
            children: [
              for (final message in provider.messages)
                _MessageCard(message: message, onTap: () => _openThread(message)),
            ],
          ),
        ],
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

  @override
  Widget build(BuildContext context) {
    final unread = !message.isRead;
    final hasSubject = message.subject != null && message.subject!.isNotEmpty;

    return Semantics(
      label: unread ? 'Unread' : null,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              InitialsAvatar(initials: InitialsAvatar.of(message.senderName), size: 40, dark: true),
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
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.publicSans(
                              fontSize: 14,
                              fontWeight: unread ? FontWeight.w700 : FontWeight.w600,
                              color: AppColors.text1,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(_timeLabel, style: AppTextStyles.meta.copyWith(fontSize: 12)),
                      ],
                    ),
                    if (hasSubject) ...[
                      const SizedBox(height: 1),
                      Text(
                        message.subject!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.publicSans(
                          fontSize: 13.5,
                          fontWeight: unread ? FontWeight.w600 : FontWeight.w500,
                          color: const Color(0xFF2B3648),
                        ),
                      ),
                    ],
                    const SizedBox(height: 2),
                    Text(
                      message.content,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.publicSans(fontSize: 13, color: AppColors.text2, height: 19 / 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 20,
                child: Column(
                  children: [
                    if (unread)
                      Container(
                        width: 8,
                        height: 8,
                        margin: const EdgeInsets.only(top: 6, bottom: 8),
                        decoration: const BoxDecoration(color: AppColors.accent, shape: BoxShape.circle),
                      ),
                    const Icon(Icons.chevron_right_rounded, size: 20, color: AppColors.iconMuted),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
