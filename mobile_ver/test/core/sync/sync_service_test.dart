import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:mobile_ver/core/sync/sync_models.dart';
import 'package:mobile_ver/core/sync/sync_service.dart';
import 'package:mobile_ver/features/todo/repositories/offline_first_todo_repository.dart';
import 'package:sqflite/sqflite.dart';

import '../../support/fake_http_api_client.dart';

const _epoch = '1970-01-01T00:00:00Z';
const _cursor = '2026-10-13T02:00:00Z';

void main() {
  test(
    'pull cursors are isolated per account and ignore the legacy cursor',
    () async {
      final db = _MemoryDatabase();
      db.table('sync_metadata').add({'key': 'sync_cursor', 'value': _cursor});
      final client = _SyncClient();
      final service = SyncService(client: client, database: db);

      await service.pullChanges(1);
      await service.pullChanges(2);
      await service.pullChanges(1);

      expect(client.cursors, [_epoch, _epoch, _cursor]);
      expect(
        db
            .table('sync_metadata')
            .where((row) => row['key'] == syncCursorKey(1))
            .single['value'],
        _cursor,
      );
      expect(
        db
            .table('sync_metadata')
            .where((row) => row['key'] == syncCursorKey(2))
            .single['value'],
        _cursor,
      );
    },
  );

  test('one sync sends the entire queue in bounded batches', () async {
    final db = _MemoryDatabase();
    for (var index = 0; index < 51; index++) {
      db.table('sync_outbox').add(_operation(index));
    }
    final client = _SyncClient();
    await SyncService(client: client, database: db).syncNow(1);

    expect(client.batchSizes, [25, 25, 1]);
    expect(db.table('sync_outbox'), isEmpty);
    expect(db.table('todo_local'), hasLength(51));
  });

  test('rejected operations do not starve later batches', () async {
    final db = _MemoryDatabase();
    for (var index = 0; index < 26; index++) {
      db.table('sync_outbox').add(_operation(index));
    }
    final client = _SyncClient()..rejectOperations = true;
    await SyncService(client: client, database: db).pushPending(1);

    expect(client.batchSizes, [25, 1]);
    expect(db.table('sync_outbox'), hasLength(26));
    expect(
      db.table('sync_outbox').every((row) => row['status'] == 'failed'),
      isTrue,
    );
  });

  test(
    'network failure retains pending writes and does not advance the cursor',
    () async {
      final db = _MemoryDatabase();
      db.table('sync_outbox').add(_operation(1));
      final client = _SyncClient()..offline = true;
      await SyncService(client: client, database: db).syncNow(1);

      expect(db.table('sync_outbox').single['status'], 'failed');
      expect(db.table('sync_outbox').single['attempt_count'], 1);
      expect(db.table('sync_metadata'), isEmpty);
    },
  );

  test('pull preserves unsent local changes and updates synced rows', () async {
    final db = _MemoryDatabase();
    db.table('todo_local').addAll([
      _localTodo(1, title: 'Edit offline', status: 'pending_update'),
      _localTodo(2, title: 'Versi lama'),
    ]);
    final client = _SyncClient()
      ..records = [_serverTodo(1, version: 2), _serverTodo(2, version: 2)];
    await SyncService(client: client, database: db).pullChanges(1);

    expect(
      db
          .table('todo_local')
          .where((row) => row['local_uuid'] == 'todo-1')
          .single['title'],
      'Edit offline',
    );
    expect(
      db
          .table('todo_local')
          .where((row) => row['local_uuid'] == 'todo-2')
          .single['title'],
      'Web 2',
    );
  });

  test(
    'boundary replays refresh data without regressing newer versions',
    () async {
      final db = _MemoryDatabase();
      db.table('todo_local').addAll([
        _localTodo(1, title: 'Detail lokal'),
        {..._localTodo(2, title: 'Versi terbaru'), 'server_version': 3},
      ]);
      final client = _SyncClient()
        ..records = [_serverTodo(1), _serverTodo(2, version: 2)];
      await SyncService(client: client, database: db).pullChanges(1);

      expect(
        {
          for (final row in db.table('todo_local'))
            row['local_uuid']: row['title'],
        },
        {'todo-1': 'Web 1', 'todo-2': 'Versi terbaru'},
      );
    },
  );

  test('web tombstones hide records locally', () async {
    final db = _MemoryDatabase();
    db.table('todo_local').add(_localTodo(1));
    final client = _SyncClient()
      ..records = [
        {..._serverTodo(1, version: 2), 'deleted_at': _cursor},
      ];
    await SyncService(client: client, database: db).pullChanges(1);

    expect(db.table('todo_local').single['deleted_locally'], 1);
  });

  test(
    'sync calls across service instances run in order for the same account',
    () async {
      final db = _MemoryDatabase();
      final entered = Completer<void>();
      final release = Completer<void>();
      final client = _SyncClient()
        ..beforePull = () async {
          if (!entered.isCompleted) {
            entered.complete();
            await release.future;
          }
        };
      final first = SyncService(client: client, database: db).pullChanges(1);
      await entered.future;
      final second = SyncService(client: client, database: db).pullChanges(1);
      await Future<void>.delayed(Duration.zero);
      expect(client.cursors, [_epoch]);
      release.complete();
      await Future.wait([first, second]);

      expect(client.cursors, [_epoch, _cursor]);
    },
  );

  test('first repository refresh returns the newly pulled web data', () async {
    final db = _MemoryDatabase();
    final client = _SyncClient()..records = [_serverTodo(1)];
    final repository = OfflineFirstTodoRepository(
      userId: 1,
      syncService: SyncService(client: client, database: db),
      database: db,
    );

    final todos = await repository.fetchAll();

    expect(todos.single.title, 'Web 1');
    expect(todos.single.syncStatus, 'synced');
  });

  test('offline repository refresh still returns cached data', () async {
    final db = _MemoryDatabase();
    db.table('todo_local').add(_localTodo(1, title: 'Cache offline'));
    final repository = OfflineFirstTodoRepository(
      userId: 1,
      syncService: SyncService(
        client: _SyncClient()..offline = true,
        database: db,
      ),
      database: db,
    );

    expect((await repository.fetchAll()).single.title, 'Cache offline');
  });

  test('invalid account ids never request or persist data', () async {
    final db = _MemoryDatabase();
    final client = _SyncClient();
    await SyncService(client: client, database: db).syncNow(0);
    expect(client.cursors, isEmpty);
    expect(client.batchSizes, isEmpty);
  });

  test(
    'concurrent screen refreshes share a sync without delaying offline cache reads',
    () async {
      final db = _MemoryDatabase();
      final entered = Completer<void>();
      final release = Completer<void>();
      final client = _SyncClient()
        ..beforePull = () async {
          entered.complete();
          await release.future;
        };
      final first = SyncService(client: client, database: db).syncNow(1);
      await entered.future;
      final second = SyncService(client: client, database: db).syncNow(1);
      expect(identical(first, second), isTrue);
      release.complete();
      await Future.wait([first, second]);
      expect(client.cursors, [_epoch]);
    },
  );
}

