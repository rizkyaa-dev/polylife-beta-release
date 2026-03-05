class ApiConfig {
  // Override with:
  // flutter run --dart-define=POLYLIFE_API_BASE_URL=https://domainmu.com/api/v1
  static const String baseUrl = String.fromEnvironment(
    'POLYLIFE_API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
