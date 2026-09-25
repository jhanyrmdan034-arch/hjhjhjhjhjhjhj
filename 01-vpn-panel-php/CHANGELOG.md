# Changelog

## 1.0.2
- Added Admin Gate regeneration in App Settings.
- Public package contains no runtime config, subscription URL, API key, Cron token, or Admin Gate.

## 1.0.1
- Fixed all admin redirects and asset/image URLs when the panel is installed inside a subdirectory such as `/test`.
- Prevented redirects from jumping to the parent WordPress site's `/admin/` path.
- Added installation-aware `app_path()` helper.

## 1.0.0
- Initial release.
