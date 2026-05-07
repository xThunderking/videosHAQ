(function () {
    var player = document.getElementById('player');
    var playerWrap = document.getElementById('playerWrap');
    var playerPanel = document.getElementById('videoPlayerPanel');
    var source = document.getElementById('playerSource');
    var cards = document.querySelectorAll('.video-card-item');
    var playToggle = document.getElementById('playToggle');
    var rewindToggle = document.getElementById('rewindToggle');
    var forwardToggle = document.getElementById('forwardToggle');
    var volumeControl = document.getElementById('volumeControl');
    var fullscreenToggle = document.getElementById('fullscreenToggle');
    var selectedVideoTitle = document.getElementById('selectedVideoTitle');
    var seekControl = document.getElementById('seekControl');
    var seekCurrent = document.getElementById('seekCurrent');
    var seekDuration = document.getElementById('seekDuration');

    if (!player || !source || cards.length === 0) {
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

    function formatTime(seconds) {
        var safeSeconds = Number(seconds);
        if (!isFinite(safeSeconds) || safeSeconds < 0) {
            safeSeconds = 0;
        }

        var total = Math.floor(safeSeconds);
        var minutes = Math.floor(total / 60);
        var secs = total % 60;

        return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function updateSeekUI() {
        if (!seekControl) {
            return;
        }

        var duration = Number(player.duration);
        var current = Number(player.currentTime);

        if (!isFinite(duration) || duration <= 0) {
            seekControl.value = '0';
            if (seekCurrent) {
                seekCurrent.textContent = '00:00';
            }
            if (seekDuration) {
                seekDuration.textContent = '00:00';
            }
            return;
        }

        var progress = Math.floor((current / duration) * 1000);
        if (progress < 0) {
            progress = 0;
        }
        if (progress > 1000) {
            progress = 1000;
        }

        seekControl.value = String(progress);

        if (seekCurrent) {
            seekCurrent.textContent = formatTime(current);
        }
        if (seekDuration) {
            seekDuration.textContent = formatTime(duration);
        }
    }

    function jumpSeconds(delta) {
        var duration = Number(player.duration);
        if (!isFinite(duration) || duration <= 0) {
            return;
        }

        var target = Number(player.currentTime) + delta;
        if (target < 0) {
            target = 0;
        }
        if (target > duration) {
            target = duration;
        }

        player.currentTime = target;
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

            var fullscreenTarget = playerPanel || playerWrap || player;
            if (typeof fullscreenTarget.requestFullscreen === 'function') {
                fullscreenTarget.requestFullscreen().catch(function () {
                    return null;
                });
            }
        });
    }

    if (rewindToggle) {
        rewindToggle.addEventListener('click', function () {
            jumpSeconds(-10);
        });
    }

    if (forwardToggle) {
        forwardToggle.addEventListener('click', function () {
            jumpSeconds(10);
        });
    }

    if (seekControl) {
        seekControl.addEventListener('input', function () {
            var duration = Number(player.duration);
            if (!isFinite(duration) || duration <= 0) {
                return;
            }

            var ratio = Number(seekControl.value) / 1000;
            player.currentTime = Math.max(0, Math.min(duration, ratio * duration));
        });
    }

    player.addEventListener('webkitbeginfullscreen', function () {
        player.pause();
    });

    player.addEventListener('play', updatePlayText);
    player.addEventListener('pause', updatePlayText);
    player.addEventListener('ended', updatePlayText);
    player.addEventListener('timeupdate', updateSeekUI);
    player.addEventListener('loadedmetadata', updateSeekUI);
    player.addEventListener('seeking', updateSeekUI);
    player.addEventListener('seeked', updateSeekUI);
    updatePlayText();
    updateSeekUI();

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            var id = card.getAttribute('data-id');
            if (!id) {
                return;
            }
            var mime = normalizeMimeType(card.getAttribute('data-mime'));
            var title = card.getAttribute('data-title') || 'Video';

            source.src = 'stream.php?id=' + encodeURIComponent(id) + '&token=' + encodeURIComponent(streamToken);
            source.type = mime;

            if (selectedVideoTitle) {
                selectedVideoTitle.textContent = title;
            }

            if (playerPanel) {
                playerPanel.classList.remove('hidden');
            }

            player.load();
            player.play().catch(function () {
                return null;
            });

            updatePlayText();
            updateSeekUI();

            cards.forEach(function (other) {
                other.classList.remove('active');
            });
            card.classList.add('active');

            if (playerPanel && typeof playerPanel.scrollIntoView === 'function') {
                playerPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
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
