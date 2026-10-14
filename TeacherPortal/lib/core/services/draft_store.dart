import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../models/report_draft.dart';

/// Unsent reports, kept on the phone between app launches. Lives in the
/// same storage as the session, so signing out (which clears it) also
/// removes them — the next teacher on a shared phone never sees them.
class DraftStore {
  static const _key = 'report_drafts';

  static Future<List<ReportDraft>> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    try {
      return (jsonDecode(raw) as List)
          .map((e) => ReportDraft.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> save(List<ReportDraft> drafts) async {
    final prefs = await SharedPreferences.getInstance();
    if (drafts.isEmpty) {
      await prefs.remove(_key);
    } else {
      await prefs.setString(_key, jsonEncode(drafts.map((d) => d.toJson()).toList()));
    }
  }
}
