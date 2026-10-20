/**
 * Flash Card Set — card flipping for the Leitner (play) and free play screens.
 *
 * view.php loads this file on every flashcard page, but only the play screens print the
 * globals it reads (maxitems, and the free play counters currentitem / remaining) — so
 * everything below is guarded and the other pages do not throw.
 *
 * A card side is shown by clearing its inline display (""), so the theme decides how it
 * lays out (theme_nit scss/components/_flashcard.scss). Sound and video sides use the
 * browser's own <audio>/<video>; the upstream Flash (dewplayer, QuickTime) and flowplayer
 * calls are gone. Hiding a side pauses its media; showing one starts it only when the
 * activity's "Start sounds automatically" is on (data-autoplay="1").
 */
// jshint undef:false, unused:false

var cards = [];
if (typeof maxitems !== 'undefined') {
    for (var i = 0; i < maxitems; i++) {
        cards[i] = true;
    }
}

/**
 * Pause every player inside a side.
 *
 * @param {HTMLElement} side
 */
function nitFcPause(side) {
    if (!side) {
        return;
    }
    side.querySelectorAll('audio, video').forEach(function(media) {
        media.pause();
    });
}

/**
 * Show a side, starting its media when the activity asks for it.
 *
 * @param {HTMLElement} side
 */
function nitFcShow(side) {
    if (!side) {
        return;
    }
    side.style.display = "";
    side.querySelectorAll('audio[data-autoplay="1"], video[data-autoplay="1"]').forEach(function(media) {
        var played = media.play();
        if (played && played.catch) {
            // The browser may refuse autoplay before any user gesture; the controls remain.
            played.catch(function() {});
        }
    });
}

/**
 * Hide a side and stop what it plays.
 *
 * @param {HTMLElement} side
 */
function nitFcHide(side) {
    if (!side) {
        return;
    }
    nitFcPause(side);
    side.style.display = "none";
}

// Free play only.

function clicked(type, item) {
    var other = (type === 'f') ? 'b' : 'f';
    nitFcHide(document.getElementById(type + item));
    nitFcShow(document.getElementById(other + item));
}

function next_card() {
    nitFcHide(document.getElementById('f' + currentitem));
    nitFcHide(document.getElementById('b' + currentitem));
    do {
        currentitem++;
        if (currentitem >= maxitems) {
            currentitem = 0;
        }
    } while (cards[currentitem] !== true);
    nitFcShow(document.getElementById('f' + currentitem));
}

function previous_card() {
    nitFcHide(document.getElementById('f' + currentitem));
    nitFcHide(document.getElementById('b' + currentitem));
    do {
        currentitem--;
        if (currentitem < 0) {
            currentitem = maxitems - 1;
        }
    } while (cards[currentitem] !== true);
    nitFcShow(document.getElementById('f' + currentitem));
}

function remove_card() {
    remaining--;
    document.getElementById('remain').innerHTML = remaining;
    if (remaining === 0) {
        nitFcHide(document.getElementById('f' + currentitem));
        nitFcHide(document.getElementById('b' + currentitem));
        nitFcShow(document.getElementById('finished'));
        document.getElementById('next').disabled = true;
        document.getElementById('previous').disabled = true;
        document.getElementById('remove').disabled = true;
    } else {
        cards[currentitem] = false;
        next_card();
    }
}

// Leitner play only.

function togglecard() {
    var question = document.getElementById("questiondiv");
    var answer = document.getElementById("answerdiv");
    if (question.style.display === "none") {
        nitFcHide(answer);
        nitFcShow(question);
    } else {
        nitFcHide(question);
        nitFcShow(answer);
    }
}
