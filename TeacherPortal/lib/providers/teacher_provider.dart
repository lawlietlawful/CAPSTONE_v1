import 'package:flutter/material.dart';
import '../core/constants/api_constants.dart';
import '../core/services/api_service.dart';
import '../models/advised_student.dart';
import '../models/behavioral_report.dart';
import '../models/dashboard_stats.dart';
import '../models/referral.dart';
import '../models/referral_detail.dart';
import '../models/app_notification.dart';
import '../models/roster_student.dart';
import '../models/student_detail.dart';

/// Holds the teacher's advised students, dashboard counters, filed reports and
/// referrals, and drives the two submit actions. One provider covers every tab
/// since they share data (a new submission changes the dashboard counters and,
/// when it escalates, the referral list).
class TeacherProvider extends ChangeNotifier {
  List<AdvisedStudent> _students = [];
  List<BehavioralReport> _reports = [];
  List<Referral> _referrals = [];

  DashboardStats _dashboard = DashboardStats.empty;

  /// Referral types are served by the API so this list can never drift from
  /// Referral::REFERRAL_TYPES on the backend. Falls back to the known set only
  /// if the referrals request hasn't resolved yet.
  List<String> _referralTypes = const [
    'Absences',
    'Tardiness',
    'Poor academic performance',
    'Misconduct',
    'Other',
  ];

  /// Incident types are served by the API so this list can never drift from
  /// BehavioralReport::INCIDENT_TYPES, which the escalation rules depend on.
  /// Seeded with the known set until the first reports request resolves.
  List<String> _incidentTypes = IncidentTypes.all;

  // "My Students" roster + at-risk summary.
  List<RosterStudent> _roster = [];
  Map<String, int> _riskSummary = const {};
  bool _loadingRoster = false;
  String? _rosterError;

  // Notifications / activity feed.
  List<AppNotification> _notifications = [];
  int _unreadNotifications = 0;
  bool _loadingNotifications = false;
  String? _notificationsError;

  // Search + status filter, per list. Empty search / null status = no filter.
  // The extra dimensions (type/priority/severity/date range) are optional
  // refinements on top of search+status, not a replacement for it.
  String _referralSearch = '';
  String? _referralStatus;
  String? _referralType;
  String? _referralPriority;
  String? _referralDateFrom; // yyyy-MM-dd
  String? _referralDateTo;
  String _reportSearch = '';
  String? _reportStatus;
  String? _reportIncidentType;
  String? _reportSeverity;
  String? _reportDateFrom;
  String? _reportDateTo;

  int _pendingReferralCount = 0;

  // Pagination state. `_total*` is the whole-history count from the server (for
  // the "X filed" header), independent of how many pages are loaded. `_*Page`
  // is the highest page fetched so far; `_hasMore*` drives infinite scroll.
  int _totalReferrals = 0;
  int _referralsPage = 1;
  bool _hasMoreReferrals = false;
  bool _loadingMoreReferrals = false;

  int _totalReports = 0;
  int _reportsPage = 1;
  bool _hasMoreReports = false;
  bool _loadingMoreReports = false;

  bool _loadingStudents = false;
  bool _loadingReports = false;
  bool _loadingReferrals = false;
  bool _loadingDashboard = false;
  bool _submitting = false;

  String? _studentsError;
  String? _reportsError;
  String? _referralsError;
  String? _dashboardError;

  List<AdvisedStudent> get students => _students;
  List<BehavioralReport> get reports => _reports;
  List<Referral> get referrals => _referrals;
  List<String> get referralTypes => _referralTypes;
  List<String> get incidentTypes => _incidentTypes;
  List<RosterStudent> get roster => _roster;
  Map<String, int> get riskSummary => _riskSummary;
  bool get loadingRoster => _loadingRoster;
  String? get rosterError => _rosterError;
  int get atRiskCount =>
      (_riskSummary['high'] ?? 0) + (_riskSummary['moderate'] ?? 0);
  List<AppNotification> get notifications => _notifications;
  int get unreadNotifications => _unreadNotifications;
  bool get loadingNotifications => _loadingNotifications;
  String? get notificationsError => _notificationsError;

