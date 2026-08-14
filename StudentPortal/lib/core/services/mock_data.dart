class MockData {
  static const mockToken = 'mock-token-demo';
  static const studentId = '2025-0001';
  static const password = 'student123';

  static const student = <String, dynamic>{
    'id': 1,
    'first_name': 'Maria',
    'last_name': 'Santos',
    'email': 'maria.santos@student.mu.edu.ph',
    'grade_level': '3rd Year',
    'section': 'BSIT',
    'school_id': studentId,
  };

  static Future<Map<String, dynamic>> response(String endpoint) async {
    await Future.delayed(const Duration(milliseconds: 700));

    if (endpoint.startsWith('/student/notifications/read/') ||
        endpoint == '/student/notifications/read-all') {
      return {'message': 'Marked as read', 'success': true};
    }

    switch (endpoint) {
      case '/student/profile':
        return {'student': student};

      case '/student/risk-level':
        return <String, dynamic>{
          'has_assessment': true,
          'risk_level': 'moderate',
          'risk_score': 62.5,
          'assessed_at': '2026-06-25',
        };

      case '/student/referrals':
        final referral = <String, dynamic>{
          'id': 1,
          'concern_type': 'Excessive Absences',
          'reason':
              'Student has been frequently absent without a valid reason.',
          'priority': 'high',
          'status': 'pending',
          'referred_by_name': 'Sir Santos',
          'created_at': '2026-06-28T10:00:00.000000Z',
        };
        return {
          'has_active_referral': true,
          'active_referral': referral,
          'all_referrals': <Map<String, dynamic>>[referral],
        };

      case '/student/seminars':
        return {
          'required': <Map<String, dynamic>>[
            {
              'id': 1,
              'title': 'Attendance Intervention Program',
              'description':
                  'A guided intervention program designed to help students improve their attendance habits. Topics include time management, morning routines, and understanding the impact of absences on academic performance.',
              'date': '2026-07-04',
              'time': '08:00:00',
              'venue': 'AVR Room 1',
              'speaker': 'Ma\'am Edago',
              'is_required': true,
              'assigned_by': 'ml_system',
              'status': 'enrolled',
            },
            {
              'id': 2,
              'title': 'Study Habits and Academic Performance Workshop',
              'description':
                  'An interactive workshop covering effective study techniques, note-taking strategies, and exam preparation to boost academic performance.',
              'date': '2026-07-10',
              'time': '13:00:00',
              'venue': 'Library Hall',
              'speaker': 'Dr. Santos',
              'is_required': true,
              'assigned_by': 'manual',
              'status': 'enrolled',
            },
          ],
          'completed': <Map<String, dynamic>>[
            {
              'id': 3,
              'title': 'Student Wellness and Mental Health Seminar',
              'description':
                  'A seminar promoting mental health awareness, stress management, and available guidance services for students.',
              'date': '2026-06-20',
              'time': '09:00:00',
              'venue': 'Auditorium',
              'speaker': 'Ma\'am Edago',
              'is_required': true,
              'assigned_by': 'ml_system',
              'status': 'attended',
              'attended_at': '2026-06-20T09:00:00',
            },
          ],
        };

      case '/student/notifications':
        return {
          'unread_count': 2,
          'notifications': <Map<String, dynamic>>[
            {
              'id': 1,
              'title': 'Referral Filed',
              'message':
                  'A referral has been filed for you due to excessive absences. Please visit the Guidance Office.',
              'type': 'referral_alert',
              'is_read': false,
              'created_at': '2026-06-28T10:00:00.000000Z',
            },
            {
              'id': 2,
              'title': 'Seminar Assignment',
              'message':
                  'You have been automatically assigned to the Attendance Intervention Program on July 4, 2026 at 8:00 AM.',
              'type': 'seminar_ai',
              'is_read': false,
              'created_at': '2026-06-26T09:30:00.000000Z',
            },
            {
              'id': 3,
              'title': 'SMS Sent to Parent',
              'message':
                  'An SMS notification has been sent to your parent/guardian regarding your attendance record.',
              'type': 'sms_sent',
              'is_read': true,
              'created_at': '2026-06-25T14:00:00.000000Z',
            },
          ],
        };

      default:
        return {'message': 'OK'};
    }
  }
}
