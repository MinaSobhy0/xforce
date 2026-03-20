import 'package:flutter/material.dart';

/// SDUI Renderer - Renders server-driven UI components
///
/// Maps component types from API to Flutter widgets.
/// Add new component types here as needed.
class SDUIRenderer {
  final Map<String, dynamic> config;
  final Map<String, dynamic>? screenData;
  final void Function(String action, Map<String, dynamic>? params)? onAction;

  SDUIRenderer({
    required this.config,
    this.screenData,
    this.onAction,
  });

  /// Get branding colors from config
  Color get primaryColor =>
      _parseColor(config['branding']?['primary_color']) ?? Colors.blue;

  Color get secondaryColor =>
      _parseColor(config['branding']?['secondary_color']) ?? Colors.blueAccent;

  Color get accentColor =>
      _parseColor(config['branding']?['accent_color']) ?? Colors.amber;

  /// Render a full screen from SDUI definition
  Widget renderScreen(Map<String, dynamic> screen) {
    final components = screen['components'] as List<dynamic>? ?? [];

    if (components.isEmpty) {
      return const Center(child: Text('No content available'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: components.length,
      itemBuilder: (context, index) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: renderComponent(components[index] as Map<String, dynamic>),
        );
      },
    );
  }

  /// Render a single component by type
  Widget renderComponent(Map<String, dynamic> component) {
    final type = component['type'] as String;
    final props = component['props'] as Map<String, dynamic>? ?? {};

    switch (type) {
      case 'attendance_status':
        return AttendanceStatusCard(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'stats_grid':
        return StatsGrid(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
        );

      case 'upcoming_appointments':
        return UpcomingAppointments(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'quick_actions':
        return QuickActions(
          props: props,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'attendance_card':
        return AttendanceCard(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'tab_bar':
        return SDUITabBar(
          props: props,
          primaryColor: primaryColor,
        );

      case 'attendance_history':
        return AttendanceHistory(
          props: props,
          data: screenData,
        );

      case 'balance_cards':
        return BalanceCards(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
        );

      case 'request_button':
        return RequestButton(
          props: props,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'requests_list':
        return RequestsList(
          props: props,
          data: screenData,
        );

      case 'period_selector':
        return PeriodSelector(
          props: props,
          primaryColor: primaryColor,
        );

      case 'salary_summary':
        return SalarySummary(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
        );

      case 'earnings_breakdown':
        return EarningsBreakdown(
          props: props,
          data: screenData,
        );

      case 'deductions_breakdown':
        return DeductionsBreakdown(
          props: props,
          data: screenData,
        );

      case 'download_button':
        return DownloadButton(
          props: props,
          primaryColor: primaryColor,
          onAction: onAction,
        );

      case 'week_calendar':
        return WeekCalendar(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
        );

      case 'shift_card':
        return ShiftCard(
          props: props,
          data: screenData,
        );

      case 'working_hours':
        return WorkingHours(
          props: props,
          data: screenData,
        );

      case 'date_picker':
        return SDUIDatePicker(
          props: props,
          primaryColor: primaryColor,
        );

      case 'appointments_list':
        return AppointmentsList(
          props: props,
          data: screenData,
          onAction: onAction,
        );

      case 'search_bar':
        return SDUISearchBar(
          props: props,
        );

      case 'patients_list':
        return PatientsList(
          props: props,
          data: screenData,
          onAction: onAction,
        );

      case 'profile_header':
        return ProfileHeader(
          props: props,
          data: screenData,
        );

      case 'profile_details':
        return ProfileDetails(
          props: props,
          data: screenData,
        );

      case 'action_list':
        return ActionList(
          props: props,
          onAction: onAction,
        );

      case 'commission_summary':
        return CommissionSummary(
          props: props,
          data: screenData,
          primaryColor: primaryColor,
        );

      case 'commission_plan':
        return CommissionPlan(
          props: props,
          data: screenData,
        );

      case 'commission_history':
        return CommissionHistory(
          props: props,
          data: screenData,
        );

      default:
        return _unknownComponent(type);
    }
  }

  Widget _unknownComponent(String type) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.grey[200],
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey[400]!),
      ),
      child: Row(
        children: [
          Icon(Icons.extension, color: Colors.grey[600]),
          const SizedBox(width: 12),
          Text(
            'Unknown component: $type',
            style: TextStyle(color: Colors.grey[600]),
          ),
        ],
      ),
    );
  }

  Color? _parseColor(String? colorHex) {
    if (colorHex == null) return null;
    final hex = colorHex.replaceFirst('#', '');
    return Color(int.parse('FF$hex', radix: 16));
  }
}

// ============================================================
// COMPONENT WIDGETS
// ============================================================

/// Attendance Status Card - Main check-in/out interface
class AttendanceStatusCard extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const AttendanceStatusCard({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final isCheckedIn = data?['is_checked_in'] ?? false;
    final checkInTime = data?['check_in_time'];
    final isOnBreak = data?['is_on_break'] ?? false;

    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            // Status Icon
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isCheckedIn
                    ? (isOnBreak ? Colors.orange[50] : Colors.green[50])
                    : Colors.grey[100],
              ),
              child: Icon(
                isCheckedIn
                    ? (isOnBreak ? Icons.coffee : Icons.check_circle)
                    : Icons.radio_button_unchecked,
                size: 48,
                color: isCheckedIn
                    ? (isOnBreak ? Colors.orange : Colors.green)
                    : Colors.grey,
              ),
            ),
            const SizedBox(height: 16),

