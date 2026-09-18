import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/services/message_service.dart';

class ComposeMessageScreen extends StatefulWidget {
  const ComposeMessageScreen({super.key});

  @override
  State<ComposeMessageScreen> createState() => _ComposeMessageScreenState();
}

class _ComposeMessageScreenState extends State<ComposeMessageScreen> {
  final _subjectController = TextEditingController();
  final _contentController = TextEditingController();
  List<Map<String, dynamic>> _counselors = [];
  int? _selectedCounselorId;
  bool _isLoading = true;
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _loadCounselors();
  }

  Future<void> _loadCounselors() async {
    try {
      final counselors = await MessageService.fetchCounselors();
      setState(() {
        _counselors = counselors;
        if (_counselors.isNotEmpty) {
          _selectedCounselorId = _counselors.first['id'];
        }
      });
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _send() async {
    if (_contentController.text.trim().isEmpty) return;
    if (_selectedCounselorId == null) return;

    setState(() => _isSending = true);
    try {
      await MessageService.sendMessage(
        receiverId: _selectedCounselorId!,
        subject: _subjectController.text.trim(),
        content: _contentController.text.trim(),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to send message: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _contentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text('Compose Notice', style: TextStyle(fontWeight: FontWeight.w600)),
        backgroundColor: AppColors.navy,
        foregroundColor: Colors.white,
        actions: [
          if (_isSending)
            const Center(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 20),
                child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
              ),
            )
          else
            IconButton(
              icon: const Icon(Icons.send_rounded),
              onPressed: _contentController.text.trim().isEmpty ? null : _send,
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('To:', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.black54)),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<int>(
                    initialValue: _selectedCounselorId,
                    decoration: InputDecoration(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[300]!)),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[300]!)),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.accent, width: 2)),
                    ),
                    items: _counselors.map((c) {
                      return DropdownMenuItem<int>(
                        value: c['id'],
                        child: Text('${c['name']} (Counselor)'),
                      );
                    }).toList(),
                    onChanged: (val) => setState(() => _selectedCounselorId = val),
                  ),

                  const SizedBox(height: 20),
                  const Text('Message:', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.black54)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _contentController,
                    maxLines: 8,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      hintText: 'Type your message here...',
                      contentPadding: const EdgeInsets.all(16),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[300]!)),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey[300]!)),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.accent, width: 2)),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
