import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/services/api_service.dart';
import '../../models/message.dart';
import '../../providers/teacher_provider.dart';
import '../../widgets/common/form_widgets.dart';

/// One thread with a counselor: the original notice, every reply in order,
/// and a box to send another. Fetching marks the thread read as a
/// server-side side effect (GET /messages/{id}).
class MessageThreadScreen extends StatefulWidget {
  final int messageId;
  const MessageThreadScreen({super.key, required this.messageId});

  @override
  State<MessageThreadScreen> createState() => _MessageThreadScreenState();
}

class _MessageThreadScreenState extends State<MessageThreadScreen> {
  late Future<Message> _future;
  final _replyController = TextEditingController();
  bool _sending = false;
  String? _sendError;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _replyController.dispose();
    super.dispose();
  }

  Future<Message> _load() =>
      context.read<TeacherProvider>().fetchThread(widget.messageId);

  void _retry() => setState(() => _future = _load());

  Future<void> _send() async {
    final content = _replyController.text.trim();
    if (content.isEmpty) return;

    setState(() {
      _sending = true;
      _sendError = null;
    });
    try {
      await context.read<TeacherProvider>().sendReply(
            parentId: widget.messageId,
            content: content,
          );
      _replyController.clear();
      if (!mounted) return;
      // Re-fetch rather than append locally: mirrors the rest of the app's
      // "detail screens always reflect the server" convention.
      setState(() => _future = _load());
    } on ApiException catch (e) {
      setState(() => _sendError = e.message);
    } catch (_) {
      setState(() => _sendError = 'Unable to send. Check your connection.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Message',
          style: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w500),
        ),
      ),
      body: FutureBuilder<Message>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError || !snap.hasData) {
            return CenteredMessage(
              icon: Icons.wifi_off_rounded,
              title: 'Could not load this message',
              message: 'Please check your connection and try again.',
              actionLabel: 'Retry',
              onAction: _retry,
            );
          }
          return _buildThread(snap.data!);
        },
      ),
    );
  }

  Widget _buildThread(Message root) {
    // Every thread reached from the inbox has this teacher as the root
    // message's receiver (that's what "inbox" means), so it's a reliable way
    // to tell which side of each bubble is "me" — replies then alternate
    // sender/receiver around that fixed point.
    final meId = root.receiverId;
    final all = [root, ...?root.replies];

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            children: [
              if (root.subject != null && root.subject!.isNotEmpty) ...[
                Text(root.subject!, style: AppTextStyles.pageTitle),
                const SizedBox(height: 14),
              ],
              for (final m in all)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _Bubble(message: m, isMe: m.senderId == meId),
                ),
            ],
          ),
        ),
        _ReplyBar(
          controller: _replyController,
          sending: _sending,
          error: _sendError,
          onSend: _send,
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  final Message message;
  final bool isMe;
  const _Bubble({required this.message, required this.isMe});

  String get _timeLabel =>
      DateFormat('MMM d, h:mm a').format(message.createdAt.toLocal());

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints:
            BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: isMe ? AppColors.accent : AppColors.surface,
            border: isMe ? null : Border.all(color: AppColors.border),
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(14),
              topRight: const Radius.circular(14),
              bottomLeft: Radius.circular(isMe ? 14 : 4),
              bottomRight: Radius.circular(isMe ? 4 : 14),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                isMe ? 'You' : message.senderName,
                style: GoogleFonts.inter(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: isMe ? Colors.white70 : AppColors.text3,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                message.content,
                style: GoogleFonts.inter(
                  fontSize: 13.5,
                  height: 1.45,
                  color: isMe ? Colors.white : AppColors.text1,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _timeLabel,
                style: GoogleFonts.inter(
                  fontSize: 10.5,
                  color: isMe ? Colors.white70 : AppColors.text3,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReplyBar extends StatelessWidget {
  final TextEditingController controller;
  final bool sending;
  final String? error;
  final VoidCallback onSend;

  const _ReplyBar({
    required this.controller,
    required this.sending,
    required this.error,
    required this.onSend,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        decoration: const BoxDecoration(
          color: AppColors.surface,
          border: Border(top: BorderSide(color: AppColors.border)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (error != null) ...[
              ErrorBanner(error!),
              const SizedBox(height: 8),
            ],
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: TextField(
                    controller: controller,
                    minLines: 1,
                    maxLines: 4,
                    textInputAction: TextInputAction.newline,
                    style: GoogleFonts.inter(fontSize: 13.5, color: AppColors.text1),
                    decoration: InputDecoration(
                      hintText: 'Write a reply...',
                      hintStyle: GoogleFonts.inter(fontSize: 13.5, color: AppColors.text3),
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.background,
                      contentPadding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(22),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(22),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(22),
                        borderSide: const BorderSide(color: AppColors.accent, width: 1.5),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  width: 44,
                  height: 44,
                  child: IconButton.filled(
                    onPressed: sending ? null : onSend,
                    style: IconButton.styleFrom(backgroundColor: AppColors.accent),
                    icon: sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.send_rounded, size: 18, color: Colors.white),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
