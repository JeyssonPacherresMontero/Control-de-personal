<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | <?= htmlspecialchars(APP_NAME) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= jushsal_logo_data_uri('favicon') ?: asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .login-box {
            width: 420px;
        }
        @media (max-width: 576px) {
            .login-box {
                width: 92%;
            }
        }
    </style>
</head>
<body class="hold-transition login-page bg-dark" style="background: radial-gradient(circle at center, #1e3a5f 0%, #0f172a 100%);">
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-primary shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header text-center py-4 bg-white border-bottom">
            <div class="mb-3">
                <img src="<?= jushsal_logo_data_uri('full') ?: asset('img/logo_jushsal.png') ?>" alt="JUSHSAL" class="img-fluid" style="max-height: 125px; width: auto; object-fit: contain;">
            </div>
            <div class="badge badge-primary px-3 py-1 font-weight-normal" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-fingerprint mr-1"></i> Control de Personal y Asistencia
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <p class="login-box-msg text-secondary pt-0 mb-3 font-weight-bold">Ingresa tus credenciales para acceder</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="?route=login" method="post">
                <div class="input-group mb-3">
                    <input type="text" name="usuario" class="form-control" placeholder="Nombre de usuario" required autofocus autocomplete="username">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Contraseña" required autocomplete="current-password">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()" title="Mostrar/Ocultar contraseña" style="border-color: #ced4da;">
                            <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i> Iniciar Sesión
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-4 pt-3 text-center border-top">
                <small class="text-muted">
                    <i class="fa-solid fa-shield-halved mr-1"></i> Acceso seguro al sistema de control de asistencia
                </small>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
<!-- /.login-box -->

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('loginPassword');
    const icon = document.getElementById('togglePasswordIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
