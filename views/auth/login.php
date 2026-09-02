<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | <?= htmlspecialchars(APP_NAME) ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page bg-dark" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-primary shadow-lg border-0">
        <div class="card-header text-center py-4">
            <div class="mb-2">
                <i class="fa-solid fa-fingerprint fa-3x text-primary"></i>
            </div>
            <a href="#" class="h3 font-weight-bold text-dark text-decoration-none"><b>ZK-Control</b> Personal</a>
            <div class="text-muted small">Sistema de Asistencia en Tiempo Real</div>
        </div>
        <div class="card-body">
            <p class="login-box-msg text-secondary">Ingresa tus credenciales para acceder</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="?route=login" method="post">
                <div class="input-group mb-3">
                    <input type="text" name="usuario" class="form-control" placeholder="Usuario (admin)" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Contraseña (admin123)" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i> Acceder al Panel
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-4 pt-3 border-top">
                <div class="text-secondary small font-weight-bold mb-2 text-center">
                    <i class="fa-solid fa-users-gear mr-1"></i> Cuentas de Demostración por Rol:
                </div>
                <div class="table-responsive">
                    <table class="table table-xs table-borderless small mb-0 text-muted">
                        <tbody>
                            <tr>
                                <td><span class="badge badge-danger">ADMIN</span></td>
                                <td><code>admin</code></td>
                                <td class="text-right"><button type="button" class="btn btn-xs btn-outline-secondary py-0" onclick="setCreds('admin','admin123')">Usar</button></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-primary">RRHH</span></td>
                                <td><code>rrhh</code></td>
                                <td class="text-right"><button type="button" class="btn btn-xs btn-outline-secondary py-0" onclick="setCreds('rrhh','admin123')">Usar</button></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-info">SUPERVISOR</span></td>
                                <td><code>supervisor</code></td>
                                <td class="text-right"><button type="button" class="btn btn-xs btn-outline-secondary py-0" onclick="setCreds('supervisor','admin123')">Usar</button></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-secondary">CONSULTA</span></td>
                                <td><code>consulta</code></td>
                                <td class="text-right"><button type="button" class="btn btn-xs btn-outline-secondary py-0" onclick="setCreds('consulta','admin123')">Usar</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center text-muted small mt-2">
                    <small>Clave para todos: <code>admin123</code></small>
                </div>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
<!-- /.login-box -->

<script>
function setCreds(user, pass) {
    document.querySelector('input[name="usuario"]').value = user;
    document.querySelector('input[name="password"]').value = pass;
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
