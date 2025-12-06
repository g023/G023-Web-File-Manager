<?php include __DIR__ . '/header.php'; ?>

<div class="container-fluid h-100 vh-100 d-flex flex-column p-0">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="#"><i class="fas fa-folder-open me-2"></i><?php echo $config['app_name']; ?></a>
  </nav>

  <div class="row g-0 flex-grow-1 flex-column flex-md-row overflow-auto overflow-md-hidden" id="layout-row">
    <!-- Sidebar with tree navigation -->
    <div class="col-md-3 col-xl-2 border-end bg-light d-flex flex-column p-0 h-100" id="sidebar">
      <div class="p-3 border-bottom">
        <input type="text" id="tree-search" class="form-control" placeholder="Search folders...">
      </div>
      <div class="flex-grow-1 overflow-auto p-2">
        <div id="file-tree"></div>
      </div>
    </div>
    
    <!-- Main content area -->
    <div class="col-md-9 col-xl-10 d-flex flex-column p-0" id="main-content">
      <!-- Toolbar & Breadcrumb -->
      <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0" id="breadcrumb">
            <li class="breadcrumb-item active">/</li>
          </ol>
        </nav>
        <div id="toolbar">
            <div id="browser-toolbar">
                <button class="btn btn-sm btn-primary" id="btn-upload"><i class="fas fa-upload"></i> Upload</button>
                <button class="btn btn-sm btn-success" id="btn-new-folder"><i class="fas fa-folder-plus"></i> New Folder</button>
                <button class="btn btn-sm btn-secondary" id="btn-new-file"><i class="fas fa-file-plus"></i> New File</button>
            </div>
            <div id="editor-toolbar" class="d-none">
                <button class="btn btn-sm btn-primary" id="btn-save"><i class="fas fa-save"></i> Save</button>
                <button class="btn btn-sm btn-secondary" id="btn-close-editor"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
      </div>
      
      <!-- File List / Editor / Preview -->
      <div class="flex-grow-1 overflow-auto p-3 bg-light position-relative" id="content-area">
        <div id="file-list" class="row g-3"></div>
        <div id="editor-container" class="d-none h-100 w-100"></div>
        <div id="file-preview" class="d-none h-100 w-100 d-flex justify-content-center align-items-center"></div>
      </div>
    </div>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload Files</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="dropzone" id="file-upload-dropzone"></div>
      </div>
    </div>
  </div>
</div>

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">File Backups</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="backup-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Date</th>
                        <th>Operation</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