  String get referralSearch => _referralSearch;
  String? get referralStatus => _referralStatus;
  String? get referralType => _referralType;
  String? get referralPriority => _referralPriority;
  String? get referralDateFrom => _referralDateFrom;
  String? get referralDateTo => _referralDateTo;
  bool get referralFilterActive =>
      _referralSearch.isNotEmpty ||
      _referralStatus != null ||
      _referralType != null ||
      _referralPriority != null ||
      _referralDateFrom != null ||
      _referralDateTo != null;
  String get reportSearch => _reportSearch;
  String? get reportStatus => _reportStatus;
  String? get reportIncidentType => _reportIncidentType;
  String? get reportSeverity => _reportSeverity;
  String? get reportDateFrom => _reportDateFrom;
  String? get reportDateTo => _reportDateTo;
  bool get reportFilterActive =>
      _reportSearch.isNotEmpty ||
      _reportStatus != null ||
      _reportIncidentType != null ||
      _reportSeverity != null ||
      _reportDateFrom != null ||
      _reportDateTo != null;

  /// Builds the `&key=value&...` query suffix from the given filter values,
  /// URL-encoding each value and skipping any that are null/empty.
  String _buildQuery(Map<String, String?> params) {
    final parts = <String>[];
    params.forEach((key, value) {
      final trimmed = value?.trim() ?? '';
      if (trimmed.isNotEmpty) {
        parts.add('$key=${Uri.encodeQueryComponent(trimmed)}');
      }
    });
    return parts.isEmpty ? '' : '&${parts.join('&')}';
  }

  DashboardStats get dashboard => _dashboard;
  int get pendingReferralCount => _pendingReferralCount;

  /// Whole-history totals from the server — use these for the "X filed" headers,
  /// not `referrals.length`/`reports.length`, which count only what's paged in.
  int get totalReferrals => _totalReferrals;
  int get totalReports => _totalReports;
  bool get hasMoreReferrals => _hasMoreReferrals;
  bool get hasMoreReports => _hasMoreReports;
  bool get loadingMoreReferrals => _loadingMoreReferrals;
  bool get loadingMoreReports => _loadingMoreReports;

  bool get loadingStudents => _loadingStudents;
  bool get loadingReports => _loadingReports;
  bool get loadingReferrals => _loadingReferrals;
  bool get loadingDashboard => _loadingDashboard;
  bool get submitting => _submitting;

  String? get studentsError => _studentsError;
  String? get reportsError => _reportsError;
  String? get referralsError => _referralsError;
  String? get dashboardError => _dashboardError;

