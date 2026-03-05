class Pengumuman {
  final int id;
  final String title;
  final String? body;
  final String excerpt;
  final String? imageUrl;
  final String targetMode;
  final String publishedAt;
  final Map<String, dynamic>? creator;

  Pengumuman({
    required this.id,
    required this.title,
    this.body,
    required this.excerpt,
    this.imageUrl,
    required this.targetMode,
    required this.publishedAt,
    this.creator,
  });

  factory Pengumuman.fromJson(Map<String, dynamic> json) {
    return Pengumuman(
      id: json['id'],
      title: json['title'],
      body: json['body'],
      excerpt: json['excerpt'] ?? '',
      imageUrl: json['image_url'],
      targetMode: json['target_mode'] ?? 'global',
      publishedAt: json['published_at'],
      creator: json['creator'],
    );
  }
}
