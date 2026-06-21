# Changelog

## 1.0.1 - 2026-06-21

### Fixed
- Restored disabled element detection in Broken References auditor
- Reverted Missing Alt Text SQL pre-filter that incorrectly excluded assets in Craft 5
- Fixed sidebar only showing Orphaned Assets on first install — now lists all registered checks dynamically
- Fixed N+1 queries in Broken References auditor via bulk element loading
- Added try/catch around audit runs — failures now show a graceful error instead of a 500
- Added `requirePermission` to CP controller actions
- Audit runs now pruned to last 10 to prevent unbounded DB growth
- Updated developer URLs to correct GitHub account

## 1.0.0 - 2026-06-21

### Added
- Orphaned Assets — detects uploaded files not referenced by any entry
- Missing Alt Text — flags images with no alt text set
- Large Assets — warns on files over 2 MB, critical above 5 MB
- Broken References — surfaces entries linking to disabled or deleted elements
- Persistent results — audit runs stored in the database, survive navigation
- Sidebar navigation — all checks listed with live issue counts, anchored sections
- Thumbnail previews next to image filenames in results tables