            // Status Text
            Text(
              isCheckedIn
                  ? (isOnBreak ? 'On Break' : 'Checked In')
                  : 'Not Checked In',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),

            if (checkInTime != null) ...[
              const SizedBox(height: 8),
              Text(
                'Since $checkInTime',
                style: TextStyle(color: Colors.grey[600]),
              ),
            ],

            const SizedBox(height: 24),

            // Action Buttons
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (!isCheckedIn)
                  ElevatedButton.icon(
                    onPressed: () => onAction?.call('check_in', null),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 32,
                        vertical: 12,
                      ),
                    ),
                    icon: const Icon(Icons.login),
                    label: const Text('Check In'),
                  ),
                if (isCheckedIn && !isOnBreak) ...[
                  OutlinedButton.icon(
                    onPressed: () => onAction?.call('start_break', null),
                    icon: const Icon(Icons.coffee),
                    label: const Text('Break'),
                  ),
                  const SizedBox(width: 12),
                  ElevatedButton.icon(
                    onPressed: () => onAction?.call('check_out', null),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      foregroundColor: Colors.white,
                    ),
                    icon: const Icon(Icons.logout),
                    label: const Text('Check Out'),
                  ),
                ],
                if (isOnBreak)
                  ElevatedButton.icon(
                    onPressed: () => onAction?.call('end_break', null),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      foregroundColor: Colors.white,
                    ),
                    icon: const Icon(Icons.play_arrow),
                    label: const Text('End Break'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Statistics Grid - 2x2 grid of stat cards
class StatsGrid extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;

  const StatsGrid({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
  });

  @override
  Widget build(BuildContext context) {
    final items = props['items'] as List<dynamic>? ?? [];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 1.5,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
      ),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index] as Map<String, dynamic>;
        final key = item['key'] as String;
        final label = item['label'] as String? ?? _formatKey(key);
        final value = data?[key]?.toString() ?? '0';

        return Card(
          elevation: 1,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  value,
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: primaryColor,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  label,
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  String _formatKey(String key) {
    return key
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }
}

/// Upcoming Appointments List
class UpcomingAppointments extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const UpcomingAppointments({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final appointments = data?['upcoming_appointments'] as List<dynamic>? ?? [];
    final limit = props['limit'] as int? ?? 3;

    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Upcoming Appointments',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                TextButton(
                  onPressed: () => onAction?.call('view_all_appointments', null),
                  child: const Text('View All'),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          if (appointments.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'No upcoming appointments',
                  style: TextStyle(color: Colors.grey),
                ),
              ),
            )
          else
            ...appointments.take(limit).map((apt) {
              final appointment = apt as Map<String, dynamic>;
              return ListTile(
                leading: CircleAvatar(
                  backgroundColor: primaryColor.withOpacity(0.1),
                  child: Text(
                    (appointment['patient_name'] as String?)?.isNotEmpty == true
                        ? appointment['patient_name'][0].toUpperCase()
                        : '?',
                    style: TextStyle(color: primaryColor),
                  ),
                ),
                title: Text(appointment['patient_name'] ?? 'Unknown Patient'),
                subtitle: Text(appointment['service_name'] ?? ''),
                trailing: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      appointment['time'] ?? '',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    if (appointment['status'] != null)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: _getStatusColor(appointment['status']),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          appointment['status'],
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                          ),
                        ),
                      ),
                  ],
                ),
                onTap: () => onAction?.call('view_appointment', {
                  'id': appointment['id'],
                }),
              );
            }),
        ],
      ),
    );
  }

  Color _getStatusColor(String? status) {
    return switch (status) {
      'confirmed' => Colors.green,
      'pending' => Colors.orange,
      'in_progress' => Colors.blue,
      'completed' => Colors.grey,
      'cancelled' => Colors.red,
      _ => Colors.grey,
    };
  }
}

