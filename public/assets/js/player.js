(function () {
    var player = document.getElementById('player');
    var playerWrap = document.getElementById('playerWrap');
    var source = document.getElementById('playerSource');
    var list = document.querySelectorAll('.video-item');
    var playToggle = document.getElementById('playToggle');
    var volumeControl = document.getElementById('volumeControl');
    var fullscreenToggle = document.getElementById('fullscreenToggle');

    if (!player || !source || list.length === 0) {
        return;
    }

    var streamToken = player.getAttribute('data-stream-token') || '';
    var allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

    function normalizeMimeType(mime) {
        var normalized = String(mime || '').toLowerCase();
        if (allowedMimeTypes.indexOf(normalized) !== -1) {
            return normalized;
        }

        return 'video/mp4';
    }

    player.controls = false;
    player.setAttribute('controlsList', 'nodownload noplaybackrate noremoteplayback');
    player.setAttribute('disablePictureInPicture', '');
    player.setAttribute('disableRemotePlayback', '');
    player.disablePictureInPicture = true;
    player.addEventListener('contextmenu', function (event) {
        event.preventDefault();
    });
    player.addEventListener('dragstart', function (event) {
        event.preventDefault();
    });
    player.addEventListener('dblclick', function (event) {
        event.preventDefault();
    });

    if ('mediaSession' in navigator) {
        ['play', 'pause', 'seekbackward', 'seekforward', 'seekto'].forEach(function (action) {
            try {
                navigator.mediaSession.setActionHandler(action, null);
            } catch (e) {
                return null;
            }
        });
    }

    player.addEventListener('enterpictureinpicture', function () {
        if (document.pictureInPictureElement) {
            document.exitPictureInPicture().catch(function () {
                return null;
            });
        }
    });

    function updatePlayText() {
        if (!playToggle) {
            return;
        }

        playToggle.textContent = player.paused ? 'Reproducir' : 'Pausar';
    }

    if (playToggle) {
        playToggle.addEventListener('click', function () {
            if (player.paused) {
                player.play().catch(function () {
                    return null;
                });
                return;
            }

            player.pause();
        });
    }

    if (volumeControl) {
        player.volume = Number(volumeControl.value);
        volumeControl.addEventListener('input', function () {
            player.volume = Number(volumeControl.value);
        });
    }

    if (fullscreenToggle) {
        fullscreenToggle.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(function () {
                    return null;
                });
                return;
            }

            var fullscreenTarget = playerWrap || player;
            if (typeof fullscreenTarget.requestFullscreen === 'function') {
                fullscreenTarget.requestFullscreen().catch(function () {
                    return null;
                });
            }
        });
    }

    player.addEventListener('webkitbeginfullscreen', function () {
        player.pause();
    });

    player.addEventListener('play', updatePlayText);
    player.addEventListener('pause', updatePlayText);
    player.addEventListener('ended', updatePlayText);
    updatePlayText();

    list.forEach(function (item) {
        item.addEventListener('click', function () {
            var id = item.getAttribute('data-id');
            if (!id) {
                return;
            }
            var mime = normalizeMimeType(item.getAttribute('data-mime'));

            source.src = 'stream.php?id=' + encodeURIComponent(id) + '&token=' + encodeURIComponent(streamToken);
            source.type = mime;
            player.load();
            player.play().catch(function () {
                return null;
            });
            updatePlayText();

            list.forEach(function (other) {
                other.classList.remove('active');
            });
            item.classList.add('active');
        });
    });

    document.addEventListener('keydown', function (event) {
        var key = (event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'u' || key === 'p')) {
            event.preventDefault();
            return;
        }

        if ((event.ctrlKey && event.shiftKey) && (key === 'i' || key === 'j' || key === 'c')) {
            event.preventDefault();
            return;
        }

        if (key === 'f12') {
            event.preventDefault();
        }
    });

    document.addEventListener('contextmenu', function (event) {
        if (event.target === player || (playerWrap && playerWrap.contains(event.target))) {
            event.preventDefault();
        }
    });
})();
