# Image Rename Script for OpenCart — Manual
This script batch-renames product images in your OpenCart catalog, generating SEO-friendly filenames and updating the database accordingly.

## Features
Scans a specified image folder (optionally recursively)
Renames images using transliterated product names or meta titles
Updates image paths in the database
Supports dry-run mode (no actual changes, just logs)
Logs all actions to a file
Requirements
OpenCart installation (tested on 2.x/3.x)
PHP CLI access
Sufficient permissions to read/write image files and update the database
## Usage
### Copy the Script

Place _image_rename_v1.php_ in your OpenCart root or a safe location.

### Edit Configuration

- Open _image_rename_v1.php_.
- Set `$dry_run = false;` to actually rename files (default is true for testing).
- Adjust `$language_id` if needed (example, 1 for Ukrainian, 2 for Russian etc).
- Run the Script

Open a terminal in your OpenCart root and run:

folder (optional): Relative path to scan (default: catalog/)
recursive (optional): true to scan subfolders recursively
Examples:

Scan `catalog/ non-recursively` (default):
`php image_rename_v1.php`
`Scan catalog/products/ recursively:`
`php [image_rename_v1.php](http://_vscodecontentref_/2) catalog/products/ true`
Check the Log

All actions are logged to _system/storage/logs/image_rename.log_.
Review this file for details and errors.

## Notes
+ Dry-run mode ($dry_run = true) logs intended changes without modifying files or the database.
+ Actual renaming: Set `$dry_run = false` to apply changes.
+ Back up your images and database before running in production mode.
+ The script only processes images linked to products in the database.

## Troubleshooting
- If you see Папка не знайдена, check your folder path.
- Ensure PHP has permissions to rename files and write logs.
- Check OpenCart's config.php and DIR_* constants are correct.

## License
MIT License.
Use at your own risk. Always back up your data before running batch scripts.
