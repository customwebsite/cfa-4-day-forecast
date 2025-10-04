# GitHub Release Instructions for Automatic Updates

This plugin now supports automatic updates from GitHub! Follow these instructions to create releases that WordPress will automatically detect.

## Quick Release Checklist

- [ ] Update version number in `cfa-fire-forecast.php` header (Line 6)
- [ ] Update version constant in `cfa-fire-forecast.php` (Line 20)
- [ ] Update version in `README.md` (download link and credits)
- [ ] Update `README.md` changelog with release notes
- [ ] Commit all changes to repository
- [ ] Create Git tag (e.g., `v4.2.0`)
- [ ] Push code and tags to GitHub
- [ ] Create GitHub release with tag
- [ ] Upload plugin ZIP file to release

## Step-by-Step Release Process

### 1. Update Version Numbers

Update the version in **THREE** places:

**File: `cfa-fire-forecast-plugin/cfa-fire-forecast.php`**
```php
/**
 * Version: 4.2.0  // Line 6 - Update this
 */

define('CFA_FIRE_FORECAST_VERSION', '4.2.0'); // Line 20 - Update this
```

**File: `README.md`**
```markdown
**[Download CFA Fire Forecast Plugin (v4.2.0)](...)** // Update download link

- **Version**: 4.2.0  // Update credits section
```

### 2. Update Changelog

Add your release notes to `README.md`:

```markdown
## Changelog

### Version 4.2.0
- Feature 1: Description
- Feature 2: Description
- Bug fix: Description
```

### 3. Commit Changes

```bash
git add .
git commit -m "Release version 4.2.0"
```

### 4. Create Git Tag

**IMPORTANT:** Tag format must match the version number exactly!

```bash
# Create tag (use v prefix)
git tag v4.2.0

# Or with annotation (recommended)
git tag -a v4.2.0 -m "Release version 4.2.0"
```

### 5. Push to GitHub

```bash
# Push code
git push origin main

# Push tags
git push --tags
```

### 6. Create GitHub Release

1. Go to your repository: https://github.com/customwebsite/cfa-4-day-forecast
2. Click "Releases" (right sidebar)
3. Click "Create a new release"
4. **Tag:** Select the tag you just created (v4.2.0)
5. **Release title:** Version 4.2.0
6. **Description:** Add your release notes (same as changelog)
7. **Attach files:** Upload `cfa-fire-forecast-plugin.zip`
8. Click "Publish release"

## How Automatic Updates Work

### For WordPress Users:

1. **Update Check:** WordPress checks GitHub every 12 hours for new releases
2. **Version Compare:** Compares installed version with latest GitHub release tag
3. **Notification:** Shows update available in WordPress Dashboard → Plugins
4. **One-Click Update:** User clicks "Update Now" and plugin downloads from GitHub
5. **Installation:** WordPress automatically installs the new version

### Version Detection:

The Plugin Update Checker library:
- Reads the latest Git tag from GitHub (e.g., `v4.2.0`)
- Compares it with `CFA_FIRE_FORECAST_VERSION` constant
- Uses semantic versioning to determine if update is needed

## Version Numbering (Semantic Versioning)

Use this format: `MAJOR.MINOR.PATCH`

- **MAJOR** (4.x.x): Breaking changes, major rewrites
- **MINOR** (x.2.x): New features, backwards compatible
- **PATCH** (x.x.1): Bug fixes, minor improvements

Examples:
- `4.2.0` → `4.2.1` = Bug fix release
- `4.2.0` → `4.3.0` = New feature release
- `4.2.0` → `5.0.0` = Major update with breaking changes

## Testing Updates

### Test on Staging Site First:

1. Install version 4.1.0 on a test WordPress site
2. Create new release 4.2.0 on GitHub
3. Wait ~15 minutes for update check
4. Verify update notification appears
5. Click "Update Now" and verify successful update
6. Test all plugin features work correctly

### Force Update Check:

If you don't want to wait for automatic check:

```php
// Add to functions.php temporarily
delete_site_transient('update_plugins');
```

Then visit: Dashboard → Plugins (update notification should appear)

## Troubleshooting

### Update Not Showing:

1. **Check tag format:** Must be `v4.2.0` (with 'v' prefix)
2. **Check version match:** Tag must match version in plugin header exactly
3. **Check ZIP file:** Make sure ZIP is attached to GitHub release
4. **Clear transients:** Delete `update_plugins` transient
5. **Check GitHub API:** Ensure repository is public (or configure access token for private)

### Version Conflicts:

If WordPress shows wrong version:
1. Verify version in plugin header matches tag
2. Clear all transients
3. Deactivate and reactivate plugin
4. Check for PHP errors in debug.log

### ZIP File Requirements:

Your `cfa-fire-forecast-plugin.zip` must:
- Include the `plugin-update-checker` folder
- Have correct directory structure
- Not include `.git` folder
- Have proper file permissions

## Automation (Optional)

### GitHub Actions for Auto-Release:

Create `.github/workflows/release.yml`:

```yaml
name: Create Release
on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Create plugin ZIP
        run: |
          cd ..
          zip -r cfa-fire-forecast-plugin.zip cfa-fire-forecast-plugin -x "*.git*"
      
      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          files: ../cfa-fire-forecast-plugin.zip
          body: |
            Release ${{ github.ref_name }}
            See CHANGELOG for details.
```

This will automatically:
1. Detect new tag push
2. Create ZIP file
3. Create GitHub release
4. Attach ZIP file

## Support

For issues with automatic updates:
1. Check WordPress error logs
2. Enable WP_DEBUG and check for PHP errors
3. Verify GitHub repository is accessible
4. Test with Plugin Update Checker debug mode

## Security Notes

- Updates are downloaded over HTTPS
- Plugin verifies ZIP integrity
- WordPress checks file permissions before update
- Backup your site before major updates
- Test updates on staging environment first
