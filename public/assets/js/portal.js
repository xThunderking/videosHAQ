(function () {
    var openButton = document.getElementById('openRequestCodeModal');
    var modal = document.getElementById('requestCodeModal');
    var closeButton = document.querySelector('[data-close-request-modal]');
    var toastStack = document.getElementById('toastStack');
    var toastError = document.getElementById('portalToastError');
    var toastSuccess = document.getElementById('portalToastSuccess');

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

        var ttl = typeof durationMs === 'number' && durationMs > 0 ? durationMs : 4200;
        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }, ttl);
    }

    if (toastError) {
        showToast(toastError.getAttribute('data-toast-message') || '', 'error', 5200);
    }
    if (toastSuccess) {
        showToast(toastSuccess.getAttribute('data-toast-message') || '', 'success', 4200);
    }

    if (!openButton || !modal || !closeButton) {
        return;
    }

    function openModal() {
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    }

    openButton.addEventListener('click', function () {
        openModal();
    });

    closeButton.addEventListener('click', function () {
        closeModal();
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
})();
