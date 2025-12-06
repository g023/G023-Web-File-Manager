Dropzone.autoDiscover = false;

$(document).ready(function() {
    // Load tree state first
    $.get('api/state.php?key=tree_state', function(savedState) {
        initTree(savedState);
        // Initial load of root if no selection
        // We can't easily check if jstree has selection here because it's async.
        // But we can just load root list.
        loadFileList('/');
    });

    // Search functionality
    var to = false;
    $('#tree-search').keyup(function () {
        if(to) { clearTimeout(to); }
        to = setTimeout(function () {
            var v = $('#tree-search').val();
            $('#file-tree').jstree(true).search(v);
        }, 250);
    });

    // Event handlers
    $('#file-tree').on('select_node.jstree', function (e, data) {
        var node = data.node;
        if (node.original.type === 'directory') {
            loadFileList(node.original.path);
        } else {
            previewFile(node.original.path);
        }
    });
    
    // Save state on changes
    $('#file-tree').on('state_ready.jstree open_node.jstree close_node.jstree', function () {
        var state = $('#file-tree').jstree(true).get_state();
        $.post('api/state.php', { key: 'tree_state', value: state });
    });

    // Handle Drag and Drop Move
    $('#file-tree').on('move_node.jstree', function (e, data) {
        var from = data.node.original.path;
        // data.parent is the ID of the new parent.
        // If parent is '#', it means root.
        // We need to get the path of the parent node.
        var parentNode = $('#file-tree').jstree(true).get_node(data.parent);
        var toPath = (data.parent === '#' || !parentNode.original) ? '/' : parentNode.original.path;
        
        // The 'to' in moveFile API is the full destination path including filename.
        var filename = data.node.text;
        // Ensure toPath doesn't end with / unless it is just /
        if (toPath !== '/' && toPath.endsWith('/')) {
            toPath = toPath.slice(0, -1);
        }
        var destination = (toPath === '/' ? '' : toPath) + '/' + filename;
        
        console.log('Moving from ' + from + ' to ' + destination);

        $.post('api/files.php', { action: 'move', from: from, to: destination }, function(res) {
            if (res.success) {
                toastr.success('Moved successfully');
                // Update the node's original path so future operations work
                data.node.original.path = destination;
            } else {
                toastr.error('Failed to move');
                // Revert
                data.instance.refresh();
            }
        });
    });

    // Toolbar Buttons
    $('#btn-upload').click(function() {
        var path = getCurrentPath();
        // We can reuse the showUploadDialog but pass a dummy node object
        showUploadDialog({ original: { path: path } });
    });

    $('#btn-new-folder').click(function() {
        var path = getCurrentPath();
        createFolder({ original: { path: path } });
    });

    $('#btn-new-file').click(function() {
        var path = getCurrentPath();
        createFile({ original: { path: path } });
    });

    $('#btn-save').click(function() {
        if (editor && currentFilePath) {
            saveFile(currentFilePath, editor.getValue());
        }
    });

    $('#btn-close-editor').click(function() {
        var path = currentFilePath;
        var parentPath = path.substring(0, path.lastIndexOf('/'));
        if (parentPath === '') parentPath = '/';
        loadFileList(parentPath);
    });

    // Global Drag and Drop
    setupDragAndDrop();
});

var clipboard = null;
var currentFilePath = null;

function updateToolbar(mode) {
    if (mode === 'editor') {
        $('#browser-toolbar').addClass('d-none');
        $('#editor-toolbar').removeClass('d-none');
    } else {
        $('#browser-toolbar').removeClass('d-none');
        $('#editor-toolbar').addClass('d-none');
    }
}

function getCurrentPath() {
    return $('#breadcrumb li:last').text();
}