  Future<void> loadDashboard() async {
    _loadingDashboard = true;
    _dashboardError = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.dashboard);
      _dashboard = DashboardStats.fromJson(data);
    } on ApiException catch (e) {
      _dashboardError = e.message;
    } catch (_) {
      _dashboardError = 'Unable to load your dashboard. Check your connection.';
    } finally {
      _loadingDashboard = false;
      notifyListeners();
    }
  }

  /// Sets the referral filters and reloads from page 1. Each value null/empty
  /// means "don't filter on this dimension". Callers should pass through the
  /// current provider value for anything they aren't changing (see
  /// referrals_screen.dart), the same way search/status already do.
  Future<void> applyReferralFilter({
    required String search,
    required String? status,
    String? referralType,
    String? priority,
    String? dateFrom,
    String? dateTo,
  }) async {
    _referralSearch = search;
    _referralStatus = (status == null || status.isEmpty) ? null : status;
    _referralType = (referralType == null || referralType.isEmpty)
        ? null
        : referralType;
    _referralPriority = (priority == null || priority.isEmpty)
        ? null
        : priority;
    _referralDateFrom = (dateFrom == null || dateFrom.isEmpty)
        ? null
        : dateFrom;
    _referralDateTo = (dateTo == null || dateTo.isEmpty) ? null : dateTo;
    await loadReferrals();
  }

  String get _referralQuery => _buildQuery({
    'search': _referralSearch,
    'status': _referralStatus,
    'referral_type': _referralType,
    'priority': _referralPriority,
    'date_from': _referralDateFrom,
    'date_to': _referralDateTo,
  });

  /// Loads (or reloads) the first page of referrals, replacing the list.
  Future<void> loadReferrals() async {
    _loadingReferrals = true;
    _referralsError = null;
    notifyListeners();
    try {
      final filter = _referralQuery;
      final data = await ApiService.get(
        '${ApiConstants.referrals}?page=1$filter',
      );
      final list = (data['referrals'] as List? ?? []);
      _referrals = list
          .map((e) => Referral.fromJson(e as Map<String, dynamic>))
          .toList();
      _pendingReferralCount = (data['pending_count'] as num?)?.toInt() ?? 0;

      final meta = (data['meta'] as Map<String, dynamic>?);
      _referralsPage = 1;
      _totalReferrals = (meta?['total'] as num?)?.toInt() ?? _referrals.length;
      _hasMoreReferrals = (meta?['has_more'] as bool?) ?? false;

      final types = (data['types'] as List? ?? []);
      if (types.isNotEmpty) {
        _referralTypes = types.map((e) => e.toString()).toList();
      }
    } on ApiException catch (e) {
      _referralsError = e.message;
    } catch (_) {
      _referralsError = 'Unable to load your referrals. Check your connection.';
    } finally {
      _loadingReferrals = false;
      notifyListeners();
    }
  }

  /// Appends the next page of referrals for infinite scroll. No-op when a page
  /// is already loading or the last page has been reached.
  Future<void> loadMoreReferrals() async {
    if (_loadingMoreReferrals || !_hasMoreReferrals) return;
    _loadingMoreReferrals = true;
    notifyListeners();
    try {
      final nextPage = _referralsPage + 1;
      final filter = _referralQuery;
      final data = await ApiService.get(
        '${ApiConstants.referrals}?page=$nextPage$filter',
      );
      final list = (data['referrals'] as List? ?? [])
          .map((e) => Referral.fromJson(e as Map<String, dynamic>))
          .toList();

      // Dedup by id: if a new referral was inserted at the head between page
      // loads, the offset shifts and an item could otherwise repeat.
      final seen = _referrals.map((r) => r.id).toSet();
      _referrals = [..._referrals, ...list.where((r) => !seen.contains(r.id))];

      final meta = (data['meta'] as Map<String, dynamic>?);
      _referralsPage = (meta?['current_page'] as num?)?.toInt() ?? nextPage;
      _hasMoreReferrals = (meta?['has_more'] as bool?) ?? false;
      _totalReferrals = (meta?['total'] as num?)?.toInt() ?? _totalReferrals;
    } on ApiException catch (e) {
      _referralsError = e.message;
    } catch (_) {
      _referralsError = 'Unable to load more referrals.';
    } finally {
      _loadingMoreReferrals = false;
      notifyListeners();
    }
  }

  /// Fetches one report with its escalated referral attached. Not cached —
  /// the detail screen always shows current guidance status.
  Future<BehavioralReport> fetchReport(int id) async {
    final data = await ApiService.get(ApiConstants.report(id));
    return BehavioralReport.fromJson(data['report'] as Map<String, dynamic>);
  }

  /// Files a referral. Returns the created [Referral] on success, or throws an
  /// [ApiException] the caller surfaces.
  Future<Referral> submitReferral({
    required int studentId,
    required String referralType,
    String? referralTypeOther,
    required String reason,
  }) async {
    _submitting = true;
    notifyListeners();
    try {
      final data = await ApiService.post(
        ApiConstants.referrals,
        body: {
          'student_id': studentId,
          'referral_type': referralType,
          'referral_type_other': referralTypeOther,
          'reason': reason,
        },
      );
      final referral = Referral.fromJson(
        data['referral'] as Map<String, dynamic>,
      );
      // Keep the list and counters in sync without another round-trip.
      _referrals = [referral, ..._referrals];
      _totalReferrals++;
      if (referral.status == 'pending') _pendingReferralCount++;
      return referral;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }

  /// Loads the "My Students" roster (advised students + current risk) and the
  /// at-risk summary tally.
  Future<void> loadRoster() async {
    _loadingRoster = true;
    _rosterError = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.roster);
      _roster = (data['students'] as List? ?? [])
          .map((e) => RosterStudent.fromJson(e as Map<String, dynamic>))
          .toList();
      final summary = (data['summary'] as Map?)?.cast<String, dynamic>() ?? {};
      _riskSummary = summary.map(
        (k, v) => MapEntry(k, (v as num?)?.toInt() ?? 0),
      );
    } on ApiException catch (e) {
      _rosterError = e.message;
    } catch (_) {
      _rosterError = 'Unable to load your students. Check your connection.';
    } finally {
      _loadingRoster = false;
      notifyListeners();
    }
  }

  /// Fetches one student's full detail (risk, case timeline, seminars). Not
  /// cached — the detail screen always shows the current picture.
  Future<StudentDetail> fetchStudentDetail(int id) async {
    final data = await ApiService.get(ApiConstants.studentDetail(id));
    return StudentDetail.fromJson(data);
  }

  /// Upcoming/ongoing seminars matching a recommended-intervention tag, shown
  /// when a student hasn't been enrolled in one yet.
  Future<List<SeminarItem>> fetchMatchingSeminars(String tag) async {
    final data = await ApiService.get(ApiConstants.matchingSeminars(tag));
    return (data['seminars'] as List? ?? [])
        .map(
          (e) => SeminarItem.fromJson(
            e as Map<String, dynamic>,
            isAssigned: false,
          ),
        )
        .toList();
  }

  /// Fetches one referral's outcome detail (status journey + counselor notes).
  /// Not cached — the detail always reflects current guidance status.
  Future<ReferralDetail> fetchReferralDetail(int id) async {
    final data = await ApiService.get(ApiConstants.referral(id));
    return ReferralDetail.fromJson(data);
  }

  /// Loads the notification feed + unread count for the header bell.
  Future<void> loadNotifications() async {
    _loadingNotifications = true;
    _notificationsError = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.notifications);
      _notifications = (data['notifications'] as List? ?? [])
          .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
          .toList();
      _unreadNotifications = (data['unread_count'] as num?)?.toInt() ?? 0;
    } on ApiException catch (e) {
      _notificationsError = e.message;
    } catch (_) {
      _notificationsError = 'Unable to load notifications.';
    } finally {
      _loadingNotifications = false;
      notifyListeners();
    }
  }

  /// Marks every notification read. Updates local state optimistically so the
  /// bell badge clears immediately.
  Future<void> markAllNotificationsRead() async {
    if (_unreadNotifications == 0) return;
    _notifications = _notifications
        .map(
          (n) => AppNotification(
            id: n.id,
            title: n.title,
            message: n.message,
            type: n.type,
            referenceType: n.referenceType,
            referenceId: n.referenceId,
            isRead: true,
            createdAt: n.createdAt,
          ),
        )
        .toList();
    _unreadNotifications = 0;
    notifyListeners();
    try {
      await ApiService.post(ApiConstants.notificationsReadAll);
    } catch (_) {
      // The badge is already cleared locally; a failed sync self-heals on the
      // next loadNotifications().
    }
  }

  /// Marks a single notification read (called when the teacher taps it to
  /// navigate). Updates local state optimistically.
  Future<void> markNotificationRead(int id) async {
    final index = _notifications.indexWhere((n) => n.id == id);
    if (index == -1 || _notifications[index].isRead) return;
    final n = _notifications[index];
    _notifications[index] = AppNotification(
      id: n.id,
      title: n.title,
      message: n.message,
      type: n.type,
      referenceType: n.referenceType,
      referenceId: n.referenceId,
      isRead: true,
      createdAt: n.createdAt,
    );
    if (_unreadNotifications > 0) _unreadNotifications--;
    notifyListeners();
    try {
      await ApiService.post(ApiConstants.notificationRead(id));
    } catch (_) {
      // The badge is already cleared locally; a failed sync self-heals on the
      // next loadNotifications().
    }
  }

  Future<void> loadStudents() async {
    _loadingStudents = true;
    _studentsError = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConstants.students);
      final list = (data['students'] as List? ?? []);
      _students = list
          .map((e) => AdvisedStudent.fromJson(e as Map<String, dynamic>))
          .toList();
    } on ApiException catch (e) {
      _studentsError = e.message;
    } catch (_) {
      _studentsError = 'Unable to load students. Check your connection.';
    } finally {
      _loadingStudents = false;
      notifyListeners();
    }
  }

  /// Sets the report filters and reloads from page 1. Each value null/empty
  /// means "don't filter on this dimension". Same pass-through-current-value
  /// convention as applyReferralFilter.
  Future<void> applyReportFilter({
    required String search,
    required String? status,
    String? incidentType,
    String? severity,
    String? dateFrom,
    String? dateTo,
  }) async {
    _reportSearch = search;
    _reportStatus = (status == null || status.isEmpty) ? null : status;
    _reportIncidentType = (incidentType == null || incidentType.isEmpty)
        ? null
        : incidentType;
    _reportSeverity = (severity == null || severity.isEmpty) ? null : severity;
    _reportDateFrom = (dateFrom == null || dateFrom.isEmpty) ? null : dateFrom;
    _reportDateTo = (dateTo == null || dateTo.isEmpty) ? null : dateTo;
    await loadReports();
  }

  String get _reportQuery => _buildQuery({
    'search': _reportSearch,
    'status': _reportStatus,
    'incident_type': _reportIncidentType,
    'severity': _reportSeverity,
    'date_from': _reportDateFrom,
    'date_to': _reportDateTo,
  });

  /// Loads (or reloads) the first page of reports, replacing the list.
  Future<void> loadReports() async {
    _loadingReports = true;
    _reportsError = null;
    notifyListeners();
    try {
      final filter = _reportQuery;
      final data = await ApiService.get(
        '${ApiConstants.reports}?page=1$filter',
      );
      final list = (data['reports'] as List? ?? []);
      _reports = list
          .map((e) => BehavioralReport.fromJson(e as Map<String, dynamic>))
          .toList();

      final meta = (data['meta'] as Map<String, dynamic>?);
      _reportsPage = 1;
      _totalReports = (meta?['total'] as num?)?.toInt() ?? _reports.length;
      _hasMoreReports = (meta?['has_more'] as bool?) ?? false;

      final types = (data['incident_types'] as List? ?? []);
      if (types.isNotEmpty) {
        _incidentTypes = types.map((e) => e.toString()).toList();
      }
    } on ApiException catch (e) {
      _reportsError = e.message;
    } catch (_) {
      _reportsError = 'Unable to load your reports. Check your connection.';
    } finally {
      _loadingReports = false;
      notifyListeners();
    }
  }

  /// Appends the next page of reports for infinite scroll. No-op when a page is
  /// already loading or the last page has been reached.
  Future<void> loadMoreReports() async {
    if (_loadingMoreReports || !_hasMoreReports) return;
    _loadingMoreReports = true;
    notifyListeners();
    try {
      final nextPage = _reportsPage + 1;
      final filter = _reportQuery;
      final data = await ApiService.get(
        '${ApiConstants.reports}?page=$nextPage$filter',
      );
      final list = (data['reports'] as List? ?? [])
          .map((e) => BehavioralReport.fromJson(e as Map<String, dynamic>))
          .toList();

      final seen = _reports.map((r) => r.id).toSet();
      _reports = [..._reports, ...list.where((r) => !seen.contains(r.id))];

      final meta = (data['meta'] as Map<String, dynamic>?);
      _reportsPage = (meta?['current_page'] as num?)?.toInt() ?? nextPage;
      _hasMoreReports = (meta?['has_more'] as bool?) ?? false;
      _totalReports = (meta?['total'] as num?)?.toInt() ?? _totalReports;
    } on ApiException catch (e) {
      _reportsError = e.message;
    } catch (_) {
      _reportsError = 'Unable to load more reports.';
    } finally {
      _loadingMoreReports = false;
      notifyListeners();
    }
  }

  /// Files a report. Returns the created [BehavioralReport] on success (so the
  /// screen can show the AI-assessed severity / escalation), or throws an
  /// [ApiException] the caller surfaces. On success the history is refreshed.
  Future<BehavioralReport> submitReport({
    required int studentId,
    required String incidentType,
    required String incidentDate,
    String? location,
    required String description,
  }) async {
    _submitting = true;
    notifyListeners();
    try {
      final data = await ApiService.post(
        ApiConstants.reports,
        body: {
          'student_id': studentId,
          'incident_type': incidentType,
          'incident_date': incidentDate,
          'location': location,
          'description': description,
        },
      );
      final report = BehavioralReport.fromJson(
        data['report'] as Map<String, dynamic>,
      );
      // Keep the history in sync without another round-trip.
      _reports = [report, ..._reports];
      _totalReports++;
      return report;
    } finally {
      _submitting = false;
      notifyListeners();
    }
  }
}
