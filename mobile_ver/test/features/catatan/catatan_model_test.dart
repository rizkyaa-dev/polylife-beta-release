import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_ver/features/catatan/models/catatan_model.dart';

void main() {
  group('Catatan model', () {
    test('uses preview as isi when full content is not included', () {
      final catatan = Catatan.fromJson({
        'id': '12',
        'sync_uuid': 'note-uuid',
        'server_version': '3',
        'judul': 'Judul',
        'preview_isi': 'Preview aman',
        'tanggal': '2026-05-13',
        'status_sampah': '0',
      });

      expect(catatan.id, 12);
      expect(catatan.serverId, 12);
      expect(catatan.serverVersion, 3);
      expect(catatan.isi, 'Preview aman');
      expect(catatan.hasFullIsi, isFalse);
      expect(catatan.listPreview, 'Preview aman');
      expect(catatan.statusSampah, isFalse);
    });

    test('detects trash status and keeps full content when present', () {
      final catatan = Catatan.fromJson({
        'id': 15,
        'judul': 'Rahasia',
        'isi': 'Isi lengkap',
        'preview_isi': 'Preview pendek',
        'tanggal': '2026-05-10',
        'status_sampah': 1,
        'created_at': '2026-05-10T10:00:00Z',
      });

      expect(catatan.isi, 'Isi lengkap');
      expect(catatan.previewIsi, 'Preview pendek');
      expect(catatan.hasFullIsi, isTrue);
      expect(catatan.statusSampah, isTrue);
      expect(catatan.tanggalAsDate, DateTime(2026, 5, 10));
      expect(catatan.createdAt, isNotNull);
    });
  });
}