function setupDragAndDrop() {
    var dropZone = document.body;

    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        // Visual feedback could be added here
    });

    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var files = e.dataTransfer.files;
        if (files.length === 0) return;

        // Determine drop target
        var target = e.target;
        var destinationPath = null;
        var isEditorDrop = false;

        // Check if dropped on editor
        if ($('#editor-container').is(':visible') && $.contains(document.getElementById('editor-container'), target)) {
            isEditorDrop = true;
        }

        if (isEditorDrop) {
            if (files.length > 1) {
                toastr.warning('Please drop only one file into the editor.');
                return;
            }
            var file = files[0];
            if (confirm('Replace editor content with ' + file.name + '?')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    if (editor) {
                        editor.setValue(e.target.result);
                    }
                };
                reader.readAsText(file);
            }
            return;
        }

        // Determine destination path for upload
        // Check if dropped on a folder card in file list
        var card = $(target).closest('.file-card');
        if (card.length > 0 && card.data('type') === 'directory') {
            destinationPath = card.data('path');
        } else if ($('#file-list').is(':visible') && $.contains(document.getElementById('file-list'), target)) {
            // Dropped on file list background -> current folder
            destinationPath = getCurrentPath();
        } else if ($(target).closest('.jstree-node').length > 0) {
            // Dropped on tree node
            var nodeId = $(target).closest('.jstree-node').attr('id');
            var node = $('#file-tree').jstree(true).get_node(nodeId);
            if (node.original.type === 'directory') {
                destinationPath = node.original.path;
            } else {
                // Dropped on a file in tree -> upload to parent folder
                var parentId = $('#file-tree').jstree(true).get_parent(node);
                var parentNode = $('#file-tree').jstree(true).get_node(parentId);
                destinationPath = (parentId === '#' || !parentNode.original) ? '/' : parentNode.original.path;
            }
        } else {
            // Default to current path if visible
            if ($('#file-list').is(':visible')) {
                destinationPath = getCurrentPath();
            }
        }

        if (destinationPath) {
            handleFileUploads(files, destinationPath);
        }
    });
}

function handleFileUploads(files, path) {
    // Process files sequentially or parallel
    Array.from(files).forEach(function(file) {
        uploadFile(file, path);
    });
}

function uploadFile(file, path) {
    // Check if file exists
    var fullPath = (path === '/' ? '' : path) + '/' + file.name;
    
    // We need a way to check existence synchronously or just ask user.
    // Since we can't block easily in loop, let's just assume we warn.
    // But for better UX, we should check.
    // Let's use a simple check via API first.
    
    // Actually, let's just upload and let the server handle it? 
    // User asked to "Warn before replacing files with same name".
    
    // We can do a quick check.
    $.get('api/files.php?action=list&path=' + encodeURIComponent(path), function(filesInDir) {
        var filesArray = filesInDir.files || filesInDir;
        var exists = Array.isArray(filesArray) ? filesArray.find(f => f.name === file.name) : null;
        if (exists) {
            if (!confirm('File ' + file.name + ' already exists in ' + path + '. Overwrite?')) {
                return;
            }
        }
        
        performUpload(file, path);
    });
}

function performUpload(file, path) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('path', path);

    // Create a unique ID for this upload toast
    var toastId = 'upload-' + Date.now();
    
    toastr.info(
        '<div class="progress mt-2" style="height: 5px;">' +
        '<div id="' + toastId + '" class="progress-bar" role="progressbar" style="width: 0%;"></div>' +
        '</div>', 
        'Uploading ' + file.name, 
        { 
            timeOut: 0, 
            extendedTimeOut: 0, 
            tapToDismiss: false, 
            closeButton: false 
        }
    );

    $.ajax({
        url: 'api/upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener("progress", function(evt) {
                if (evt.lengthComputable) {
                    var percentComplete = (evt.loaded / evt.total) * 100;
                    $('#' + toastId).css('width', percentComplete + '%');
                }
            }, false);
            return xhr;
        },
        success: function(response) {
            toastr.clear(); // Clear the progress toast
            toastr.success(file.name + ' uploaded');
            
            // Refresh view if needed
            if (getCurrentPath() === path) {
                loadFileList(path);
            }
            var nodeId = path === '/' ? '#' : path;
            $('#file-tree').jstree(true).refresh_node(nodeId);
        },
        error: function() {
            toastr.clear();
            toastr.error('Failed to upload ' + file.name);
        }
    });
}