/// Quick Actions - Horizontal action buttons
class QuickActions extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const QuickActions({
    super.key,
    required this.props,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final items = props['items'] as List<dynamic>? ?? [];

    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: items.map((item) {
        final action = item as Map<String, dynamic>;
        final key = action['key'] as String;
        final icon = _getIcon(action['icon'] as String? ?? 'square');

        return ActionChip(
          avatar: Icon(icon, size: 18, color: primaryColor),
          label: Text(_formatKey(key)),
          onPressed: () => onAction?.call('navigate', {'screen': key}),
        );
      }).toList(),
    );
  }

  IconData _getIcon(String name) {
    return switch (name) {
      'calendar' => Icons.calendar_today,
      'clock' => Icons.access_time,
      'document' => Icons.description,
      'users' => Icons.people,
      'home' => Icons.home,
      'settings' => Icons.settings,
      'profile' => Icons.person,
      _ => Icons.square,
    };
  }

  String _formatKey(String key) {
    return key
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }
}

// ============================================================
// PLACEHOLDER COMPONENTS (implement as needed)
// ============================================================

class AttendanceCard extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const AttendanceCard({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Attendance Card - TODO'),
      ),
    );
  }
}

class SDUITabBar extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;

  const SDUITabBar({super.key, required this.props, required this.primaryColor});

  @override
  Widget build(BuildContext context) {
    return const SizedBox.shrink(); // Implement as needed
  }
}

class AttendanceHistory extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const AttendanceHistory({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Attendance History - TODO'),
      ),
    );
  }
}

class BalanceCards extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;

  const BalanceCards({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Balance Cards - TODO'),
      ),
    );
  }
}

class RequestButton extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const RequestButton({
    super.key,
    required this.props,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return ElevatedButton(
      onPressed: () => onAction?.call('new_request', null),
      child: const Text('New Request'),
    );
  }
}

class RequestsList extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const RequestsList({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Requests List - TODO'),
      ),
    );
  }
}

class PeriodSelector extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;

  const PeriodSelector({super.key, required this.props, required this.primaryColor});

  @override
  Widget build(BuildContext context) {
    return const SizedBox.shrink();
  }
}

class SalarySummary extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;

  const SalarySummary({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Salary Summary - TODO'),
      ),
    );
  }
}

class EarningsBreakdown extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const EarningsBreakdown({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Earnings Breakdown - TODO'),
      ),
    );
  }
}

class DeductionsBreakdown extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const DeductionsBreakdown({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Deductions Breakdown - TODO'),
      ),
    );
  }
}

class DownloadButton extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const DownloadButton({
    super.key,
    required this.props,
    required this.primaryColor,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: () => onAction?.call('download', props),
      icon: const Icon(Icons.download),
      label: const Text('Download'),
    );
  }
}

class WeekCalendar extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;

  const WeekCalendar({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Week Calendar - TODO'),
      ),
    );
  }
}

class ShiftCard extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const ShiftCard({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Shift Card - TODO'),
      ),
    );
  }
}

class WorkingHours extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const WorkingHours({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Working Hours - TODO'),
      ),
    );
  }
}

class SDUIDatePicker extends StatelessWidget {
  final Map<String, dynamic> props;
  final Color primaryColor;

  const SDUIDatePicker({super.key, required this.props, required this.primaryColor});

  @override
  Widget build(BuildContext context) {
    return const SizedBox.shrink();
  }
}

class AppointmentsList extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const AppointmentsList({
    super.key,
    required this.props,
    this.data,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Appointments List - TODO'),
      ),
    );
  }
}

class SDUISearchBar extends StatelessWidget {
  final Map<String, dynamic> props;

  const SDUISearchBar({super.key, required this.props});

  @override
  Widget build(BuildContext context) {
    return TextField(
      decoration: InputDecoration(
        hintText: props['placeholder'] ?? 'Search...',
        prefixIcon: const Icon(Icons.search),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
        ),
      ),
    );
  }
}

class PatientsList extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const PatientsList({
    super.key,
    required this.props,
    this.data,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Patients List - TODO'),
      ),
    );
  }
}

class ProfileHeader extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const ProfileHeader({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Profile Header - TODO'),
      ),
    );
  }
}

class ProfileDetails extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const ProfileDetails({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Profile Details - TODO'),
      ),
    );
  }
}

class ActionList extends StatelessWidget {
  final Map<String, dynamic> props;
  final void Function(String, Map<String, dynamic>?)? onAction;

  const ActionList({super.key, required this.props, this.onAction});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Action List - TODO'),
      ),
    );
  }
}

class CommissionSummary extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;
  final Color primaryColor;

  const CommissionSummary({
    super.key,
    required this.props,
    this.data,
    required this.primaryColor,
  });

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Commission Summary - TODO'),
      ),
    );
  }
}

class CommissionPlan extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const CommissionPlan({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Commission Plan - TODO'),
      ),
    );
  }
}

class CommissionHistory extends StatelessWidget {
  final Map<String, dynamic> props;
  final Map<String, dynamic>? data;

  const CommissionHistory({super.key, required this.props, this.data});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Text('Commission History - TODO'),
      ),
    );
  }
}
