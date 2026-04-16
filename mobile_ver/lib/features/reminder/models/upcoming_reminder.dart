class UpcomingReminder {
  final int id;
  final String title;
  final String targetType;
  final DateTime? scheduledAt;
  final String scheduledLabel;
  final String relativeLabel;
  final String timeLeftText;
  final int secondsLeft;

  const UpcomingReminder({
    required this.id,
    required this.title,
    required this.targetType,
    required this.scheduledAt,
    required this.scheduledLabel,
    required this.relativeLabel,
    required this.timeLeftText,
    required this.secondsLeft,
  });

  factory UpcomingReminder.fromJson(Map<String, dynamic> json) {
    return UpcomingReminder(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: json['title']?.toString() ?? 'Reminder',
      targetType: json['target_type']?.toString() ?? 'reminder',
      scheduledAt: DateTime.tryParse(json['scheduled_at']?.toString() ?? ''),
      scheduledLabel: json['scheduled_label']?.toString() ?? '',
      relativeLabel: json['relative_label']?.toString() ?? '',
      timeLeftText: json['time_left_text']?.toString() ?? '',
      secondsLeft: (json['seconds_left'] as num?)?.toInt() ?? 0,
    );
  }
}
