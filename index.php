<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (for file icon) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(to right, #e0f7fa, #ffffff);
        }
        .upload-box {
            border: 2px dashed #6c757d;
            border-radius: 10px;
            padding: 40px;
            background-color: #f8f9fa;
            text-align: center;
            transition: all 0.3s ease-in-out;
        }
        .upload-box:hover {
            background-color: #e2f0ff;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center">
                    <h3><i class="bi bi-upload"></i> Attendance Report Generator</h3>
                    <p class="mb-0">Upload your fingerprint attendance file below</p>
                </div>
                <div class="card-body">

                    <!-- Upload Form -->
                    <form action="upload.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="upload-box mb-4">
                            <i class="bi bi-file-earmark-arrow-up" style="font-size: 3rem; color: #0d6efd;"></i>
                            <p class="mt-2">Drag or select a <code>.dat</code> or <code>.pdf</code> file</p>
                            <input type="file" class="form-control mt-3"
       name="fileToUpload[]"
       id="fileToUpload"
       accept=".dat,.pdf"
       multiple
       required>
<div class="invalid-feedback">
                                Please select a .dat or .pdf file.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-cloud-arrow-up-fill"></i> Upload Attendance File
                        </button>
                    </form>

                </div>
                <div class="card-footer text-muted text-center small">
                    Supported formats: <strong>.dat</strong>, <strong>.pdf</strong> | Max file size: <em>5MB</em>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS & Form Validation Script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Enable Bootstrap validation UI
    (function () {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

</body>
</html>
