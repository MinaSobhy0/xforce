import 'package:flutter/material.dart';
import 'core/api/api_client.dart';
import 'core/sdui/sdui_screen.dart';

void main() {
  runApp(const XLinicStaffApp());
}

/// XLinic Staff Mobile App
///
/// Entry point with SDUI-driven UI.
class XLinicStaffApp extends StatelessWidget {
  const XLinicStaffApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'XLinic Staff',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
      ),
      home: const SplashScreen(),
    );
  }
}

// ============================================================
// SPLASH SCREEN
// ============================================================

/// Initial screen - checks for existing session
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  final ApiClient _api = ApiClient();

  @override
  void initState() {
    super.initState();
    _checkSession();
  }

  Future<void> _checkSession() async {
    await Future.delayed(const Duration(milliseconds: 500)); // Brief splash

    final hasSession = await _api.restoreSession();

    if (!mounted) return;

    if (hasSession) {
      // Validate session is still valid
      try {
        await _api.getCurrentUser();
        _navigateTo(MainScreen(api: _api));
      } catch (e) {
        // Session expired
        _navigateTo(TenantCodeScreen(api: _api));
      }
    } else {
      _navigateTo(TenantCodeScreen(api: _api));
    }
  }

  void _navigateTo(Widget screen) {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => screen),
    );
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            FlutterLogo(size: 80),
            SizedBox(height: 24),
            CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// TENANT CODE SCREEN
// ============================================================

/// Enter clinic app code to discover tenant
class TenantCodeScreen extends StatefulWidget {
  final ApiClient api;

  const TenantCodeScreen({super.key, required this.api});

  @override
  State<TenantCodeScreen> createState() => _TenantCodeScreenState();
}

class _TenantCodeScreenState extends State<TenantCodeScreen> {
  final _codeController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final code = _codeController.text.trim().toUpperCase();
      final tenant = await widget.api.resolveTenant(code);

