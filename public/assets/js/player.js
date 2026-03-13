(function () {
    var player = document.getElementById('player');
    var source = document.getElementById('playerSource');
    var list = document.querySelectorAll('.video-item');
    var playToggle = document.getElementById('playToggle');
    var volumeControl = document.getElementById('volumeControl');
    var fullscreenToggle = document.getElementById('fullscreenToggle');

    if (!player || !source || list.length === 0) {
        return;
    }

    var streamToken = player.getAttribute('data-stream-token') || '';

    player.controls = false;
    player.addEventListener('contextmenu', function (event) {
        event.preventDefault();
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

            player.requestFullscreen().catch(function () {
                return null;
            });
        });
    }

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

            source.src = 'stream.php?id=' + encodeURIComponent(id) + '&token=' + encodeURIComponent(streamToken);
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
        if ((event.ctrlKey || event.metaKey) && (key === 's' || key === 'u')) {
            event.preventDefault();
        }
    });
})();