function initTree(savedState) {
    $('#file-tree').jstree({
        'core': {
            'data': {
                'url': 'api/files.php?action=tree',
                'data': function (node) {
                    return { 'id': node.id, 'path': node.original ? node.original.path : '' };
                }
            },
            'check_callback': function (operation, node, node_parent, node_position, more) {
                if (operation === 'move_node') {
                    // Prevent dropping into a file
                    // node_parent is the target parent.
                    // If node_parent is a file, return false.
                    // Note: jstree root is '#', which has no original property.
                    if (node_parent.id !== '#' && node_parent.original && node_parent.original.type === 'file') {
                        return false;
                    }
                }
                return true;
            },
            'themes': {
                'name': 'default',
                'responsive': true
            }
        },
        'plugins': ['dnd', 'search', 'contextmenu', 'state', 'types'],
        'search': {
            'show_only_matches': false,
            'ajax': {
                'url': 'api/files.php?action=search',
                'data': function(str) {
                    return { 'q': str };
                }
            }
        },
        'state': {
            'key': 'file-tree-state',
            'ttl': 86400
        },
        'types': {
            'default': { 'icon': 'fas fa-file' },
            'directory': { 'icon': 'fas fa-folder text-warning' },
            'file': { 'icon': 'fas fa-file text-secondary' }
        },
        'contextmenu': {
            'items': function (node) {
                var items = {
                    'create': {
                        'label': 'New Folder',
                        'action': function () { createFolder(node); }
                    },
                    'create_file': {
                        'label': 'New File',
                        'action': function () { createFile(node); }
                    },
                    'rename': {
                        'label': 'Rename',
                        'action': function () { renameNode(node); }
                    },
                    'copy': {
                        'label': 'Copy',
                        'action': function () { copyNode(node); }
                    },
                    'paste': {
                        'label': 'Paste',
                        'action': function () { pasteNode(node); },
                        '_disabled': function () { return clipboard === null; }
                    },
                    'upload': {
                        'label': 'Upload',
                        'action': function () { showUploadDialog(node); }
                    },
                    'download': {
                        'label': 'Download as ZIP',
                        'action': function () { downloadAsZip(node); }
                    },
                    'restore': {
                        'label': 'View Backups',
                        'action': function () { showBackupDialog(node); }
                    },
                    'delete': {
                        'label': 'Delete',
                        'action': function () { deleteNode(node); }
                    }
                };
                
                if (node.original.type !== 'directory') {
                    delete items.create;
                    delete items.create_file;
                    delete items.upload;
                    delete items.paste;
                }
                
                return items;
            }
        }
    }).on('ready.jstree', function () {
        if (savedState) {
            $('#file-tree').jstree(true).set_state(savedState);
        }
    });
}

function copyNode(node) {
    clipboard = {
        path: node.original.path,
        name: node.text,
        type: node.original.type
    };
    toastr.info('Copied to clipboard');
}

function pasteNode(node) {
    if (!clipboard) return;
    
    var destDir = node.original.path;
    var destPath = (destDir === '/' ? '' : destDir) + '/' + clipboard.name;
    
    $.post('api/files.php', { action: 'copy', from: clipboard.path, to: destPath }, function(res) {
        if (res.success) {
            $('#file-tree').jstree(true).refresh();
            toastr.success('Pasted successfully');
            clipboard = null; // Clear clipboard after paste? Or keep it? Standard is keep.
            // But if we keep it, we can paste multiple times.
        } else {
            toastr.error('Failed to paste');
        }
    });
}

