# Fasting Tracker Plugin

A comprehensive WordPress plugin for tracking fasting periods with visual progress meters, milestone achievements, and detailed statistics. Supports fasting durations up to 72 hours with real-time updates and metabolic phase tracking.

## Features

### Core Functionality
- **Start/Pause/Resume/End Fast** - Full control over fasting sessions
- **Real-Time Progress Meter** - Visual circular meter with color-coded phases
- **Milestone Tracking** - Automatic detection and badges at 12h, 24h, 36h, 48h, 72h
- **Phase Information** - Detailed metabolic phase descriptions based on elapsed time
- **User Statistics** - Total fasts, hours fasted, longest fast, current streak
- **History Log** - Complete history of all completed fasting sessions
- **Preset Durations** - Quick-start buttons for common fasting protocols (16:8, 20:4, 24h, 48h, 72h)
- **Dark Cyberpunk UI** - Dystopian aesthetic with red accents and dark backgrounds

### Technical Features
- REST API endpoints for all fasting operations
- AJAX-powered real-time timer updates (every 10 seconds)
- Custom database tables for data storage (no wp_options bloat)
- User-configurable data cleanup on uninstall
- Responsive design (mobile-first)
- Font Awesome icon integration
- Server-side time calculations (prevents client manipulation)
- Secure with nonce verification and capability checks

## Installation

1. Upload the `fasting-tracker` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings at **Fasting Tracker** in the admin menu
4. Add shortcodes to pages or posts where you want the tracker to appear

## Shortcodes

### `[fasting_tracker]`
Displays the complete fasting dashboard including:
- Progress meter with current elapsed time
- Start/pause/resume/end controls
- Current metabolic phase information
- Next milestone countdown
- User statistics (total fasts, hours, streaks)
- Recent history table

**Usage:**
```
[fasting_tracker]
```

### `[fasting_meter]`
Displays standalone progress meter for currently active fast.

**Usage:**
```
[fasting_meter]
```

## Metabolic Phases

The plugin tracks and displays five metabolic phases:

