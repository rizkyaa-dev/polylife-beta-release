import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String? _errorText;

  Future<void> _handleLogin() async {
    FocusScope.of(context).unfocus();
    setState(() => _isLoading = true);

    final error = await ref.read(authProvider.notifier).login(
      _emailController.text.trim(),
      _passwordController.text,
    );

    if (!mounted) return;

    setState(() {
      _isLoading = false;
      _errorText = error;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFFFFF7FB),
                  Color(0xFFF7F4FF),
                  Color(0xFFFDF8FF),
                ],
              ),
            ),
          ),
          Positioned.fill(
            child: IgnorePointer(
              child: Opacity(
                opacity: 0.3,
                child: CustomPaint(
                  painter: _GridPainter(
                    lineColor: const Color(0xFF8181FF).withValues(alpha: 0.2),
                    step: 32,
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            top: -72,
            right: -44,
            child: Container(
              height: 180,
              width: 180,
              decoration: BoxDecoration(
                color: const Color(0xFFFFCCE1).withValues(alpha: 0.55),
                boxShadow: const [
                  BoxShadow(
                    color: Color.fromRGBO(255, 255, 255, 0.7),
                    offset: Offset(12, 12),
                    blurRadius: 0,
                  ),
                ],
              ),
            ),
          ),
          Positioned(
            bottom: -88,
            left: -46,
            child: Container(
              height: 220,
              width: 220,
              decoration: BoxDecoration(
                color: const Color(0xFFC9E5FF).withValues(alpha: 0.55),
                boxShadow: const [
                  BoxShadow(
                    color: Color.fromRGBO(255, 255, 255, 0.45),
                    offset: Offset(-12, -12),
                    blurRadius: 0,
                  ),
                ],
              ),
            ),
          ),
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 560),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(28),
                      border: Border.all(color: const Color(0xFF8181FF), width: 4),
                      color: const Color(0xFFFDFBFF).withValues(alpha: 0.96),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0xFFC5D4FF),
                          offset: Offset(12, 12),
                          blurRadius: 0,
                        ),
                      ],
                    ),
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        Positioned(
                          top: -22,
                          right: 42,
                          child: Container(
                            height: 44,
                            width: 44,
                            decoration: BoxDecoration(
                              border: Border.all(color: const Color(0xFF2B2250), width: 4),
                              color: const Color(0xFFF49CC8),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0xFF2B2250),
                                  offset: Offset(6, 6),
                                  blurRadius: 0,
                                ),
                              ],
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(22, 24, 22, 24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    height: 46,
                                    width: 46,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: const Color(0xFF2B2250), width: 4),
                                      color: const Color(0xFF8181FF),
                                      boxShadow: const [
                                        BoxShadow(
                                          color: Color(0xFF2B2250),
                                          offset: Offset(6, 6),
                                          blurRadius: 0,
                                        ),
                                      ],
                                    ),
                                    alignment: Alignment.center,
                                    child: const Text(
                                      'PL',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 22,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: const [
                                      Text(
                                        'PolyLife',
                                        style: TextStyle(
                                          color: Color(0xFF2D2D3C),
                                          fontSize: 24,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                      SizedBox(height: 2),
                                      Text(
                                        'WORKSPACE',
                                        style: TextStyle(
                                          color: Color(0xFF6D6797),
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          letterSpacing: 3.4,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const Spacer(),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(
                                        color: const Color(0xFF2B2250).withValues(alpha: 0.2),
                                        width: 2,
                                      ),
                                      color: Colors.white,
                                    ),
                                    child: const Text(
                                      'MASUK',
                                      style: TextStyle(
                                        color: Color(0xFF6D6797),
                                        fontSize: 10,
                                        fontWeight: FontWeight.w700,
                                        letterSpacing: 3.2,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 26),
                              const Text(
                                'Selamat datang kembali di PolyLife',
                                style: TextStyle(
                                  color: Color(0xFF2D2D3C),
                                  fontSize: 30,
                                  height: 1.15,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 10),
                              const Text(
                                'Pantau jadwal kuliah, IPK, dan pengingat penting dari satu tempat.',
                                style: TextStyle(
                                  color: Color(0xFF6D6797),
                                  fontSize: 15,
                                  height: 1.4,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              if (_errorText != null) ...[
                                const SizedBox(height: 18),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: const Color(0xFFFDA4AF), width: 2),
                                    color: const Color(0xFFFFF1F2),
                                  ),
                                  child: Text(
                                    _errorText!,
                                    style: const TextStyle(
                                      color: Color(0xFFBE123C),
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 22),
                              _buildInputLabel('Email'),
                              const SizedBox(height: 8),
                              TextFormField(
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                decoration: _brutalInputDecoration(
                                  hintText: 'nama@kampus.ac.id',
                                  icon: Icons.email_outlined,
                                ),
                              ),
                              const SizedBox(height: 16),
                              _buildInputLabel('Password'),
                              const SizedBox(height: 8),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: true,
                                decoration: _brutalInputDecoration(
                                  hintText: '••••••••',
                                  icon: Icons.lock_outline,
                                ),
                                onFieldSubmitted: (_) {
                                  if (!_isLoading) {
                                    _handleLogin();
                                  }
                                },
                              ),
                              const SizedBox(height: 24),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: _isLoading ? null : _handleLogin,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF8181FF),
                                    foregroundColor: Colors.white,
                                    disabledBackgroundColor: const Color(0xFFB8B8FF),
                                    elevation: 0,
                                    padding: const EdgeInsets.symmetric(vertical: 16),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(18),
                                      side: const BorderSide(color: Color(0xFF2B2250), width: 2),
                                    ),
                                  ).copyWith(
                                    shadowColor: const WidgetStatePropertyAll(Color(0xFF2B2250)),
                                  ),
                                  child: _isLoading
                                      ? const SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                          ),
                                        )
                                      : const Text(
                                          'LOGIN',
                                          style: TextStyle(
                                            fontSize: 16,
                                            letterSpacing: 0.8,
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _brutalInputDecoration({
    required String hintText,
    required IconData icon,
  }) {
    return InputDecoration(
      hintText: hintText,
      prefixIcon: Icon(icon, color: const Color(0xFF6D6797)),
      filled: true,
      fillColor: const Color(0xFFF6F4FF),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      hintStyle: const TextStyle(
        color: Color(0xFFA7A6C9),
        fontWeight: FontWeight.w600,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: const Color(0xFF8181FF).withValues(alpha: 0.4), width: 2),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: const Color(0xFF8181FF).withValues(alpha: 0.4), width: 2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: Color(0xFF8181FF), width: 2),
      ),
    );
  }

  Widget _buildInputLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        color: Color(0xFF4C4C63),
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }
}

class _GridPainter extends CustomPainter {
  final Color lineColor;
  final double step;

  _GridPainter({
    required this.lineColor,
    required this.step,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = lineColor
      ..strokeWidth = 1;

    for (double x = 0; x <= size.width; x += step) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }

    for (double y = 0; y <= size.height; y += step) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _GridPainter oldDelegate) {
    return oldDelegate.lineColor != lineColor || oldDelegate.step != step;
  }
}