function loadFileList(path) {
    console.log('Loading path: ' + path);
    
    updateToolbar('browser');

    // Hide editor/preview, show list
    $('#editor-container').addClass('d-none');
    $('#file-preview').addClass('d-none');
    $('#file-list').removeClass('d-none');

    $.get('api/files.php?action=list', { path: path }, function(response) {
        // Update breadcrumb with the real path returned from server
        if (response.path) {
            $('#breadcrumb').html('<li class="breadcrumb-item active">' + response.path + '</li>');
        } else {
            $('#breadcrumb').html('<li class="breadcrumb-item active">' + path + '</li>');
        }
        
        renderFileList(response.files || response);
    });
}

function renderFileList(files) {
    if (!Array.isArray(files)) {
        console.error('renderFileList: files is not an array', files);
        files = [];
    }
    
    var list = $('#file-list');
    list.empty();
    
    if (!files || files.length === 0) {
        list.html('<div class="col-12 text-center text-muted mt-5"><h4>Folder is empty</h4></div>');
        return;
    }

    files.forEach(function(file) {
        var icon = file.type === 'directory' ? 'fa-folder text-warning' : 'fa-file text-secondary';
        // Simple extension check for icon
        if (file.type === 'file') {
            var ext = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) icon = 'fa-image text-primary';
            else if (['pdf'].includes(ext)) icon = 'fa-file-pdf text-danger';
            else if (['zip', 'rar', '7z'].includes(ext)) icon = 'fa-file-archive text-warning';
            else if (['js', 'css', 'html', 'php', 'py'].includes(ext)) icon = 'fa-file-code text-info';
        }

        var html = `
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100 file-card shadow-sm" data-path="${file.path}" data-type="${file.type}" style="cursor: pointer;">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <i class="fas ${icon} fa-3x mb-3"></i>
                        <h6 class="card-title text-truncate w-100" title="${file.name}">${file.name}</h6>
                        <small class="text-muted">${formatSize(file.size)}</small>
                    </div>
                </div>
            </div>
        `;
        list.append(html);
    });

    // Add click handlers for cards
    $('.file-card').on('click', function() {
        var path = $(this).data('path');
        var type = $(this).data('type');
        if (type === 'directory') {
            loadFileList(path);
        } else {
            previewFile(path);
        }
    });
}

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function createFolder(node) {
    var name = prompt('Enter folder name:');
    if (name) {
        var parentPath = node.original.path ? node.original.path : '/';
        var path = (parentPath === '/' ? '' : parentPath) + '/' + name;
        $.post('api/files.php', { action: 'create', path: path, type: 'directory' }, function(res) {
            if (res.success) {
                var nodeId = parentPath === '/' ? '#' : parentPath;
                $('#file-tree').jstree(true).refresh_node(nodeId);
                
                if (getCurrentPath() === parentPath) {
                    loadFileList(parentPath);
                }
                toastr.success('Folder created');
            } else {
                toastr.error('Failed to create folder');
            }
        });
    }
}

function createFile(node) {
    var name = prompt('Enter file name:');
    if (name) {
        var parentPath = node.original.path ? node.original.path : '/';
        var path = (parentPath === '/' ? '' : parentPath) + '/' + name;
        $.post('api/files.php', { action: 'create', path: path, type: 'file' }, function(res) {
            if (res.success) {
                var nodeId = parentPath === '/' ? '#' : parentPath;
                $('#file-tree').jstree(true).refresh_node(nodeId);
                
                if (getCurrentPath() === parentPath) {
                    loadFileList(parentPath);
                }
                toastr.success('File created');
            } else {
                toastr.error('Failed to create file');
            }
        });
    }
}

