# DeviceDetector Bot Tracker Plugin

## Description

Matomo excludes any detected bots from tracked visits using their [DeviceDetector library](https://github.com/matomo-org/device-detector).

DDBotTracker plugin allows to track these bots separately.

New report page is available at Visitors > Bots, as well as additional widgets for the dashboard.

### Installation / Update

See <https://matomo.org/faq/plugins/faq_21/>.

### How to setup tracking

Add this line to your tracking script:

```javascript
_paq.push(['appendToTrackingUrl', 'bots=1']);
```

More info here: <https://matomo.org/faq/new-to-piwik/faq_63/>

This will disable default Matomo's bot filtering. Plugin uses Matomo's bot detection library and also excludes such visits from further processing, so adding this parameter won't change regular analytics as long as plugin is active.

## Requirements

- Matomo 5.x
- PHP 7.2.5+
- MySQL 8.0+ or MariaDB 10.6+