Map<String, Object?> _operation(int index) => {
  'operation_id': 'operation-$index',
  'user_id': 1,
  'entity_type': 'todolist',
  'entity_local_uuid': 'todo-$index',
  'action': 'create',
  'payload_json': jsonEncode({'nama_item': 'Mobile $index'}),
  'status': 'pending',
  'attempt_count': 0,
  'created_at': _cursor,
};

Map<String, Object?> _localTodo(
  int id, {
  String title = 'Local',
  String status = 'synced',
}) => {
  'local_uuid': 'todo-$id',
  'local_int_id': id,
  'user_id': 1,
  'server_id': id,
  'title': title,
  'server_version': 1,
  'sync_status': status,
  'deleted_locally': 0,
};

Map<String, dynamic> _serverTodo(int id, {int version = 1}) => {
  'id': id,
  'sync_uuid': 'todo-$id',
  'server_version': version,
  'nama_item': 'Web $id',
  'status': false,
  'created_at': _cursor,
  'updated_at': _cursor,
};

class _SyncClient extends FakeHttpApiClient {
  final cursors = <String>[];
  final batchSizes = <int>[];
  List<Map<String, dynamic>> records = [];
  bool offline = false;
  bool rejectOperations = false;
  Future<void> Function()? beforePull;

  @override
  Future<http.Response> get(String endpoint) async {
    cursors.add(Uri.parse(endpoint).queryParameters['cursor']!);
    await beforePull?.call();
    if (offline) throw StateError('Offline test fixture');
    return http.Response(
      jsonEncode({
        'data': {
          'cursor': _cursor,
          'entities': {'todolist': records},
        },
      }),
      200,
    );
  }

