import 'package:flutter/material.dart';
import 'sdui_renderer.dart';

/// SDUI Screen Widget
///
/// Fetches screen definition from API and renders using SDUIRenderer.
/// Handles loading, error states, and pull-to-refresh.
class SDUIScreen extends StatefulWidget {
  /// Screen ID to fetch (e.g., 'dashboard', 'attendance')
  final String screenId;

  /// Function to fetch screen definition
  final Future<Map<String, dynamic>> Function(String) fetchScreen;

  /// Function to fetch screen data (optional, for data refresh)
  final Future<Map<String, dynamic>> Function(String)? fetchData;

  /// App configuration (for branding colors, etc.)
  final Map<String, dynamic> config;

  /// Callback for component actions
  final void Function(String action, Map<String, dynamic>? params)? onAction;

  const SDUIScreen({
    super.key,
    required this.screenId,
    required this.fetchScreen,
    required this.config,
    this.fetchData,
    this.onAction,
  });

  @override
  State<SDUIScreen> createState() => _SDUIScreenState();
}

class _SDUIScreenState extends State<SDUIScreen> {
  Map<String, dynamic>? _screen;
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadScreen();
  }

  @override
  void didUpdateWidget(SDUIScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.screenId != widget.screenId) {
      _loadScreen();
    }
  }

  Future<void> _loadScreen() async {
    try {
      setState(() {
        _loading = true;
        _error = null;
      });

      // Fetch screen definition
      final screen = await widget.fetchScreen(widget.screenId);

      // Fetch screen data if available
      Map<String, dynamic>? data;
      if (widget.fetchData != null) {
        try {
          data = await widget.fetchData!(widget.screenId);
        } catch (e) {
          // Data fetch failed, but we can still render the screen
          debugPrint('Data fetch failed: $e');
        }
      }

      if (mounted) {
        setState(() {
          _screen = screen;
          _data = data;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
        });
      }
    }
  }

  Future<void> _refreshData() async {
    if (widget.fetchData == null) {
      return _loadScreen();
    }

    try {
      final data = await widget.fetchData!(widget.screenId);
      if (mounted) {
        setState(() {
          _data = data;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to refresh: $e'),
            action: SnackBarAction(
              label: 'Retry',
              onPressed: _refreshData,
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const _LoadingState();
    }

    if (_error != null) {
      return _ErrorState(
        error: _error!,
        onRetry: _loadScreen,
      );
    }

    if (_screen == null) {
      return const _EmptyState();
    }

    final renderer = SDUIRenderer(
      config: widget.config,
      screenData: _data,
      onAction: widget.onAction,
    );

    return RefreshIndicator(
      onRefresh: _refreshData,
      child: renderer.renderScreen(_screen!),
    );
  }
}

/// Loading State Widget
class _LoadingState extends StatelessWidget {
  const _LoadingState();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(),
          SizedBox(height: 16),
          Text('Loading...'),
        ],
      ),
    );
  }
}

/// Error State Widget
class _ErrorState extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;

  const _ErrorState({
    required this.error,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.red[300],
            ),
            const SizedBox(height: 16),
            Text(
              'Something went wrong',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(
              error,
              style: TextStyle(color: Colors.grey[600]),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Try Again'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Empty State Widget
class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.inbox_outlined,
            size: 64,
            color: Colors.grey[400],
          ),
          const SizedBox(height: 16),
          Text(
            'No content available',
            style: TextStyle(color: Colors.grey[600]),
          ),
        ],
      ),
    );
  }
}

/// SDUI Screen with AppBar
///
/// Wraps SDUIScreen with a Scaffold and AppBar showing screen title.
class SDUIScaffold extends StatelessWidget {
  final String screenId;
  final String? title;
  final Future<Map<String, dynamic>> Function(String) fetchScreen;
  final Future<Map<String, dynamic>> Function(String)? fetchData;
  final Map<String, dynamic> config;
  final void Function(String action, Map<String, dynamic>? params)? onAction;
  final List<Widget>? actions;

  const SDUIScaffold({
    super.key,
    required this.screenId,
    this.title,
    required this.fetchScreen,
    required this.config,
    this.fetchData,
    this.onAction,
    this.actions,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(title ?? _formatScreenId(screenId)),
        actions: actions,
      ),
      body: SDUIScreen(
        screenId: screenId,
        fetchScreen: fetchScreen,
        fetchData: fetchData,
        config: config,
        onAction: onAction,
      ),
    );
  }

  String _formatScreenId(String id) {
    return id
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }
}