function renameNode(node) {
    var name = prompt('Enter new name:', node.text);
    if (name && name !== node.text) {
        var oldPath = node.original.path;
        // Handle root path case or parent path extraction
        var parentPath = oldPath.substring(0, oldPath.lastIndexOf('/'));
        if (parentPath === '') parentPath = '/'; // If file is at root
        
        // If parentPath is empty string (because oldPath was like "/file.txt"), it becomes ""
        // We need to be careful with paths.
        // If oldPath is "E:/wamp64/www/g023websitemanager/file.txt"
        // lastIndexOf('/') will be before file.txt.
        
        var newPath = parentPath + (parentPath === '/' ? '' : '/') + name;
        
        $.post('api/files.php', { action: 'rename', from: oldPath, to: newPath }, function(res) {
            if (res.success) {
                $('#file-tree').jstree(true).refresh();
                toastr.success('Renamed successfully');
            } else {
                toastr.error('Failed to rename');
            }
        });
    }
}

function showUploadDialog(node) {
    var path = node.original.path;
    $('#uploadModal').modal('show');
    
    // Initialize Dropzone if not already
    if (Dropzone.instances.length > 0) {
        Dropzone.instances.forEach(dz => dz.destroy());
    }
    
    var myDropzone = new Dropzone('#file-upload-dropzone', { 
        url: 'api/upload.php',
        params: { path: path },
        init: function() {
            this.on('complete', function(file) {
                if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
                    setTimeout(function() {
                        $('#uploadModal').modal('hide');
                        $('#file-tree').jstree(true).refresh();
                        if ($('#breadcrumb li:last').text() === path) {
                            loadFileList(path);
                        }
                        toastr.success('Upload complete');
                    }, 1000);
                }
            });
        }
    });
}

function downloadAsZip(node) {
    var path = node.original.path;
    window.location.href = 'api/download.php?path=' + encodeURIComponent(path);
}

function deleteNode(node) {
    if (confirm('Are you sure you want to delete ' + node.text + '?')) {
        $.post('api/files.php', { action: 'delete', path: node.original.path }, function(res) {
            if (res.success) {
                var parentId = $('#file-tree').jstree(true).get_parent(node);
                $('#file-tree').jstree(true).refresh_node(parentId);
                
                var current = getCurrentPath();
                // Check if we deleted the current folder
                if (current === node.original.path) {
                    var parentPath = node.original.path.substring(0, node.original.path.lastIndexOf('/'));
                    if (parentPath === '') parentPath = '/';
                    loadFileList(parentPath);
                } else {
                    // Check if we deleted a file/folder in the current view
                    var parentPath = node.original.path.substring(0, node.original.path.lastIndexOf('/'));
                    if (parentPath === '') parentPath = '/';
                    if (current === parentPath) {
                        loadFileList(current);
                    }
                }
                
                toastr.success('Deleted successfully');
            } else {
                toastr.error('Failed to delete');
            }
        });
    }
}

function previewFile(path) {
    console.log('Previewing ' + path);
    currentFilePath = path;
    var ext = path.split('.').pop().toLowerCase();
    
    updateToolbar('editor');

    // Hide list
    $('#file-list').addClass('d-none');
    
    if (['jpg', 'jpeg', 'png', 'gif', 'svg'].includes(ext)) {
        $('#editor-container').addClass('d-none');
        $('#file-preview').removeClass('d-none').html(`<img src="api/files.php?action=raw&path=${encodeURIComponent(path)}" class="img-fluid" style="max-height: 100%;">`);
    } else if (ext === 'pdf') {
        $('#editor-container').addClass('d-none');
        $('#file-preview').removeClass('d-none').html(`<iframe src="api/files.php?action=raw&path=${encodeURIComponent(path)}" width="100%" height="100%" style="border:none;"></iframe>`);
    } else if (['txt', 'md', 'js', 'css', 'html', 'php', 'py', 'json', 'sql', 'xml', 'yml', 'ini', 'conf', 'sh', 'bat'].includes(ext)) {
        $('#file-preview').addClass('d-none');
        $('#editor-container').removeClass('d-none');
        
        $.get('api/files.php?action=content', { path: path }, function(res) {
            if (res.content !== undefined) {
                initEditor(path, res.content, getLanguageFromExtension(ext));
            } else {
                toastr.error('Failed to load file content');
            }
        });
    } else {
        $('#editor-container').addClass('d-none');
        $('#file-preview').removeClass('d-none').html('<div class="text-center text-muted"><h3>Cannot preview this file type</h3></div>');
    }
}