  @override
  Future<http.Response> post(String endpoint, Map<String, dynamic> body) async {
    final operations = (body['operations'] as List)
        .cast<Map<String, dynamic>>();
    batchSizes.add(operations.length);
    if (offline) throw StateError('Offline test fixture');
    return http.Response(
      jsonEncode({
        'data': {
          'operations': operations.map((operation) {
            final index = int.parse(
              operation['operation_id'].toString().split('-').last,
            );
            return {
              'operation_id': operation['operation_id'],
              'entity_type': 'todolist',
              'status': rejectOperations ? 'failed' : 'synced',
              if (!rejectOperations) 'record': _serverTodo(index),
            };
          }).toList(),
        },
      }),
      200,
    );
  }
}

// Database boundary double: production sync and repository logic run unchanged.
class _MemoryDatabase implements Database, Transaction {
  final _tables = <String, List<Map<String, Object?>>>{};
  List<Map<String, Object?>> table(String name) =>
      _tables.putIfAbsent(name, () => []);

  bool _matches(Map<String, Object?> row, String? where, List<Object?>? args) {
    if (where == null) return true;
    final values = args ?? [];
    if (where == 'key = ?') return row['key'] == values[0];
    if (where == 'local_uuid = ?') return row['local_uuid'] == values[0];
    if (where == 'operation_id = ?') return row['operation_id'] == values[0];
    if (where.startsWith('operation_id IN')) {
      return values.contains(row['operation_id']);
    }
    if (where == 'user_id = ? AND deleted_locally = 0') {
      return row['user_id'] == values[0] && row['deleted_locally'] == 0;
    }
    if (where == 'user_id = ? AND status IN (?, ?)') {
      return row['user_id'] == values[0] &&
          values.skip(1).contains(row['status']);
    }
    throw UnsupportedError('Unsupported fixture predicate: $where');
  }

  @override
  Future<List<Map<String, Object?>>> query(
    String name, {
    bool? distinct,
    List<String>? columns,
    String? where,
    List<Object?>? whereArgs,
    String? groupBy,
    String? having,
    String? orderBy,
    int? limit,
    int? offset,
  }) async {
    final rows = table(name).where((row) => _matches(row, where, whereArgs));
    return rows
        .skip(offset ?? 0)
        .take(limit ?? rows.length)
        .map((row) => Map<String, Object?>.from(row))
        .toList();
  }

  @override
  Future<int> insert(
    String name,
    Map<String, Object?> values, {
    String? nullColumnHack,
    ConflictAlgorithm? conflictAlgorithm,
  }) async {
    final key = name == 'sync_metadata' ? 'key' : 'local_uuid';
    if (conflictAlgorithm == ConflictAlgorithm.replace) {
      table(name).removeWhere((row) => row[key] == values[key]);
    }
    table(name).add(Map<String, Object?>.from(values));
    return 1;
  }

  @override
  Future<int> delete(
    String name, {
    String? where,
    List<Object?>? whereArgs,
  }) async {
    final previous = table(name).length;
    table(name).removeWhere((row) => _matches(row, where, whereArgs));
    return previous - table(name).length;
  }

  @override
  Future<int> update(
    String name,
    Map<String, Object?> values, {
    String? where,
    List<Object?>? whereArgs,
    ConflictAlgorithm? conflictAlgorithm,
  }) async {
    final rows = table(
      name,
    ).where((row) => _matches(row, where, whereArgs)).toList();
    for (final row in rows) {
      row.addAll(values);
    }
    return rows.length;
  }

  @override
  Future<int> rawUpdate(String sql, [List<Object?>? arguments]) async {
    final args = arguments!;
    final row = table(
      'sync_outbox',
    ).where((row) => row['operation_id'] == args.last).single;
    row['attempt_count'] = (row['attempt_count'] as int) + 1;
    if (args.length == 4) {
      row.addAll({
        'status': args[0],
        'updated_at': args[1],
        'error_message': args[2],
      });
    }
    return 1;
  }

  @override
  Future<T> transaction<T>(
    Future<T> Function(Transaction txn) action, {
    bool? exclusive,
  }) => action(this);

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
