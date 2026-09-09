<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | <?= htmlspecialchars(APP_NAME) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= jushsal_logo_data_uri('favicon') ?: asset('img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= jushsal_logo_data_uri('icon') ?: asset('img/logo_icon.png') ?>">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            background: #0f172a !important;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%) !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 440px;
        }
        .card-login-custom {
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #ffffff;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .form-control {
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 0.6rem 0.75rem;
            border-color: #cbd5e1;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-primary {
            background-color: #1e40af;
            border-color: #1e40af;
            border-radius: 6px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        @media (max-width: 576px) {
            .login-box {
                width: 92%;
            }
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-login-custom">
        <div class="card-header text-center py-4 bg-white border-bottom" style="border-color: #f1f5f9 !important;">
            <div class="mb-3">
                <img src="<?= jushsal_logo_data_uri('full') ?: asset('img/logo_jushsal.png') ?>" alt="JUSHSAL" class="img-fluid" style="max-height: 90px; width: auto; object-fit: contain;">
            </div>
            <div class="font-weight-bold text-dark mb-1" style="font-size: 1.05rem; letter-spacing: 0.3px;">
                Control de Personal y Asistencia
            </div>
            <div class="text-muted small">
                Junta de Usuarios San Lorenzo &bull; JUSHSAL
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <p class="text-secondary mb-3 font-weight-bold small text-center">Ingresa tus credenciales autorizadas</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small border-0 shadow-sm" style="border-radius: 6px;">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="?route=login" method="post">
                <?= csrf_field() ?>
                <div class="form-group mb-3">
                    <label class="small font-weight-bold text-dark mb-1">Nombre de Usuario</label>
                    <div class="input-group">
                        <input type="text" name="usuario" class="form-control" placeholder="Ej: admin" required autofocus autocomplete="username">
                        <div class="input-group-append">
                            <span class="input-group-text bg-light border-left-0 text-muted">
                                <i class="fa-solid fa-user"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="small font-weight-bold text-dark mb-1">Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()" title="Mostrar/Ocultar contraseña" style="border-color: #cbd5e1;">
                                <i class="fa-solid fa-eye text-muted" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block py-2">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Iniciar Sesión
                </button>
            </form>

            <div class="mt-4 pt-3 text-center border-top" style="border-color: #f1f5f9 !important;">
                <small class="text-muted font-weight-normal">
                    <i class="fa-solid fa-shield-check mr-1 text-success"></i> Acceso seguro al sistema institucional
                </small>
            </div>
        </div>
    </div>
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
