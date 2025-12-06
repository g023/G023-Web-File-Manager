# G023 Web File Manager

**⚠️ WARNING: This is a fun project and not intended for production use. Use at your own risk.**

A powerful, web-based file management system designed for efficient server-side file operations. This application provides a modern, responsive interface for navigating, editing, and managing files and directories on your server, with advanced features like drag-and-drop uploads, syntax-highlighted editing, automatic backups, and comprehensive file previews.

- **Interactive File Tree**: Navigate your file system with an expandable tree view, complete with search functionality and drag-and-drop support.
- **IDE-Style File Editing**: Edit files directly in the browser with syntax highlighting for various programming languages, powered by Monaco Editor.
- **Drag and Drop Operations**: Easily move files and folders, or upload new content by dragging items into the interface.
- **File Permissions Management**: Change file and directory permissions (chmod) with a simple interface.
- **Archive Handling**: Upload ZIP files and extract them automatically, or download selected files and directories as ZIP archives.
- **File Previews**: View images, PDFs, text files, and even convert documents to Markdown for quick previews.
- **Full-Text Search**: Search across files and directories to find what you need quickly.
- **Context Menus**: Right-click for quick access to common operations.
- **State Persistence**: The interface remembers your expanded folders, current view, and editor settings.
- **Automatic Backups**: Compressed backups of edited or deleted files, with restore capabilities.
- **Responsive Design**: Works seamlessly on desktop and mobile devices.
- **API-Driven Backend**: RESTful API for integrations and extensions.

## Requirements

- PHP 8.0 or higher with ZipArchive extension
- SQLite 3 or higher
- A web server such as Apache or Nginx
- Modern web browser with JavaScript enabled

## Installation

1. Download or clone the project to your web server's document root directory.
2. Ensure the web server has read/write access to the project directory.
3. Configure your web server to serve PHP files and allow directory listings if needed.
4. Open your browser and navigate to the project's URL.
5. The application will initialize automatically with a default SQLite database.

## Permissions

For the application to function correctly, the web server user (typically `www-data`, `apache`, or `nobody` on Unix-like systems) must have the following permissions:

### Directories Requiring Read/Write Access (755 recommended):
- **Project root directory** (`/`): Full read/write access for file and directory operations.
- **db/**: Read/write access for SQLite database file creation and updates.
- **uploads/**: Read/write access for uploaded files (created automatically if not present).
- **temp/**: Read/write access for temporary files and operations (created automatically if not present).

### Files Requiring Specific Permissions:
- **db/filemanager.db**: Read/write access (664 recommended) for database operations.
- **config/config.php**: Read access only (644 recommended) - contains sensitive configuration data.

### Notes:
- On Windows systems with WAMP/XAMPP, ensure the web server service account has full control over these directories.
- If permission errors occur, use `chmod` commands (Unix) or adjust folder permissions via Windows Explorer (Windows) to grant the web server user appropriate access.
- The application will attempt to create missing directories (`uploads/`, `temp/`) automatically, but may fail if parent directory permissions are insufficient.

The application uses a configuration file located in `config/config.php`. You can modify settings such as:

- Base path of the project
- Upload directory path
- Temporary files directory path
- SQLite database file path
- Application name and version

## Usage

### Basic Navigation

- Use the file tree on the left sidebar to browse directories.
- Click on folders to expand them.
- Click on files to view or edit them.

### File Operations

- **Create**: Use the toolbar or context menu to create new files or folders.
- **Edit**: Click on a file to open it in the editor. Changes are saved automatically with backups.
- **Delete**: Right-click and select delete. Files are backed up before removal.
- **Move/Copy**: Drag files between directories or use the context menu.
- **Permissions**: Right-click and select "Change Permissions" to modify chmod settings.

### Uploads and Downloads

- Drag files from your computer to the upload area.
- For ZIP files, they can be extracted automatically upon upload.
- Select multiple files or directories and download them as a ZIP archive.

### Backups

- All edits and deletions trigger automatic backups.
- Access backups via the context menu on files.
- Restore previous versions directly from the interface.

### Search

- Use the search bar in the file tree to find files and folders by name.
- Full-text search is available for supported file types.

## API

The application includes a RESTful API for programmatic access and integrations:

### File Operations
- `GET /api/files/tree` - Retrieve directory tree structure
- `GET /api/files/list?path=/folder` - List contents of a directory
- `GET /api/files/info?path=/file.txt` - Get file metadata
- `GET /api/files/content?path=/file.txt` - Retrieve file content
- `POST /api/files/create` - Create new files or directories
- `PUT /api/files/rename` - Rename files or directories
- `DELETE /api/files/delete` - Delete files or directories
- `POST /api/files/move` - Move files or directories
- `POST /api/files/copy` - Copy files or directories
- `POST /api/files/chmod` - Change permissions

### Upload/Download
- `POST /api/upload` - Upload files (supports multiple files and drag-drop)
- `POST /api/upload/zip` - Upload and extract ZIP archives
- `GET /api/download?paths[]=/file1&paths[]=/file2` - Download files as ZIP
- `GET /api/download/dir?path=/folder` - Download directories as ZIP

### Previews
- `GET /api/files/preview?path=/image.jpg` - Get image thumbnails
- `GET /api/files/markdown?path=/document.docx` - Convert files to Markdown

### State Management
- `GET /api/state` - Retrieve current UI state
- `POST /api/state` - Save UI state
- `DELETE /api/state` - Clear saved state

### Backup & Restore
- `GET /api/backups?path=/file.txt` - List backup versions
- `GET /api/backups/{id}` - Get backup details
- `POST /api/backups/restore/{id}` - Restore from backup
- `DELETE /api/backups/{id}` - Delete backups
- `GET /api/backups/stats` - View backup statistics

API responses are in JSON format. Authentication may be required for sensitive operations.

## Troubleshooting

- **Permission Issues**: Ensure the web server user has appropriate read/write permissions on the project directory.
- **Database Errors**: Check SQLite database file permissions and ensure the `db/` directory is writable.
- **Upload Failures**: Verify PHP upload limits in your server configuration.
- **Browser Compatibility**: Use a modern browser like Chrome, Firefox, or Edge for best results.

## Contributing

We welcome contributions to improve the project. To contribute:

1. Fork the repository on GitHub.
2. Create a feature branch for your changes.
3. Make your modifications and test thoroughly.
4. Submit a pull request with a clear description of your changes.

Please follow coding standards and include tests where applicable.

## License

Copyright (c) 2025, g023

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

## Author

g023  
X/Twitter: [g023dev](https://x.com/g023dev)  
GitHub: [https://github.com/g023](https://github.com/g023)