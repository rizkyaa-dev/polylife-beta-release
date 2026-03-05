class User {
  final int id;
  final String name;
  final String email;
  final String role;
  final String roleLabel;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.roleLabel,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      name: json['name'],
      email: json['email'],
      role: json['role'] ?? 'user',
      roleLabel: json['role_label'] ?? 'Pengguna',
    );
  }
}