var editor = null;

function initEditor(path, content, language) {
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});
    
    require(['vs/editor/editor.main'], function() {
        if (editor) {
            editor.dispose();
        }
        
        // Load editor state
        $.get('api/state.php?key=editor_settings', function(settings) {
            settings = settings || { theme: 'vs-dark', wordWrap: 'on', minimap: true };
            
            editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: content,
                language: language,
                theme: settings.theme,
                automaticLayout: true,
                minimap: { enabled: settings.minimap },
                wordWrap: settings.wordWrap
            });
            
            // Save command (Ctrl+S)
            editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
                saveFile(path, editor.getValue());
            });
        });
    });
}

function saveFile(path, content) {
    $.post('api/files.php', { action: 'save', path: path, content: content }, function(res) {
        if (res.success) {
            toastr.success('File saved successfully');
        } else {
            toastr.error('Failed to save file');
        }
    });
}

function getLanguageFromExtension(ext) {
    var map = {
        'js': 'javascript', 'py': 'python', 'php': 'php', 'html': 'html', 
        'css': 'css', 'json': 'json', 'md': 'markdown', 'sql': 'sql',
        'xml': 'xml', 'yml': 'yaml', 'sh': 'shell', 'bat': 'bat'
    };
    return map[ext] || 'plaintext';
}

function showBackupDialog(node) {
    var path = node.original.path;
    $('#backupModal').modal('show');
    $('#backupModal .modal-title').text('Backups: ' + node.text);
    
    loadBackups(path);
}

function loadBackups(path) {
    var tbody = $('#backup-table tbody');
    tbody.html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
    
    $.get('api/backups.php', { path: path }, function(backups) {
        tbody.empty();
        if (backups.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center">No backups found</td></tr>');
            return;
        }
        
        backups.forEach(function(backup) {
            var date = new Date(backup.created_at).toLocaleString();
            var html = `
                <tr>
                    <td>${backup.version}</td>
                    <td>${date}</td>
                    <td><span class="badge bg-${getOperationColor(backup.operation)}">${backup.operation}</span></td>
                    <td>${formatSize(backup.original_size)}</td>
                    <td>
                        <button class="btn btn-sm btn-warning btn-restore" data-id="${backup.id}" title="Restore"><i class="fas fa-undo"></i></button>
                        <button class="btn btn-sm btn-danger btn-delete-backup" data-id="${backup.id}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.append(html);
        });
        
        $('.btn-restore').click(function() {
            var id = $(this).data('id');
            if (confirm('Are you sure you want to restore this version? Current file will be overwritten (and backed up).')) {
                $.post('api/backups.php?action=restore', { id: id }, function(res) {
                    if (res.success) {
                        toastr.success('File restored successfully');
                        loadBackups(path); // Reload backups as a new one might be created
                        // Refresh editor if open
                        if (editor && !$('#editor-container').hasClass('d-none')) {
                            previewFile(path);
                        }
                    } else {
                        toastr.error('Failed to restore file');
                    }
                });
            }
        });
        
        $('.btn-delete-backup').click(function() {
            var id = $(this).data('id');
            if (confirm('Delete this backup?')) {
                $.ajax({
                    url: 'api/backups.php?id=' + id,
                    type: 'DELETE',
                    success: function(res) {
                        if (res.success) {
                            toastr.success('Backup deleted');
                            loadBackups(path);
                        } else {
                            toastr.error('Failed to delete backup');
                        }
                    }
                });
            }
        });
    });
}

function getOperationColor(op) {
    switch(op) {
        case 'edit': return 'primary';
        case 'delete': return 'danger';
        case 'move': return 'warning';
        case 'rename': return 'info';
        default: return 'secondary';
    }
}
