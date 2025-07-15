# WPTAG - WordPress Code Tag Manager

Professional WordPress plugin for managing tracking codes, analytics scripts, and third-party integrations with advanced conditional loading.

## Features

### Core Functionality
- **Code Snippet Management**: Add, edit, and organize code snippets with categories
- **Multiple Insert Positions**: Head, footer, before/after content
- **Smart Conditional Loading**: Control when and where snippets appear
- **Service Templates**: Quick setup for popular services like Google Analytics, Facebook Pixel
- **Performance Optimization**: Built-in caching and code optimization
- **Import/Export**: Backup and migrate your snippets easily

### Conditional Loading Options
- Page types (home, posts, pages, archives, etc.)
- User status (logged in/out, user roles)
- Device types (desktop, mobile, tablet)
- Specific posts/pages
- Categories and tags
- URL patterns
- Date/time ranges
- Custom conditions via filters

### Supported Services Templates
- Google Analytics 4
- Facebook Pixel
- Google Ads Conversion
- Google Search Console
- Baidu Analytics
- And more...

## Installation

1. Upload the `wptag` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WPTAG in your WordPress admin

## Usage

### Creating a Snippet
1. Go to WPTAG > Code Snippets
2. Click "Add New"
3. Enter snippet details:
   - Name and description
   - Code content
   - Position (head/footer/content)
   - Category and priority
   - Conditions (optional)
4. Save and activate

### Using Templates
1. Go to WPTAG > Service Templates
2. Select a service (e.g., Google Analytics)
3. Enter your configuration (e.g., Tracking ID)
4. Click "Create Snippet"

### Setting Conditions
- Add multiple conditions to control snippet visibility
- Combine conditions with AND/OR logic
- Test conditions in preview mode

## Requirements

- WordPress 6.8+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+

## Database Tables

The plugin creates three tables:
- `wp_wptag_snippets` - Stores code snippets
- `wp_wptag_templates` - Service templates
- `wp_wptag_logs` - Activity logs

## Hooks and Filters

### Actions
- `wptag_before_render_snippet` - Before snippet output
- `wptag_after_render_snippet` - After snippet output

### Filters
- `wptag_snippet_output` - Modify snippet output
- `wptag_custom_condition` - Add custom conditions
- `wptag_cache_ttl` - Modify cache duration

## Performance

- Intelligent caching reduces database queries
- Conditional pre-processing for faster page loads
- Code minification option
- Compatible with popular caching plugins

## Security

- Input validation and sanitization
- XSS protection
- SQL injection prevention
- User capability checks
- Nonce verification for all actions

## Troubleshooting

### Snippets not appearing
1. Check if snippet is active
2. Verify conditions are met
3. Clear cache (WPTAG Settings > Clear Cache)
4. Enable debug mode for detailed output

### Performance issues
1. Reduce number of active snippets
2. Enable caching
3. Optimize conditions
4. Use priority settings wisely

## Developer Documentation

### Adding Custom Conditions

```php
add_filter('wptag_custom_condition', function($result, $type, $value, $operator, $context) {
    if ($type === 'my_custom_condition') {
        // Your condition logic here
        return $result;
    }
    return $result;
}, 10, 5);
```

### Modifying Snippet Output

```php
add_filter('wptag_snippet_output', function($code, $snippet) {
    // Modify code before output
    return $code;
}, 10, 2);
```

## Support

For support and documentation, visit [wptag.com](https://wptag.com)

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Core snippet management
- Conditional loading engine
- Service templates
- Caching system
- Import/export functionality
