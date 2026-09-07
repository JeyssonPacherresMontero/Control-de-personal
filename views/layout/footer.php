    </div>
    <!-- /.content-wrapper -->

    <!-- MODAL GLOBAL MI PERFIL Y SEGURIDAD -->
    <div class="modal fade" id="modalMiPerfilGlobal" tabindex="-1" role="dialog" aria-labelledby="modalMiPerfilTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                    <h5 class="modal-title font-weight-bold text-dark mb-0 d-flex align-items-center" id="modalMiPerfilTitle" style="font-size: 1.1rem;">
                        <i class="fa-solid fa-circle-user text-primary mr-2 fa-lg"></i> Mi Perfil y Seguridad
                    </h5>
                    <button type="button" class="close text-secondary" data-dismiss="modal" aria-label="Close" style="outline: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-4 bg-light">
                    <!-- TARJETA DE DATOS DEL USUARIO -->
                    <div class="card mb-3 border bg-white shadow-none" style="border-radius: 8px;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3 font-weight-bold" style="width: 46px; height: 46px; font-size: 1.2rem; flex-shrink: 0;">
                                    <?= strtoupper(substr($currentUser['nombre'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="font-weight-bold text-dark mb-0 text-truncate" style="font-size: 0.98rem;"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></h6>
                                    <div class="d-flex align-items-center mt-1" style="gap: 5px;">
                                        <span class="badge-pill-custom <?= $roleBadgeClass ?>" style="font-size: 0.7rem;"><?= htmlspecialchars($roleLabel) ?></span>
                                        <span class="badge-pill-custom badge-pill-online" style="font-size: 0.7rem;"><i class="fa-solid fa-circle" style="font-size: 5px;"></i> En línea</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row small text-secondary border-top pt-2">
                                <div class="col-6 mb-1">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Nombre de Usuario:</span>
                                    <strong class="text-dark font-monospace"><?= htmlspecialchars($currentUser['usuario'] ?? '') ?></strong>
                                </div>
                                <div class="col-6 mb-1">
                                    <span class="text-muted d-block" style="font-size: 0.75rem;">Institución:</span>
                                    <strong class="text-dark">JUSHSAL</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULARIO DE CAMBIO DE CONTRASEÑA -->
                    <div class="card border bg-white shadow-none mb-0" style="border-radius: 8px;">
                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center">
                            <i class="fa-solid fa-key text-primary mr-2"></i>
                            <span class="font-weight-bold text-dark small text-uppercase" style="letter-spacing: 0.04em;">Cambiar Contraseña de Acceso</span>
                        </div>
                        <div class="card-body p-3">
                            <form id="formCambiarPasswordGlobal" onsubmit="submitCambioPassword(event)">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary mb-1">Contraseña Actual</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="current_password" id="pass_current" class="form-control" placeholder="Ingresa tu contraseña actual" required>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('pass_current', this)">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-secondary mb-1">Nueva Contraseña</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="new_password" id="pass_new" class="form-control" placeholder="Mínimo 5 caracteres" minlength="5" required>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('pass_new', this)">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-secondary mb-1">Confirmar Nueva Contraseña</label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="confirm_password" id="pass_confirm" class="form-control" placeholder="Repite la nueva contraseña" minlength="5" required>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('pass_confirm', this)">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" id="btnGuardarPass" class="btn btn-primary btn-sm btn-block py-2">
                                    <i class="fa-solid fa-floppy-disk mr-1"></i> Actualizar Contraseña
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white d-flex justify-content-between py-2 px-4 border-top">
                    <a href="?route=logout" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Estás seguro de que deseas cerrar tu sesión en el sistema?')">
                        <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar Sesión
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer text-center small text-muted bg-white border-top py-3" style="border-color: #e2e8f0 !important;">
        <strong>
            Copyright &copy; <?= date('Y') ?>
            <a href="?route=dashboard" class="text-primary font-weight-bold">JUSHSAL</a> &bull; Junta de Usuarios del Sector Hidráulico Menor San Lorenzo.
        </strong>
        <span class="d-none d-sm-inline-block ml-2 text-secondary">| Sistema Integral de Control de Personal y Asistencia</span>
    </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- overlayScrollbars -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.4.4/browser/overlayscrollbars.browser.es6.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables & Plugins -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function openMiPerfilModal() {
        $('#formCambiarPasswordGlobal')[0].reset();
        $('#modalMiPerfilGlobal').modal('show');
    }

    function togglePassVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
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

    function submitCambioPassword(e) {
        e.preventDefault();
        const pNew = $('#pass_new').val();
        const pConfirm = $('#pass_confirm').val();

        if (pNew !== pConfirm) {
            Swal.fire({
                icon: 'warning',
                title: 'Contraseñas no coinciden',
                text: 'La nueva contraseña y su confirmación deben ser exactamente iguales.',
                confirmButtonColor: '#1d4ed8'
            });
            return;
        }

        const btn = $('#btnGuardarPass');
        const origText = btn.html();
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i> Actualizando...');

        $.ajax({
            url: '?route=perfil&action=cambiar_password',
            type: 'POST',
            data: $('#formCambiarPasswordGlobal').serialize(),
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html(origText);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Contraseña Actualizada!',
                        text: res.message || 'Tu contraseña se ha cambiado correctamente.',
                        confirmButtonColor: '#1d4ed8'
                    }).then(() => {
                        $('#modalMiPerfilGlobal').modal('hide');
                        $('#formCambiarPasswordGlobal')[0].reset();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.error || 'No se pudo actualizar la contraseña.',
                        confirmButtonColor: '#1d4ed8'
                    });
                }
            },
            error: function() {
                btn.prop('disabled', false).html(origText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Servidor',
                    text: 'Ocurrió un error al procesar la solicitud. Por favor intenta de nuevo.',
                    confirmButtonColor: '#1d4ed8'
                });
            }
        });
    }

    $(document).ready(function () {
        // Limpiar cualquier residuo de modo oscuro previo
        localStorage.removeItem('theme_mode');
        $('body').removeClass('dark-mode');
        $('html').removeClass('dark-mode');

        if ($('.datatable').length) {
            $('.datatable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25,
                "language": {
                    "emptyTable": "No hay registros disponibles",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                    "infoFiltered": "(filtrado de _MAX_ registros en total)",
                    "lengthMenu": "Mostrar _MENU_ registros",
                    "loadingRecords": "Cargando...",
                    "processing": "Procesando...",
                    "search": "Buscar:",
                    "zeroRecords": "No se encontraron coincidencias",
                    "paginate": {
                        "first": "Primero",
                        "last": "Último",
                        "next": "Siguiente",
                        "previous": "Anterior"
                    }
                }
            });
        }
    });
</script>
</body>
</html>
