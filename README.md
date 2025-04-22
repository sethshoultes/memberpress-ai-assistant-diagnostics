# MemberPress AI Assistant Diagnostics

## Description
This WordPress plugin provides diagnostic and testing tools for the MemberPress AI Assistant plugin. It helps developers and administrators debug, test, and validate the functionality of the AI Assistant.

## Features
- Comprehensive diagnostic dashboard within WordPress admin
- Edge case testing for AI tools and agents
- Error recovery system validation
- Performance monitoring and benchmarking
- Integration tests for various AI tools
- System health checks and environment validation

## Installation
1. Upload the `memberpress-ai-assistant-diagnostics` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the diagnostics page from the WordPress Tools menu

## Usage
Once activated, the plugin adds a diagnostics page to the WordPress Tools menu. From this page, you can:

- Run system diagnostics
- Test edge cases and error recovery
- Monitor AI performance
- Validate tool executions
- Check log files and error reports

## Requirements
- WordPress 5.8 or higher
- MemberPress AI Assistant plugin

## Test Suites
The plugin includes several test suites:
- Edge case testing
- Integration testing
- Error recovery
- Resource limits
- Input validation
- Plugin activation/deactivation

## For Developers
The diagnostics plugin provides hooks and filters for extending the testing capabilities:
- `mpai_render_diagnostics` - Action to render additional diagnostic sections
- `mpai_diagnostic_results` - Filter to modify diagnostic results
- `mpai_test_result` - Filter to process test results

## License
GPL v2 or later

## Author
MemberPress - https://memberpress.com