### Phase 1: Glycogen Depletion (0-12 hours)
- **Color:** Orange (#ff8c00)
- **Description:** Initial hunger expected, body using stored glycogen
- **Metabolic State:** Transition from glucose to fat metabolism begins

### Phase 2: Ketosis Begins (12-24 hours)
- **Color:** Yellow (#ffd700)
- **Description:** Fat burning starts, ketone production begins
- **Metabolic State:** Liver glycogen depletes, ketogenesis accelerates
- **Milestone:** "Ketosis Initiated" badge at 12 hours

### Phase 3: Deep Ketosis (24-36 hours)
- **Color:** Light Green (#90ee90)
- **Description:** Full ketosis active, autophagy accelerates
- **Metabolic State:** Fat burning increases significantly, cellular cleanup begins
- **Milestone:** "24-Hour Milestone" badge at 24 hours

### Phase 4: Peak Metabolic State (36-48 hours)
- **Color:** Green (#00ff00)
- **Description:** Highest ketone production, mental clarity peaks
- **Metabolic State:** Growth hormone increases, inflammation decreases
- **Milestone:** "Peak Performance" badge at 36 hours

### Phase 5: Extended Fasting (48-72 hours)
- **Color:** Dark Green (#006400)
- **Description:** Sustained ketosis, cellular repair in full swing
- **Metabolic State:** Complete cellular reset, maximum autophagy
- **Milestones:** 
  - "48-Hour Champion" badge at 48 hours
  - "72-Hour Master" badge at 72 hours

## REST API Endpoints

All endpoints require user authentication. Base URL: `/wp-json/fasting/v1/`

### POST `/start`
Start a new fast.

**Parameters:**
- `target_hours` (integer, required) - Target duration (1-72 hours)

**Response:**
```json
{
  "success": true,
  "session_id": 123,
  "message": "Fast started successfully"
}
```

### POST `/pause`
Pause the active fast.

**Parameters:**
- `session_id` (integer, required) - Current session ID

**Response:**
```json
{
  "success": true,
  "message": "Fast paused"
}
```

### POST `/resume`
Resume a paused fast.

**Parameters:**
- `session_id` (integer, required) - Current session ID

**Response:**
```json
{
  "success": true,
  "message": "Fast resumed"
}
```

### POST `/end`
End the active fast and record milestones.

**Parameters:**
- `session_id` (integer, required) - Current session ID

**Response:**
```json
{
  "success": true,
  "duration": 24.5,
  "milestones": [
    {"hours": 12, "badge": "Ketosis Initiated"},
    {"hours": 24, "badge": "24-Hour Milestone"}
  ],
  "message": "Fast completed"
}
```

### GET `/current`
Get current active fast data.

**Response:**
```json
{
  "active": true,
  "fast": {
    "session_id": 123,
    "start_time": "2025-10-17 08:00:00",
    "target_duration": 24,
    "is_paused": false,
    "elapsed_hours": 12.5,
    "current_phase": {
      "name": "Ketosis Begins",
      "description": "Fat burning starts...",
      "color": "#ffd700"
    },
    "next_milestone": {
      "hours": 24,
      "badge": "24-Hour Milestone",
      "hours_remaining": 11.5
    },
    "progress_percent": 52.08
  }
}
```

### GET `/history`
Get user's fasting history.

**Parameters:**
- `limit` (integer, optional) - Number of records (default: 10)

**Response:**
```json
{
  "history": [
    {
      "id": 123,
      "start_time": "2025-10-16 08:00:00",
      "end_time": "2025-10-17 08:00:00",
      "actual_duration": 24,
      "completed": 1
    }
  ]
}
```

### GET `/stats`
Get user's statistics.

**Response:**
```json
{
  "stats": {
    "total_fasts": 15,
    "total_hours": 360,
    "longest_fast": 48,
    "current_streak": 7,
    "milestones_earned": 4
  }
}
```

## Database Schema

The plugin creates three custom database tables:

### `wp_fasting_sessions`
Stores all fasting sessions.

```sql
CREATE TABLE wp_fasting_sessions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime DEFAULT NULL,
  target_duration int(11) NOT NULL,
  actual_duration int(11) DEFAULT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  paused_at datetime DEFAULT NULL,
  completed tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY is_active (is_active),
  KEY created_at (created_at)
)
```

### `wp_fasting_milestones`
Stores achieved milestones.

```sql
CREATE TABLE wp_fasting_milestones (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL,
  milestone_hours int(11) NOT NULL,
  achieved_at datetime NOT NULL,
  badge_name varchar(100) NOT NULL,
  PRIMARY KEY (id),
  KEY session_id (session_id),
  KEY milestone_hours (milestone_hours)
)
```

### `wp_fasting_settings`
Stores plugin configuration.

```sql
CREATE TABLE wp_fasting_settings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  setting_key varchar(100) NOT NULL,
  setting_value longtext NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY setting_key (setting_key)
)
```

## Plugin Hooks

The plugin provides hooks for extensibility:

### Actions

**`fasting_tracker_fast_started`**
Fired when a fast is started.
```php
do_action('fasting_tracker_fast_started', $session_id, $user_id, $target_hours);
```

**`fasting_tracker_fast_paused`**
Fired when a fast is paused.
```php
do_action('fasting_tracker_fast_paused', $session_id, $user_id);
```

**`fasting_tracker_fast_resumed`**
Fired when a fast is resumed.
```php
do_action('fasting_tracker_fast_resumed', $session_id, $user_id);
```

**`fasting_tracker_fast_ended`**
Fired when a fast is completed.
```php
do_action('fasting_tracker_fast_ended', $session_id, $user_id, $duration, $milestones);
```

**`fasting_tracker_milestone_achieved`**
Fired when a milestone is reached.
```php
do_action('fasting_tracker_milestone_achieved', $session_id, $milestone_hours, $badge_name);
```

### Filters

**`fasting_tracker_milestone_descriptions`**
Filter milestone descriptions.
```php
$descriptions = apply_filters('fasting_tracker_milestone_descriptions', $descriptions);
```

**`fasting_tracker_phase_colors`**
Filter phase colors.
```php
$colors = apply_filters('fasting_tracker_phase_colors', $colors);
```

**`fasting_tracker_max_duration`**
Filter maximum fast duration (default: 72 hours).
```php
$max_hours = apply_filters('fasting_tracker_max_duration', 72);
```

**`fasting_tracker_update_interval`**
Filter timer update interval in milliseconds (default: 10000).
```php
$interval = apply_filters('fasting_tracker_update_interval', 10000);
```

## File Structure

```
fasting-tracker/
├── fasting-tracker.php           # Main plugin file (initialization)
├── uninstall.php                 # Cleanup on plugin deletion
├── index.php                     # Security stub
├── README.md                     # This file
├── includes/
│   ├── class-fasting-database.php    # Database operations
│   ├── class-fasting-core.php        # Core business logic
│   ├── class-fasting-api.php         # REST API endpoints
│   ├── class-fasting-admin.php       # Admin settings page
│   ├── class-fasting-public.php      # Shortcodes & front-end
│   └── index.php                     # Security stub
└── assets/
    ├── public.css                # Front-end styling (cyberpunk theme)
    ├── public.js                 # Front-end JavaScript
    └── index.php                 # Security stub
```

## Settings

Configure the plugin at **Fasting Tracker** in the WordPress admin menu.

### Available Settings

**Enable Notifications**
- Show milestone notifications in the dashboard
- Default: Enabled

**Email Alerts**
- Send email alerts when milestones are achieved
- Default: Disabled

**Delete Data on Uninstall**
- Remove all plugin data (sessions, milestones, settings) when uninstalling
- Default: Disabled (data is preserved)

## Requirements

- **WordPress:** 6.8 or higher
- **PHP:** 8.0 or higher
- **MySQL:** 5.7 or higher (or MariaDB 10.2+)
- **Font Awesome:** Required for icons (should be available on your site)

## Security Features

- Nonce verification on all forms and AJAX requests
- Capability checks for all admin operations
- Prepared statements for all database queries
- Output escaping on all user data
- Server-side time calculations (prevents manipulation)
- Input validation and sanitization
- SQL injection protection via `$wpdb->prepare()`
- XSS protection via escaping functions
- CSRF protection via nonces

## Performance Optimizations

- Lazy loading of settings (loaded only when needed)
- Database query caching in object properties
- Transient caching for expensive operations
- Conditional asset loading (only on pages with shortcodes)
- Indexed database columns for fast queries
- Minimal AJAX overhead (10-second update intervals)

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Styling

The plugin uses a dark cyberpunk aesthetic:
- **Background:** #262626 (dark gray)
- **Text:** #ffffff (white)
- **Accent:** #d11c1c (red)
- Glowing effects and shadows
- Angular, geometric design
- Monospace fonts for numbers
- Responsive grid layouts
- Mobile-optimized controls

## Development

### Adding Custom Milestones

```php
add_filter('fasting_tracker_milestone_descriptions', function($descriptions) {
    $descriptions[18] = 'Custom milestone at 18 hours';
    return $descriptions;
});
```

### Modifying Phase Colors

```php
add_filter('fasting_tracker_phase_colors', function($colors) {
    $colors['ketosis_begins'] = '#custom-color';
    return $colors;
});
```

### Listening to Fast Events

```php
add_action('fasting_tracker_fast_started', function($session_id, $user_id, $target_hours) {
    // Custom logic when fast starts
}, 10, 3);

add_action('fasting_tracker_milestone_achieved', function($session_id, $hours, $badge) {
    // Send custom notification
}, 10, 3);
```

## Troubleshooting

### Timer Not Updating
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify AJAX URL is correct
- Confirm user is logged in

### Shortcode Not Displaying
- Verify user is logged in
- Check shortcode spelling: `[fasting_tracker]`
- Ensure plugin is activated
- Clear cache if using caching plugin

### Database Errors
- Verify MySQL version (5.7+)
- Check WordPress prefix in database
- Ensure proper database permissions
- Reactivate plugin to recreate tables

### Styles Not Applying
- Check if Font Awesome is loaded
- Clear browser cache
- Verify page has shortcode (conditional loading)
- Check for CSS conflicts with theme

## Support

For issues, feature requests, or questions:
1. Check this README documentation
2. Review the code comments in plugin files
3. Enable WP_DEBUG to see error messages
4. Check browser console for JavaScript errors

## Changelog

### Version 1.0.0
- Initial release
- Full fasting tracking functionality
- REST API implementation
- Real-time timer updates
- Milestone system with badges
- User statistics and history
- Dark cyberpunk UI theme
- Mobile-responsive design
- Custom database tables
- User-configurable uninstall cleanup

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Developed for WordPress 6.8+ with focus on:
- Security best practices
- Performance optimization
- User experience
- Code maintainability
- Extensibility via hooks

---

**Version:** 1.0.0  
**Last Updated:** October 2025  
**Requires WordPress:** 6.8+  
**Requires PHP:** 8.0+
