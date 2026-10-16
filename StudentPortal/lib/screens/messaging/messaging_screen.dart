import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/app_decor.dart';
import '../../core/constants/app_text_styles.dart';
import '../../core/services/message_service.dart';
import '../../models/message.dart';
import '../../widgets/common/surfaces.dart';
import '../../widgets/premium/skeleton.dart';

/// Notices the Guidance Office sent the student (read-only). Opening one
/// marks it read.
class MessagingScreen extends StatefulWidget {
  const MessagingScreen({super.key});

  @override
  State<MessagingScreen> createState() => _MessagingScreenState();
}

class _MessagingScreenState extends State<MessagingScreen> {
  List<Message> _inbox = [];
  bool _isLoading = true;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _loadInbox();
  }

  Future<void> _loadInbox() async {
    setState(() {
      _isLoading = _inbox.isEmpty;
      _failed = false;
    });
    try {
      final messages = await MessageService.fetchInbox();
      if (!mounted) return;
      setState(() => _inbox = messages);
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _senderName(Message msg) => msg.sender?['name']?.toString() ?? 'Guidance Office';

  Future<void> _open(Message msg) async {
    final isUnread = msg.readAt == null;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => _NoticeSheet(
        sender: _senderName(msg),
        subject: msg.subject,
        content: msg.content,
        sentAt: msg.createdAt,
      ),
    );

    // If unread, mark as read by hitting the show endpoint (the backend marks
    // read_at as a side effect of GET).
    if (isUnread) {
      try {
        await MessageService.fetchThread(msg.id);
        _loadInbox(); // Refresh to update the unread indicator
      } catch (_) {
        // ignore — it stays unread and is marked next time
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Notices from Guidance')),
      body: _isLoading
          ? const SkeletonList(count: 4, leading: true, padding: EdgeInsets.fromLTRB(16, 4, 16, 16))
          : RefreshIndicator(
              onRefresh: _loadInbox,
              color: AppColors.accent,
              child: _inbox.isEmpty
                  ? _Empty(failed: _failed, onRetry: _loadInbox)
                  : ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                      children: [
                        CardGroup(
                          children: [
                            for (final msg in _inbox)
                              _NoticeRow(
                                sender: _senderName(msg),
                                subject: msg.subject,
                                content: msg.content,
                                sentAt: msg.createdAt,
                                unread: msg.readAt == null,
                                onTap: () => _open(msg),
                              ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        const InfoNote(
                          icon: Icons.storefront_outlined,
                          text: 'To reply, visit the Guidance Office or talk to your adviser.',
                        ),
                      ],
                    ),
            ),
    );
  }
}

String _when(DateTime sent) {
  final local = sent.toLocal();
  final now = DateTime.now();
  if (DateUtils.isSameDay(local, now)) return DateFormat('h:mm a').format(local);
  if (local.year == now.year) return DateFormat('MMM d').format(local);
  return DateFormat('MMM d, yyyy').format(local);
}

class _NoticeRow extends StatelessWidget {
  final String sender;
  final String? subject;
  final String content;
  final DateTime sentAt;
  final bool unread;
  final VoidCallback onTap;

  const _NoticeRow({
    required this.sender,
    required this.subject,
    required this.content,
    required this.sentAt,
    required this.unread,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasSubject = subject != null && subject!.trim().isNotEmpty;
    return Semantics(
      label: unread ? 'Unread' : null,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              InitialsAvatar(initials: InitialsAvatar.of(sender), size: 40, dark: true),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            sender,
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
                        Text(_when(sentAt), style: AppTextStyles.meta.copyWith(fontSize: 12)),
                      ],
                    ),
                    if (hasSubject) ...[
                      const SizedBox(height: 1),
                      Text(
                        subject!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.publicSans(fontSize: 13.5, fontWeight: unread ? FontWeight.w600 : FontWeight.w500, color: const Color(0xFF2B3648)),
                      ),
                    ],
                    const SizedBox(height: 2),
                    Text(
                      content,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.publicSans(fontSize: 13, height: 19 / 13, color: AppColors.text2),
                    ),
                  ],
                ),
              ),
              if (unread) ...[
                const SizedBox(width: 10),
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.only(top: 6),
                  decoration: const BoxDecoration(color: AppColors.accent, shape: BoxShape.circle),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// The full notice, read-only.
class _NoticeSheet extends StatelessWidget {
  final String sender;
  final String? subject;
  final String content;
  final DateTime sentAt;

  const _NoticeSheet({required this.sender, required this.subject, required this.content, required this.sentAt});

  @override
  Widget build(BuildContext context) {
    final local = sentAt.toLocal();
    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.8),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(color: AppColors.borderStrong, borderRadius: BorderRadius.circular(2)),
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  InitialsAvatar(initials: InitialsAvatar.of(sender), size: 40, dark: true),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Notice from $sender', style: AppTextStyles.sectionTitle),
                        Text(DateFormat('MMM d, yyyy · h:mm a').format(local), style: AppTextStyles.meta),
                      ],
                    ),
                  ),
                ],
              ),
              if (subject != null && subject!.trim().isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(subject!, style: AppTextStyles.pageTitle.copyWith(fontSize: 18)),
              ],
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: AppDecor.card(color: AppColors.background, shadow: const []),
                child: SelectableText(
                  content,
                  style: GoogleFonts.publicSans(fontSize: 15, height: 1.55, color: AppColors.text1),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Close'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  final bool failed;
  final VoidCallback onRetry;
  const _Empty({required this.failed, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 36),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: AppDecor.card(radius: 28),
                    child: Icon(failed ? Icons.wifi_off_rounded : Icons.inbox_outlined, size: 26, color: AppColors.text3),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    failed ? 'Could not load notices' : 'No notices yet',
                    textAlign: TextAlign.center,
                    style: AppTextStyles.sectionHeading,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    failed
                        ? 'Check your connection, then try again.'
                        : 'Messages from the Guidance Office will appear here.',
                    textAlign: TextAlign.center,
                    style: AppTextStyles.meta.copyWith(fontSize: 13.5),
                  ),
                  if (failed) ...[
                    const SizedBox(height: 16),
                    OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
                  ],
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
