import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

class AppDatabase {
  AppDatabase._();

  static final AppDatabase instance = AppDatabase._();

  static const int _version = 1;
  Database? _database;

  Future<Database> get database async {
    final existing = _database;
    if (existing != null) {
      return existing;
    }

    final root = await getDatabasesPath();
    final db = await openDatabase(
      p.join(root, 'polylife_mobile.db'),
      version: _version,
      onCreate: _create,
    );
    _database = db;
    return db;
  }

  Future<T> transaction<T>(Future<T> Function(Transaction txn) action) async {
    final db = await database;
    return db.transaction(action);
  }

  Future<void> _create(Database db, int version) async {
    await db.execute('''
      CREATE TABLE sync_metadata (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
      )
    ''');

    await db.execute('''
      CREATE TABLE catatan_local (
        local_uuid TEXT PRIMARY KEY,
        local_int_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        server_id INTEGER,
        judul TEXT NOT NULL,
        isi TEXT NOT NULL,
        preview_isi TEXT NOT NULL,
        has_full_isi INTEGER NOT NULL DEFAULT 1,
        tanggal TEXT NOT NULL,
        status_sampah INTEGER NOT NULL DEFAULT 0,
        server_version INTEGER NOT NULL DEFAULT 0,
        sync_status TEXT NOT NULL,
        deleted_locally INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT,
        dirty_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE keuangan_local (
        local_uuid TEXT PRIMARY KEY,
        local_int_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        server_id INTEGER,
        jenis TEXT NOT NULL,
        kategori TEXT NOT NULL,
        deskripsi TEXT,
        nominal REAL NOT NULL,
        tanggal TEXT NOT NULL,
        server_version INTEGER NOT NULL DEFAULT 0,
        sync_status TEXT NOT NULL,
        deleted_locally INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT,
        dirty_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE todo_local (
        local_uuid TEXT PRIMARY KEY,
        local_int_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        server_id INTEGER,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        completed INTEGER NOT NULL DEFAULT 0,
        due_at TEXT,
        priority TEXT NOT NULL DEFAULT 'normal',
        server_version INTEGER NOT NULL DEFAULT 0,
        sync_status TEXT NOT NULL,
        deleted_locally INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT,
        dirty_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE jadwal_local (
        local_uuid TEXT PRIMARY KEY,
        local_int_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        server_id INTEGER,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        start_at TEXT NOT NULL,
        end_at TEXT NOT NULL,
        location TEXT NOT NULL DEFAULT '',
        notes TEXT NOT NULL DEFAULT '',
        completed INTEGER NOT NULL DEFAULT 0,
        server_version INTEGER NOT NULL DEFAULT 0,
        sync_status TEXT NOT NULL,
        deleted_locally INTEGER NOT NULL DEFAULT 0,
        created_at TEXT,
        updated_at TEXT,
        dirty_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE pengumuman_local (
        server_id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT,
        excerpt TEXT NOT NULL,
        image_url TEXT,
        target_mode TEXT NOT NULL,
        published_at TEXT NOT NULL,
        creator_json TEXT,
        updated_at TEXT
      )
    ''');

    await db.execute('''
      CREATE TABLE sync_outbox (
        operation_id TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        entity_type TEXT NOT NULL,
        entity_local_uuid TEXT NOT NULL,
        entity_server_id INTEGER,
        action TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        base_server_version INTEGER,
        depends_on_operation_id TEXT,
        status TEXT NOT NULL,
        attempt_count INTEGER NOT NULL DEFAULT 0,
        schema_version INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        locked_until TEXT,
        error_message TEXT
      )
    ''');

    await db.execute(
      'CREATE INDEX idx_catatan_user ON catatan_local(user_id, deleted_locally, tanggal, server_id)',
    );
    await db.execute(
      'CREATE INDEX idx_keuangan_user ON keuangan_local(user_id, deleted_locally, tanggal, server_id)',
    );
    await db.execute(
      'CREATE INDEX idx_todo_user ON todo_local(user_id, deleted_locally, completed, server_id)',
    );
    await db.execute(
      'CREATE INDEX idx_jadwal_user ON jadwal_local(user_id, deleted_locally, start_at, server_id)',
    );
    await db.execute(
      'CREATE INDEX idx_outbox_pending ON sync_outbox(user_id, status, created_at)',
    );
  }
}
