(function () {
    var modalTriggers = document.querySelectorAll('[data-open-modal]');
    var modalCloseButtons = document.querySelectorAll('[data-close-modal]');
    var editButtons = document.querySelectorAll('.open-edit-area');
    var editVideoButtons = document.querySelectorAll('.open-edit-video');
    var editAreaKey = document.getElementById('edit_area_key');
    var editAreaKeyView = document.getElementById('edit_area_key_view');
    var editAreaLabel = document.getElementById('edit_area_label');
    var editAreaCode = document.getElementById('edit_area_code');
    var editAreaActive = document.getElementById('edit_area_active');
    var deleteAreaKey = document.getElementById('delete_area_key');
    var deleteAreaButton = document.getElementById('deleteAreaButton');
    var editAreaHint = document.getElementById('editAreaHint');
    var statusToggles = document.querySelectorAll('.js-status-toggle');
    var confirmButtons = document.querySelectorAll('[data-confirm]');
    var editVideoId = document.getElementById('edit_video_id');
    var deleteVideoId = document.getElementById('delete_video_id');
    var editVideoTitleInput = document.getElementById('edit_video_title_input');
    var editVideoContext = document.getElementById('editVideoContext');
    var toastStack = document.getElementById('toastStack');
    var serverToastError = document.getElementById('serverToastError');
    var serverToastSuccess = document.getElementById('serverToastSuccess');
    var lastTrigger = null;

    var form = document.getElementById('chunkUploadForm');
    var progressBar = document.getElementById('uploadProgressBar');
    var progressText = document.getElementById('uploadProgressText');

    function showToast(message, kind, durationMs) {
        if (!message) {
            return;
        }

        if (!toastStack) {
            window.alert(message);
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'toast-item' + (kind === 'success' ? ' toast-success' : ' toast-error');
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        toastStack.appendChild(toast);

        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        var ttl = typeof durationMs === 'number' && durationMs > 0 ? durationMs : 4000;
        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, ttl);
    }

    if (serverToastError) {
        showToast(serverToastError.getAttribute('data-toast-message') || '', 'error', 5200);
    }
    if (serverToastSuccess) {
        showToast(serverToastSuccess.getAttribute('data-toast-message') || '', 'success', 4200);
    }

    statusToggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            if (toggle.form) {
                toggle.form.submit();
            }
        });
    });

    function openModalById(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        document.querySelectorAll('.modal:not(.hidden)').forEach(function (openModal) {
            openModal.classList.add('hidden');
        });

        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');

        var modalCard = modal.querySelector('.modal-card');
        if (modalCard) {
            modalCard.scrollTop = 0;
        }

        var firstFocusable = modal.querySelector('input:not([type="hidden"]), select, textarea, button:not([disabled])');
        if (firstFocusable) {
            window.setTimeout(function () {
                firstFocusable.focus();
            }, 0);
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');

        var openModals = document.querySelectorAll('.modal:not(.hidden)');
        if (openModals.length === 0) {
            document.body.classList.remove('modal-open');

            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                window.setTimeout(function () {
                    lastTrigger.focus();
                }, 0);
            }
        }
    }

    modalTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modalId = trigger.getAttribute('data-open-modal');
            if (!modalId) {
                return;
            }
            lastTrigger = trigger;
            openModalById(modalId);
        });
    });

    modalCloseButtons.forEach(function (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeModal(closeBtn.closest('.modal'));
        });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var openModals = document.querySelectorAll('.modal:not(.hidden)');
        if (openModals.length === 0) {
            return;
        }

        closeModal(openModals[openModals.length - 1]);
    });

    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            var message = button.getAttribute('data-confirm') || 'Confirmar eliminacion?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var areaKey = btn.getAttribute('data-area-key') || '';
            var areaLabel = btn.getAttribute('data-area-label') || '';
            var isActive = btn.getAttribute('data-area-active') === '1';
            var isSystems = btn.getAttribute('data-area-systems') === '1';
            var hasVideos = btn.getAttribute('data-area-has-videos') === '1';

            if (editAreaKey) {
                editAreaKey.value = areaKey;
            }
            if (editAreaKeyView) {
                editAreaKeyView.value = areaKey;
            }
            if (editAreaLabel) {
                editAreaLabel.value = areaLabel;
            }
            if (editAreaCode) {
                editAreaCode.value = '';
            }
            if (editAreaActive) {
                editAreaActive.checked = isActive;
                editAreaActive.disabled = isSystems;
            }
            if (deleteAreaKey) {
                deleteAreaKey.value = areaKey;
            }
            if (deleteAreaButton) {
                deleteAreaButton.classList.toggle('hidden', isSystems || hasVideos);
            }
            if (editAreaHint) {
                if (isSystems) {
                    editAreaHint.textContent = 'El area systems solo permite editar nombre/codigo.';
                } else if (hasVideos) {
                    editAreaHint.textContent = 'No se puede eliminar un area con videos registrados.';
                } else {
                    editAreaHint.textContent = 'Puedes actualizar o eliminar esta area.';
                }
            }
        });
    });

    editVideoButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var videoId = btn.getAttribute('data-video-id') || '';
            var videoTitle = btn.getAttribute('data-video-title') || '';
            var videoArea = btn.getAttribute('data-video-area') || '';

            if (editVideoId) {
                editVideoId.value = videoId;
            }
            if (deleteVideoId) {
                deleteVideoId.value = videoId;
            }
            if (editVideoTitleInput) {
                editVideoTitleInput.value = videoTitle;
            }
            if (editVideoContext) {
                editVideoContext.textContent = 'Area: ' + videoArea;
            }
        });
    });

    if (!form || !progressBar || !progressText) {
        return;
    }

    function setStatus(message, kind) {
        showToast(message, kind === 'success' ? 'success' : 'error', kind === 'success' ? 3800 : 5200);
    }

    function resetStatus() {
        // Floating toasts auto-close, no inline status reset required.
    }

    function updateProgress(percent, message) {
        progressBar.style.width = percent + '%';
        progressText.textContent = message;
    }

    function buildUploadId() {
        var random = Math.random().toString(36).slice(2);
        var timestamp = Date.now().toString(36);
        return (timestamp + random + random).slice(0, 32);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        resetStatus();

        var areaInput = document.getElementById('area');
        var titleInput = document.getElementById('title');
        var fileInput = document.getElementById('video_file');

        if (!areaInput || !titleInput || !fileInput || !fileInput.files || fileInput.files.length === 0) {
            setStatus('Selecciona un area y un archivo.', 'error');
            return;
        }

        var area = areaInput.value;
        var title = titleInput.value;
        var file = fileInput.files[0];
        var extension = (file.name.split('.').pop() || '').toLowerCase();
        var allowed = ['mp4', 'webm', 'ogg'];

        if (allowed.indexOf(extension) === -1) {
            setStatus('Formato no permitido. Solo mp4, webm y ogg.', 'error');
            return;
        }

        var chunkSize = 10 * 1024 * 1024;
        var totalChunks = Math.ceil(file.size / chunkSize);
        var uploadId = buildUploadId();

        if (totalChunks < 1) {
            setStatus('Archivo invalido.', 'error');
            return;
        }

        var submitButton = form.querySelector('button[type="submit"]');
        var csrfInput = form.querySelector('input[name="csrf_token"]');
        var csrfToken = csrfInput ? csrfInput.value : '';
        if (submitButton) {
            submitButton.disabled = true;
        }

        updateProgress(0, 'Preparando subida...');

        try {
            for (var index = 0; index < totalChunks; index++) {
                var start = index * chunkSize;
                var end = Math.min(start + chunkSize, file.size);
                var chunk = file.slice(start, end);
                var payload = new FormData();

                payload.append('area', area);
                payload.append('title', title);
                payload.append('upload_id', uploadId);
                payload.append('chunk_index', String(index));
                payload.append('total_chunks', String(totalChunks));
                payload.append('total_size', String(file.size));
                payload.append('original_name', file.name);
                payload.append('chunk', chunk, 'chunk.part');

                var response = await fetch('upload_chunk.php', {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'X-CSRF-Token': csrfToken,
                    },
                });

                var json = await response.json().catch(function () {
                    return { ok: false, message: 'Respuesta invalida del servidor' };
                });

                if (!response.ok || !json.ok) {
                    throw new Error(json.message || 'Error en subida por bloques');
                }

                var percent = Math.floor(((index + 1) / totalChunks) * 100);
                updateProgress(percent, 'Subiendo... ' + percent + '% (' + (index + 1) + '/' + totalChunks + ')');
            }

            setStatus('Video subido y registrado correctamente.', 'success');
            updateProgress(100, 'Carga completada.');
            form.reset();
            window.setTimeout(function () {
                window.location.reload();
            }, 1000);
        } catch (error) {
            setStatus(error.message || 'No se pudo completar la subida.', 'error');
            updateProgress(0, 'Error en la subida.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
})();