      await widget.api.setTenant(tenant['slug']);

      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            api: widget.api,
            tenantName: tenant['name'] ?? 'Clinic',
            logoUrl: tenant['logo_url'],
          ),
        ),
      );
    } catch (e) {
      setState(() {
        _error = 'Invalid clinic code. Please try again.';
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.business,
                  size: 64,
                  color: Colors.blue,
                ),
                const SizedBox(height: 24),
                Text(
                  'Enter Clinic Code',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: 8),
                Text(
                  'Enter the code provided by your clinic',
                  style: TextStyle(color: Colors.grey[600]),
                ),
                const SizedBox(height: 32),
                TextFormField(
                  controller: _codeController,
                  textAlign: TextAlign.center,
                  textCapitalization: TextCapitalization.characters,
                  style: const TextStyle(
                    fontSize: 24,
                    letterSpacing: 8,
                    fontWeight: FontWeight.bold,
                  ),
                  decoration: InputDecoration(
                    hintText: 'ABC123',
                    hintStyle: TextStyle(
                      color: Colors.grey[400],
                      letterSpacing: 8,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                      vertical: 20,
                      horizontal: 24,
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Please enter a clinic code';
                    }
                    return null;
                  },
                ),
                if (_error != null) ...[
                  const SizedBox(height: 16),
                  Text(
                    _error!,
                    style: const TextStyle(color: Colors.red),
                  ),
                ],
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _submit,
                    child: _loading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Continue'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ============================================================
// LOGIN SCREEN
// ============================================================

/// Staff login screen
class LoginScreen extends StatefulWidget {
  final ApiClient api;
  final String tenantName;
  final String? logoUrl;

  const LoginScreen({
    super.key,
    required this.api,
    required this.tenantName,
    this.logoUrl,
  });

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  bool _obscurePassword = true;
  String? _error;

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final result = await widget.api.login(
        _emailController.text.trim(),
        _passwordController.text,
      );

      if (!mounted) return;

      if (result['success'] == true) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => MainScreen(api: widget.api)),
        );
      } else {
        setState(() {
          _error = result['message'] ?? 'Login failed';
          _loading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Invalid credentials. Please try again.';
        _loading = false;
      });
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.tenantName),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 32),
                // Logo or placeholder
                Center(
                  child: widget.logoUrl != null
                      ? Image.network(
                          widget.logoUrl!,
                          height: 80,
                          errorBuilder: (_, __, ___) => const Icon(
                            Icons.business,
                            size: 64,
                          ),
                        )
                      : const Icon(Icons.business, size: 64),
                ),
                const SizedBox(height: 32),
                Text(
                  'Staff Login',
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    prefixIcon: Icon(Icons.email_outlined),
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Please enter your email';
                    }
                    if (!value.contains('@')) {
                      return 'Please enter a valid email';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: 'Password',
                    prefixIcon: const Icon(Icons.lock_outlined),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter your password';
                    }
                    return null;
                  },
                ),
                if (_error != null) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.red[50],
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.error_outline, color: Colors.red[700]),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _error!,
                            style: TextStyle(color: Colors.red[700]),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                SizedBox(
                  height: 50,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _login,
                    child: _loading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Login'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ============================================================
// MAIN SCREEN
// ============================================================

/// Main app screen with SDUI-driven navigation
class MainScreen extends StatefulWidget {
  final ApiClient api;

  const MainScreen({super.key, required this.api});

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _currentIndex = 0;
  Map<String, dynamic>? _config;
  List<Map<String, dynamic>> _tabs = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadConfig();
  }

  Future<void> _loadConfig() async {
    try {
      final config = await widget.api.getConfig();
      if (mounted) {
        setState(() {
          _config = config;
          _tabs = List<Map<String, dynamic>>.from(
            config['navigation']?['tabs'] ?? [],
          );
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load config: $e')),
        );
      }
    }
  }

  void _handleAction(String action, Map<String, dynamic>? params) {
    switch (action) {
      case 'check_in':
        _showCheckInDialog();
        break;
      case 'check_out':
        _confirmCheckOut();
        break;
      case 'start_break':
        _startBreak();
        break;
      case 'end_break':
        _endBreak();
        break;
      case 'navigate':
        final screen = params?['screen'] as String?;
        if (screen != null) {
          _navigateToScreen(screen);
        }
        break;
      case 'view_appointment':
        final id = params?['id'];
        if (id != null) {
          _showAppointmentDetails(id);
        }
        break;
      case 'logout':
        _logout();
        break;
      default:
        debugPrint('Unhandled action: $action');
    }
  }

  void _showCheckInDialog() {
    // TODO: Implement check-in flow (QR scan, GPS, manual)
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Check In'),
        content: const Text('Check-in dialog - implement as needed'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              try {
                await widget.api.checkIn(method: 'manual');
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Checked in successfully')),
                  );
                  setState(() {}); // Refresh
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Check-in failed: $e')),
                  );
                }
              }
            },
            child: const Text('Check In'),
          ),
        ],
      ),
    );
  }

  void _confirmCheckOut() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Check Out'),
        content: const Text('Are you sure you want to check out?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              try {
                await widget.api.checkOut();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Checked out successfully')),
                  );
                  setState(() {}); // Refresh
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Check-out failed: $e')),
                  );
                }
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Check Out'),
          ),
        ],
      ),
    );
  }

  Future<void> _startBreak() async {
    try {
      await widget.api.startBreak();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Break started')),
        );
        setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to start break: $e')),
        );
      }
    }
  }

  Future<void> _endBreak() async {
    try {
      await widget.api.endBreak();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Break ended')),
        );
        setState(() {});
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to end break: $e')),
        );
      }
    }
  }

  void _navigateToScreen(String screenId) {
    // Find tab index or show as modal
    final tabIndex = _tabs.indexWhere((t) => t['id'] == screenId);
    if (tabIndex >= 0) {
      setState(() {
        _currentIndex = tabIndex;
      });
    } else {
      // Show as separate screen
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => SDUIScaffold(
            screenId: screenId,
            fetchScreen: widget.api.getScreen,
            fetchData: widget.api.getScreenData,
            config: _config!,
            onAction: _handleAction,
          ),
        ),
      );
    }
  }

  void _showAppointmentDetails(dynamic id) {
    // TODO: Navigate to appointment details
    debugPrint('Show appointment: $id');
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Logout'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await widget.api.logout();
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => TenantCodeScreen(api: widget.api)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_config == null) {
      return Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('Failed to load configuration'),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _loadConfig,
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final currentTab = _tabs.isNotEmpty ? _tabs[_currentIndex] : null;
    final screenId = currentTab?['id'] ?? 'dashboard';
    final branding = _config?['branding'] as Map<String, dynamic>? ?? {};

    return Scaffold(
      appBar: AppBar(
        title: Text(branding['app_name'] ?? 'Staff App'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
            tooltip: 'Logout',
          ),
        ],
      ),
      body: SDUIScreen(
        key: ValueKey(screenId),
        screenId: screenId,
        config: _config!,
        fetchScreen: widget.api.getScreen,
        fetchData: widget.api.getScreenData,
        onAction: _handleAction,
      ),
      bottomNavigationBar: _tabs.length > 1
          ? BottomNavigationBar(
              currentIndex: _currentIndex,
              onTap: (index) => setState(() => _currentIndex = index),
              type: BottomNavigationBarType.fixed,
              items: _tabs.map((tab) {
                return BottomNavigationBarItem(
                  icon: Icon(_getIcon(tab['icon'] ?? '')),
                  label: tab['label'] ?? tab['id'],
                );
              }).toList(),
            )
          : null,
    );
  }

  IconData _getIcon(String name) {
    return switch (name) {
      'home' => Icons.home,
      'calendar' => Icons.calendar_today,
      'clock' => Icons.access_time,
      'calendar-days' => Icons.calendar_month,
      'ellipsis-horizontal' => Icons.more_horiz,
      'document-text' => Icons.description,
      'sun' => Icons.wb_sunny,
      'user-circle' => Icons.account_circle,
      'currency-dollar' => Icons.attach_money,
      'users' => Icons.people,
      _ => Icons.square,
    };
  }
}
