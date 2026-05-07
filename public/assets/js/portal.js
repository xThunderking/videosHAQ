(function () {
    var toastStack = document.getElementById('toastStack');
    var toastError = document.getElementById('portalToastError');
    var toastSuccess = document.getElementById('portalToastSuccess');
    var systemsOpenTrigger = document.querySelector('[data-open-systems-modal]');
    var systemsModal = document.getElementById('systemsAccessModal');
    var systemsModalClose = document.querySelector('[data-close-systems-modal]');

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

    if (!systemsOpenTrigger || !systemsModal || !systemsModalClose) {
        return;
    }

    function focusFirstInput() {
        var firstInput = systemsModal.querySelector('input:not([type="hidden"]), button:not([disabled])');
        if (firstInput) {
            window.setTimeout(function () {
                firstInput.focus();
            }, 0);
        }
    }

    function openSystemsModal() {
        systemsModal.classList.remove('hidden');
        document.body.classList.add('modal-open');
        focusFirstInput();
    }

    function closeSystemsModal() {
        systemsModal.classList.add('hidden');
        document.body.classList.remove('modal-open');

        if (systemsOpenTrigger && typeof systemsOpenTrigger.focus === 'function') {
            window.setTimeout(function () {
                systemsOpenTrigger.focus();
            }, 0);
        }
    }

    if (!systemsModal.classList.contains('hidden')) {
        document.body.classList.add('modal-open');
        focusFirstInput();
    }

    systemsOpenTrigger.addEventListener('click', function () {
        openSystemsModal();
    });

    systemsModalClose.addEventListener('click', function () {
        closeSystemsModal();
    });

    systemsModal.addEventListener('click', function (event) {
        if (event.target === systemsModal) {
            closeSystemsModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !systemsModal.classList.contains('hidden')) {
            closeSystemsModal();
        }
    });
})();
