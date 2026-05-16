import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:mobile_ver/core/database/app_database.dart';
import 'package:mobile_ver/features/reminder/models/reminder_list_item.dart';
import 'package:timezone/data/latest_all.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

class ReminderNotificationService {
  const ReminderNotificationService._();

  static const ReminderNotificationService instance =
      ReminderNotificationService._();

  static final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();
  static bool _initialized = false;
  static bool _initializing = false;
  static bool _exactAlarmPermissionRequested = false;

  Future<void> initialize() async {
    if (_initialized || _initializing || kIsWeb) {
      return;
    }

    _initializing = true;
    try {
      tz.initializeTimeZones();
      await _configureLocalTimezone();

      const androidSettings = AndroidInitializationSettings(
        'ic_stat_polylife_reminder',
      );
      const darwinSettings = DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      );
      const settings = InitializationSettings(
        android: androidSettings,
        iOS: darwinSettings,
        macOS: darwinSettings,
      );

      await _plugin.initialize(settings: settings);
      _initialized = true;
    } catch (_) {
      _initialized = false;
    } finally {
      _initializing = false;
    }
  }

  Future<void> schedule(ReminderListItem item) async {
    if (!item.active || item.scheduledAt == null) {
      await cancel(item);
      return;
    }

    final scheduledAt = item.scheduledAt!.toLocal();
    if (!scheduledAt.isAfter(DateTime.now())) {
      await cancel(item);
      return;
    }

    await initialize();
    if (!_initialized) {
      return;
    }

    final permitted = await _ensureNotificationPermission();
    if (!permitted) {
      return;
    }

    final notificationId = _notificationId(item);
    final payload = jsonEncode({
      'type': 'reminder',
      'id': item.id,
      'destination': item.destination,
      'target_type': item.targetType,
    });
    final title = item.title.trim().isEmpty ? 'Reminder' : item.title.trim();
    final context = item.targetContext.trim().isNotEmpty
        ? item.targetContext.trim()
        : item.targetLabel.trim();
    final body = context.isEmpty
        ? 'Reminder kamu sudah waktunya.'
        : '$context sudah waktunya.';

    final details = _notificationDetails(body);

    final scheduledDate = tz.TZDateTime.from(scheduledAt, tz.local);
    final scheduleMode = await _preferredScheduleMode();

    try {
      await _plugin.zonedSchedule(
        id: notificationId,
        title: title,
        body: body,
        scheduledDate: scheduledDate,
        notificationDetails: details,
        androidScheduleMode: scheduleMode,
        payload: payload,
      );
    } catch (_) {
      await _plugin.zonedSchedule(
        id: notificationId,
        title: title,
        body: body,
        scheduledDate: scheduledDate,
        notificationDetails: details,
        androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
        payload: payload,
      );
    }
  }

  Future<String> showTestNotification() async {
    await initialize();
    if (!_initialized) {
      return 'Plugin notifikasi belum berhasil diinisialisasi.';
    }

    final permitted = await _ensureNotificationPermission();
    if (!permitted) {
      return 'Izin notifikasi belum aktif. Aktifkan dari App Info > Notifications.';
    }

    try {
      await _plugin.show(
        id: 1,
        title: 'Tes Reminder PolyLife',
        body: 'Kalau pesan ini muncul, notifikasi Android sudah aktif.',
        notificationDetails: _notificationDetails(
          'Kalau pesan ini muncul, notifikasi Android sudah aktif.',
        ),
      );
    } catch (e) {
      return 'Gagal menampilkan notifikasi test: $e';
    }

    final pendingCount = await pendingCountOrZero();
    final exactStatus = await _exactAlarmStatusText();
    return 'Test dikirim. Pending reminder: $pendingCount. $exactStatus';
  }

  Future<void> scheduleAll(Iterable<ReminderListItem> items) async {
    await initialize();
    if (!_initialized) {
      return;
    }

    await _plugin.cancelAll();
    for (final item in items) {
      await schedule(item);
    }
  }

  Future<int> pendingCountOrZero() async {
    await initialize();
    if (!_initialized) {
      return 0;
    }

    try {
      final pending = await _plugin.pendingNotificationRequests();
      return pending.length;
    } catch (_) {
      return 0;
    }
  }

  Future<void> scheduleUserRemindersFromDatabase(int userId) async {
    if (userId <= 0) {
      return;
    }

    final db = await AppDatabase.instance.database;
    final rows = await db.query(
      'reminder_local',
      where: 'user_id = ? AND deleted_locally = 0',
      whereArgs: [userId],
    );

    await scheduleAll(rows.map(_itemFromRow));
  }

  Future<void> cancel(ReminderListItem item) async {
    await initialize();
    if (!_initialized) {
      return;
    }

    await _plugin.cancel(id: _notificationId(item));
  }

  Future<void> cancelByIdentity({
    required int id,
    String localUuid = '',
    int? serverId,
  }) async {
    await initialize();
    if (!_initialized) {
      return;
    }

    await _plugin.cancel(
      id: _stableId(
        localUuid.isNotEmpty ? localUuid : (serverId ?? id).toString(),
      ),
    );
  }

  Future<void> cancelAll() async {
    await initialize();
    if (!_initialized) {
      return;
    }

    await _plugin.cancelAll();
  }

  NotificationDetails _notificationDetails(String body) {
    return NotificationDetails(
      android: AndroidNotificationDetails(
        'polylife_reminders',
        'Reminder PolyLife',
        icon: 'ic_stat_polylife_reminder',
        channelDescription: 'Notifikasi pengingat jadwal, to-do, dan kegiatan.',
        importance: Importance.max,
        priority: Priority.max,
        category: AndroidNotificationCategory.reminder,
        styleInformation: BigTextStyleInformation(body),
      ),
      iOS: const DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      ),
      macOS: const DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      ),
    );
  }

  Future<bool> _ensureNotificationPermission() async {
    final android = _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    if (android != null) {
      return await android.requestNotificationsPermission() ?? true;
    }

    final ios = _plugin
        .resolvePlatformSpecificImplementation<
          IOSFlutterLocalNotificationsPlugin
        >();
    if (ios != null) {
      return await ios.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          ) ??
          false;
    }

    final macos = _plugin
        .resolvePlatformSpecificImplementation<
          MacOSFlutterLocalNotificationsPlugin
        >();
    if (macos != null) {
      return await macos.requestPermissions(
            alert: true,
            badge: true,
            sound: true,
          ) ??
          false;
    }

    return true;
  }

  Future<AndroidScheduleMode> _preferredScheduleMode() async {
    final android = _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    if (android == null) {
      return AndroidScheduleMode.exactAllowWhileIdle;
    }

    if (_exactAlarmPermissionRequested) {
      return AndroidScheduleMode.exactAllowWhileIdle;
    }

    _exactAlarmPermissionRequested = true;
    final granted = await android.requestExactAlarmsPermission();
    return granted == false
        ? AndroidScheduleMode.inexactAllowWhileIdle
        : AndroidScheduleMode.exactAllowWhileIdle;
  }

  Future<String> _exactAlarmStatusText() async {
    final android = _plugin
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >();
    if (android == null) {
      return 'Exact alarm tidak relevan di platform ini.';
    }

    try {
      final canScheduleExact = await android.canScheduleExactNotifications();
      return canScheduleExact == false
          ? 'Exact alarm belum aktif; reminder bisa terlambat.'
          : 'Exact alarm aktif.';
    } catch (_) {
      return 'Status exact alarm tidak terbaca.';
    }
  }

  Future<void> _configureLocalTimezone() async {
    try {
      final timezone = await FlutterTimezone.getLocalTimezone();
      tz.setLocalLocation(tz.getLocation(timezone.identifier));
    } catch (_) {
      tz.setLocalLocation(tz.getLocation('Asia/Jakarta'));
    }
  }

  ReminderListItem _itemFromRow(Map<String, Object?> row) {
    final localUuid = row['local_uuid']?.toString() ?? '';
    final serverId = (row['server_id'] as num?)?.toInt();
    final localIntId = (row['local_int_id'] as num?)?.toInt();
    final scheduledAt = DateTime.tryParse(
      row['scheduled_at']?.toString() ?? '',
    );

    return ReminderListItem(
      id: serverId ?? localIntId ?? _stableId(localUuid),
      localUuid: localUuid,
      serverId: serverId,
      serverVersion: (row['server_version'] as num?)?.toInt() ?? 0,
      syncStatus: row['sync_status']?.toString() ?? 'synced',
      title: row['title']?.toString() ?? 'Reminder',
      targetType: row['target_type']?.toString() ?? 'reminder',
      targetLabel: row['target_label']?.toString() ?? 'Reminder',
      targetContext: row['target_context']?.toString() ?? '',
      destination: row['destination']?.toString() ?? 'todo',
      active: (row['active'] as num?)?.toInt() == 1,
      scheduledAt: scheduledAt,
      scheduledLabel: row['scheduled_label']?.toString() ?? '',
    );
  }

  int _notificationId(ReminderListItem item) {
    final stableKey = item.localUuid.trim().isNotEmpty
        ? item.localUuid.trim()
        : (item.serverId ?? item.id).toString();
    return _stableId(stableKey);
  }

  int _stableId(String value) {
    var hash = 0;
    for (final code in value.codeUnits) {
      hash = 0x1fffffff & (hash + code);
      hash = 0x1fffffff & (hash + ((0x0007ffff & hash) << 10));
      hash ^= hash >> 6;
    }

    return max(1, hash.abs());
  }
}
