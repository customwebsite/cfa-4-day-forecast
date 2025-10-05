# Automated Release Workflow

This repository uses GitHub Actions to automate plugin releases.

## How It Works

When you push a version tag (e.g., `v4.4.0`), the workflow automatically:

1. **Creates the plugin zip file** - Packages the `cfa-fire-forecast-plugin/` directory
2. **Generates release notes** - Creates formatted release notes with installation instructions
3. **Creates GitHub Release** - Publishes the release with the zip file attached
4. **Triggers WordPress updates** - Plugin Update Checker detects the new version

## Creating a New Release

### Step 1: Update Version Numbers

Update the version in these files:
- `cfa-fire-forecast-plugin/cfa-fire-forecast.php` (Version comment and VERSION constant)
- `README.md` (download link version)

### Step 2: Commit Changes

```bash
git add .
git commit -m "Release v4.4.0"
git push origin main
```

### Step 3: Create and Push Tag

```bash
# Create annotated tag
git tag -a v4.4.0 -m "Release v4.4.0 - Brief description"

# Push tag to GitHub
git push origin v4.4.0
```

### Step 4: Workflow Runs Automatically

The GitHub Actions workflow will:
- Build the plugin zip file
- Create the GitHub release
- Upload the zip file as a release asset

### Step 5: Verify Release

Check the release at:
`https://github.com/customwebsite/cfa-4-day-forecast/releases`

## WordPress Auto-Update

Once the release is published, WordPress sites with the plugin installed will:
- Detect the update within 12 hours (or immediately via "Check Again")
- Show "New version available" notification
- Allow one-click update from WordPress admin

## Version Format

- **Tag format:** `v4.4.0` (must start with `v`)
- **Plugin version:** `4.4.0` (without `v`)
- **Example:** Tag `v4.5.0` → Plugin version `4.5.0`

## Troubleshooting

### Workflow Doesn't Trigger
- Verify tag format starts with `v` (e.g., `v4.4.0`)
- Check GitHub Actions tab for workflow status
- Ensure you pushed the tag: `git push origin v4.4.0`

### WordPress Doesn't See Update
- Wait 12 hours or click "Check Again" in WordPress
- Verify release was created on GitHub
- Check that zip file is attached to the release
- Ensure tag matches version in plugin file

## Manual Release (Fallback)

If the workflow fails, you can create a release manually:

1. Go to GitHub → Releases → "Draft a new release"
2. Create tag: `v4.4.0`
3. Upload `cfa-fire-forecast-plugin.zip` manually
4. Add release notes
5. Publish release
